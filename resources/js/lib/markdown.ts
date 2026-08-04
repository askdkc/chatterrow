import { mentionDisplayText, parseMentionTokens } from '@/lib/mentions';
import type { MentionResource } from '@/types';

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function mentionMarkup(
    kind: 'direct' | 'everyone',
    text: string,
    self = false,
): string {
    const classes = [
        'mention',
        kind === 'everyone' ? 'mention-everyone' : 'mention-direct',
        self ? 'mention-self' : '',
    ]
        .filter(Boolean)
        .join(' ');

    return `<span class="${classes}">${escapeHtml(text)}</span>`;
}

function renderMentions(
    value: string,
    mentions: readonly MentionResource[],
    currentUserId: number | null | undefined,
    preserve: (html: string) => string,
): string {
    const resolved = new Map(
        mentions
            .filter((mention) => mention.kind === 'direct')
            .map((mention) => [String(mention.id), mention]),
    );
    const tokens = parseMentionTokens(value);

    if (tokens.length === 0) {
        return value;
    }

    let rendered = '';
    let cursor = 0;

    for (const token of tokens) {
        rendered += value.slice(cursor, token.start);

        if (token.kind === 'everyone') {
            rendered += preserve(mentionMarkup('everyone', '@everyone'));
        } else {
            const mention =
                token.id === null ? undefined : resolved.get(token.id);

            if (!mention) {
                rendered += preserve(mentionMarkup('direct', '[deleted user]'));
            } else {
                rendered += preserve(
                    mentionMarkup(
                        'direct',
                        mentionDisplayText(mention),
                        currentUserId === mention.id,
                    ),
                );
            }
        }

        cursor = token.end;
    }

    return rendered + value.slice(cursor);
}

function renderInline(
    value: string,
    mentions: readonly MentionResource[],
    currentUserId: number | null | undefined,
): string {
    const replacements: string[] = [];
    const preserve = (html: string): string => {
        const index = replacements.push(html) - 1;

        return `\uE000${index}\uE001`;
    };

    let rendered = value.replace(/`([^`\n]+)`/g, (_, code: string) =>
        preserve(`<code>${escapeHtml(code)}</code>`),
    );

    rendered = rendered.replace(
        /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g,
        (_, label: string, url: string) =>
            preserve(
                `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${renderInline(label, mentions, currentUserId)}</a>`,
            ),
    );

    rendered = renderMentions(rendered, mentions, currentUserId, preserve);

    rendered = escapeHtml(rendered)
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_\n]+)__/g, '<u>$1</u>')
        .replace(/~~([^~\n]+)~~/g, '<s>$1</s>')
        .replace(/_([^_\n]+)_/g, '<em>$1</em>');

    return rendered.replace(/\uE000(\d+)\uE001/g, (_, index: string) => {
        return replacements[Number(index)] ?? '';
    });
}

function isBlockStart(line: string): boolean {
    return (
        line.startsWith('```') ||
        line.startsWith('- ') ||
        /^\d+\. /.test(line) ||
        line.startsWith('> ')
    );
}

type FencedCodeBlock = {
    code: string;
    language: string;
    token: string;
};

export type RenderedMessagePart =
    | { kind: 'html'; html: string }
    | { kind: 'code'; code: string; html: string; language: string };

function extractFencedCode(value: string): {
    blocks: FencedCodeBlock[];
    markdown: string;
} {
    const lines = value.split('\n');
    const markdown: string[] = [];
    const blocks: FencedCodeBlock[] = [];
    let index = 0;

    while (index < lines.length) {
        const opening = lines[index].match(/^[ \t]{0,3}(`{3,}|~{3,})([^\r]*)$/);

        if (!opening) {
            markdown.push(lines[index]);
            index += 1;
            continue;
        }

        const marker = opening[1] ?? '';
        const character = marker[0] ?? '`';
        const markerLength = marker.length;
        const language = (opening[2] ?? '').trim().split(/\s+/, 1)[0] ?? '';
        const code: string[] = [];
        index += 1;

        while (
            index < lines.length &&
            !new RegExp(
                `^[ \\t]{0,3}${character.repeat(markerLength)}${character}*[ \\t]*$`,
            ).test(lines[index])
        ) {
            code.push(lines[index]);
            index += 1;
        }

        index += index < lines.length ? 1 : 0;
        const token = `\uE100SHIKICODE${blocks.length}\uE101`;
        blocks.push({ code: code.join('\n'), language, token });
        markdown.push(token);
    }

    return { blocks, markdown: markdown.join('\n') };
}

function detectedLanguage(code: string): string {
    const trimmed = code.trim();

    if (/^<\?php\b/.test(trimmed)) {
        return 'php';
    }

    if (/^<(?:!doctype|html|head|body|[a-z][\w-]*[\s>])/i.test(trimmed)) {
        return 'html';
    }

    if (/^\s*\((?:defun|lambda|let|setq|print|princ)\b/i.test(trimmed)) {
        return 'common-lisp';
    }

    if (/^(?:const|let|var|function|import|export)\b/m.test(trimmed)) {
        return 'javascript';
    }

    if (/^[{[]/.test(trimmed)) {
        try {
            JSON.parse(trimmed);

            return 'json';
        } catch {
            // Continue without guessing when the content is not valid JSON.
        }
    }

    return '';
}

function renderMarkdownContent(
    value: string,
    mentions: readonly MentionResource[] = [],
    currentUserId?: number | null,
): string {
    const lines = value.split('\n');
    const blocks: string[] = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (line.startsWith('- ')) {
            const items: string[] = [];

            while (index < lines.length && lines[index].startsWith('- ')) {
                items.push(
                    `<li>${renderInline(lines[index].slice(2), mentions, currentUserId)}</li>`,
                );
                index += 1;
            }

            blocks.push(`<ul>${items.join('')}</ul>`);
            continue;
        }

        if (/^\d+\. /.test(line)) {
            const items: string[] = [];

            while (index < lines.length && /^\d+\. /.test(lines[index])) {
                items.push(
                    `<li>${renderInline(lines[index].replace(/^\d+\. /, ''), mentions, currentUserId)}</li>`,
                );
                index += 1;
            }

            blocks.push(`<ol>${items.join('')}</ol>`);
            continue;
        }

        if (line.startsWith('> ')) {
            const quote: string[] = [];

            while (index < lines.length && lines[index].startsWith('> ')) {
                quote.push(
                    renderInline(
                        lines[index].slice(2),
                        mentions,
                        currentUserId,
                    ),
                );
                index += 1;
            }

            blocks.push(`<blockquote>${quote.join('<br>')}</blockquote>`);
            continue;
        }

        if (line === '') {
            blocks.push('<br>');
            index += 1;
            continue;
        }

        const paragraph: string[] = [];

        while (
            index < lines.length &&
            lines[index] !== '' &&
            !isBlockStart(lines[index])
        ) {
            paragraph.push(renderInline(lines[index], mentions, currentUserId));
            index += 1;
        }

        blocks.push(paragraph.join('<br>'));
    }

    return blocks.join('');
}

function requestedLanguage(block: FencedCodeBlock): string {
    return (block.language || detectedLanguage(block.code)).toLocaleLowerCase(
        'en-US',
    );
}

function fallbackCodePart(block: FencedCodeBlock): RenderedMessagePart {
    return {
        kind: 'code',
        code: block.code,
        language: requestedLanguage(block) || 'text',
        html: `<pre><code>${escapeHtml(block.code)}</code></pre>`,
    };
}

function interleaveMessageParts(
    rendered: string,
    blocks: readonly FencedCodeBlock[],
    codeParts: readonly RenderedMessagePart[],
): RenderedMessagePart[] {
    const parts: RenderedMessagePart[] = [];
    let cursor = 0;

    blocks.forEach((block, index) => {
        const tokenIndex = rendered.indexOf(block.token, cursor);

        if (tokenIndex < 0) {
            return;
        }

        if (tokenIndex > cursor) {
            parts.push({
                kind: 'html',
                html: rendered.slice(cursor, tokenIndex),
            });
        }

        parts.push(codeParts[index] ?? fallbackCodePart(block));
        cursor = tokenIndex + block.token.length;
    });

    if (cursor < rendered.length) {
        parts.push({ kind: 'html', html: rendered.slice(cursor) });
    }

    return parts;
}

function joinMessageParts(parts: readonly RenderedMessagePart[]): string {
    return parts.map((part) => part.html).join('');
}

export function renderMessageMarkdownParts(
    value: string,
    mentions: readonly MentionResource[] = [],
    currentUserId?: number | null,
): RenderedMessagePart[] {
    const extracted = extractFencedCode(value);
    const rendered = renderMarkdownContent(
        extracted.markdown,
        mentions,
        currentUserId,
    );

    return interleaveMessageParts(
        rendered,
        extracted.blocks,
        extracted.blocks.map(fallbackCodePart),
    );
}

export function renderMessageMarkdown(
    value: string,
    mentions: readonly MentionResource[] = [],
    currentUserId?: number | null,
): string {
    return joinMessageParts(
        renderMessageMarkdownParts(value, mentions, currentUserId),
    );
}

export async function renderHighlightedMessageMarkdownParts(
    value: string,
    mentions: readonly MentionResource[] = [],
    currentUserId?: number | null,
): Promise<RenderedMessagePart[]> {
    const extracted = extractFencedCode(value);

    if (extracted.blocks.length === 0) {
        return renderMessageMarkdownParts(value, mentions, currentUserId);
    }

    const rendered = renderMarkdownContent(
        extracted.markdown,
        mentions,
        currentUserId,
    );

    try {
        const shiki = await import('shiki/bundle/web');
        const codeParts = await Promise.all(
            extracted.blocks.map(async (block) => {
                const requested = requestedLanguage(block);
                const language = shiki.bundledLanguagesInfo.find(
                    (candidate) =>
                        candidate.id === requested ||
                        candidate.aliases?.includes(requested),
                );

                if (!language) {
                    return fallbackCodePart(block);
                }

                return {
                    kind: 'code' as const,
                    code: block.code,
                    language: language.id,
                    html: await shiki.codeToHtml(block.code, {
                        lang: language.id,
                        themes: {
                            light: 'github-light-high-contrast',
                            dark: 'github-dark-high-contrast',
                        },
                    }),
                };
            }),
        );

        return interleaveMessageParts(rendered, extracted.blocks, codeParts);
    } catch {
        return renderMessageMarkdownParts(value, mentions, currentUserId);
    }
}

export async function renderHighlightedMessageMarkdown(
    value: string,
    mentions: readonly MentionResource[] = [],
    currentUserId?: number | null,
): Promise<string> {
    return joinMessageParts(
        await renderHighlightedMessageMarkdownParts(
            value,
            mentions,
            currentUserId,
        ),
    );
}

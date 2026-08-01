function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderInline(value: string): string {
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
                `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${renderInline(label)}</a>`,
            ),
    );

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

function extractFencedCode(value: string): {
    blocks: FencedCodeBlock[];
    markdown: string;
} {
    const lines = value.split('\n');
    const markdown: string[] = [];
    const blocks: FencedCodeBlock[] = [];
    let index = 0;

    while (index < lines.length) {
        if (!lines[index].startsWith('```')) {
            markdown.push(lines[index]);
            index += 1;
            continue;
        }

        const language = lines[index].slice(3).trim().split(/\s+/, 1)[0] ?? '';
        const code: string[] = [];
        index += 1;

        while (index < lines.length && !lines[index].startsWith('```')) {
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

export function renderMessageMarkdown(value: string): string {
    const lines = value.split('\n');
    const blocks: string[] = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (line.startsWith('```')) {
            const code: string[] = [];
            index += 1;

            while (index < lines.length && !lines[index].startsWith('```')) {
                code.push(lines[index]);
                index += 1;
            }

            index += index < lines.length ? 1 : 0;
            blocks.push(
                `<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`,
            );
            continue;
        }

        if (line.startsWith('- ')) {
            const items: string[] = [];

            while (index < lines.length && lines[index].startsWith('- ')) {
                items.push(`<li>${renderInline(lines[index].slice(2))}</li>`);
                index += 1;
            }

            blocks.push(`<ul>${items.join('')}</ul>`);
            continue;
        }

        if (/^\d+\. /.test(line)) {
            const items: string[] = [];

            while (index < lines.length && /^\d+\. /.test(lines[index])) {
                items.push(
                    `<li>${renderInline(lines[index].replace(/^\d+\. /, ''))}</li>`,
                );
                index += 1;
            }

            blocks.push(`<ol>${items.join('')}</ol>`);
            continue;
        }

        if (line.startsWith('> ')) {
            const quote: string[] = [];

            while (index < lines.length && lines[index].startsWith('> ')) {
                quote.push(renderInline(lines[index].slice(2)));
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
            paragraph.push(renderInline(lines[index]));
            index += 1;
        }

        blocks.push(paragraph.join('<br>'));
    }

    return blocks.join('');
}

export async function renderHighlightedMessageMarkdown(
    value: string,
): Promise<string> {
    const extracted = extractFencedCode(value);

    if (extracted.blocks.length === 0) {
        return renderMessageMarkdown(value);
    }

    const rendered = renderMessageMarkdown(extracted.markdown);

    try {
        const shiki = await import('shiki/bundle/web');
        const highlighted = await Promise.all(
            extracted.blocks.map(async (block) => {
                const requested = (
                    block.language || detectedLanguage(block.code)
                ).toLocaleLowerCase('en-US');
                const language = shiki.bundledLanguagesInfo.find(
                    (candidate) =>
                        candidate.id === requested ||
                        candidate.aliases?.includes(requested),
                );

                if (!language) {
                    return `<pre><code>${escapeHtml(block.code)}</code></pre>`;
                }

                return shiki.codeToHtml(block.code, {
                    lang: language.id,
                    theme: 'github-dark',
                });
            }),
        );

        return extracted.blocks.reduce(
            (html, block, index) =>
                html.replace(block.token, highlighted[index]),
            rendered,
        );
    } catch {
        return renderMessageMarkdown(value);
    }
}

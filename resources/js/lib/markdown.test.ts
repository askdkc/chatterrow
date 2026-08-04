import { describe, expect, it } from 'vitest';
import type { MentionResource } from '@/types';
import {
    renderHighlightedMessageMarkdown,
    renderHighlightedMessageMarkdownParts,
    renderMessageMarkdown,
} from './markdown';

describe('renderMessageMarkdown', () => {
    it('renders composer formatting syntax', () => {
        const rendered = renderMessageMarkdown(
            '**太字** _斜体_ __下線__ ~~取消~~ `code`',
        );

        expect(rendered).toBe(
            '<strong>太字</strong> <em>斜体</em> <u>下線</u> <s>取消</s> <code>code</code>',
        );
    });

    it('renders links, lists, quotes, and code blocks', () => {
        const rendered = renderMessageMarkdown(
            '[Laravel](https://laravel.com)\n- one\n- two\n> quote\n```\nconst ok = true;\n```',
        );

        expect(rendered).toContain('href="https://laravel.com"');
        expect(rendered).toContain('<ul><li>one</li><li>two</li></ul>');
        expect(rendered).toContain('<blockquote>quote</blockquote>');
        expect(rendered).toContain('<pre><code>const ok = true;</code></pre>');
    });

    it('escapes HTML and rejects unsafe link schemes', () => {
        const rendered = renderMessageMarkdown(
            '<img src=x onerror=alert(1)> [bad](javascript:alert(1))',
        );

        expect(rendered).toContain('&lt;img');
        expect(rendered).not.toContain('<img');
        expect(rendered).not.toContain('href=');
    });

    it('renders resolved, everyone, and self mentions without exposing IDs', () => {
        const mentions: MentionResource[] = [
            { id: 12, name: 'Alice <Admin>', kind: 'direct' },
        ];
        const rendered = renderMessageMarkdown(
            '<@12> <!everyone> <@999>',
            mentions,
            12,
        );

        expect(rendered).toContain(
            'class="mention mention-direct mention-self"',
        );
        expect(rendered).toContain('&lt;Admin&gt;');
        expect(rendered).toContain('class="mention mention-everyone"');
        expect(rendered).toContain('[deleted user]');
        expect(rendered).not.toContain('<@999>');
    });

    it('does not convert mentions in inline or fenced code', () => {
        const mentions: MentionResource[] = [
            { id: 12, name: 'Alice', kind: 'direct' },
        ];
        const rendered = renderMessageMarkdown(
            '`<@12>`\n~~~\n<@12>\n~~~\n<@12>',
            mentions,
            12,
        );

        expect(rendered).toContain('<code>&lt;@12&gt;</code>');
        expect(rendered).toContain('<pre><code>&lt;@12&gt;</code></pre>');
        expect(rendered).toContain(
            'class="mention mention-direct mention-self"',
        );
    });
});

describe('renderHighlightedMessageMarkdown', () => {
    it('highlights a fenced code block with an explicit language', async () => {
        const rendered = await renderHighlightedMessageMarkdown(
            '```js\nconst answer = 42;\n```',
        );

        expect(rendered).toContain('class="shiki');
        expect(rendered).toContain('github-light-high-contrast');
        expect(rendered).toContain('github-dark-high-contrast');
        expect(rendered).toContain('background-color:#ffffff');
        expect(rendered).toContain('--shiki-dark-bg:#0a0c10');
        expect(rendered).toContain('--shiki-dark');
        expect(rendered).toContain('<span');
        expect(rendered).toContain('answer');
    });

    it('detects HTML while keeping its source escaped', async () => {
        const source = '```\n<h1>Hello</h1>\n```';
        const rendered = await renderHighlightedMessageMarkdown(source);
        const parts = await renderHighlightedMessageMarkdownParts(source);
        const codePart = parts.find((part) => part.kind === 'code');

        if (!codePart || codePart.kind !== 'code') {
            throw new Error('Expected an HTML code part');
        }

        const tokenColors = Array.from(
            codePart.html.matchAll(/style="color:(#[0-9a-f]{6})/gi),
            (match) => match[1].toLowerCase(),
        );

        expect(codePart.language).toBe('html');
        expect(new Set(tokenColors).size).toBeGreaterThan(1);
        expect(rendered).toContain('class="shiki');
        expect(rendered).not.toContain('<h1>Hello</h1>');
        expect(rendered).toContain('&#x3C;');
    });

    it('keeps an explicit HTML language label', async () => {
        const parts = await renderHighlightedMessageMarkdownParts(
            '```html\n<div class="note">Hello</div>\n```',
        );
        const codePart = parts.find((part) => part.kind === 'code');

        expect(codePart?.kind).toBe('code');
        expect(codePart?.kind === 'code' && codePart.language).toBe('html');
    });
});

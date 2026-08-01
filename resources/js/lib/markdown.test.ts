import { describe, expect, it } from 'vitest';
import {
    renderHighlightedMessageMarkdown,
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
});

describe('renderHighlightedMessageMarkdown', () => {
    it('highlights a fenced code block with an explicit language', async () => {
        const rendered = await renderHighlightedMessageMarkdown(
            '```js\nconst answer = 42;\n```',
        );

        expect(rendered).toContain('class="shiki');
        expect(rendered).toContain('<span');
        expect(rendered).toContain('answer');
    });

    it('detects HTML while keeping its source escaped', async () => {
        const rendered = await renderHighlightedMessageMarkdown(
            '```\n<h1>Hello</h1>\n```',
        );

        expect(rendered).toContain('class="shiki');
        expect(rendered).not.toContain('<h1>Hello</h1>');
        expect(rendered).toContain('&#x3C;');
    });
});

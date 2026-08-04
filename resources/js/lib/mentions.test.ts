import { describe, expect, it } from 'vitest';
import {
    findMentionQuery,
    parseMentionTokens,
    replaceMentionRange,
    restoreDraftMentions,
    safeMentionText,
    serializeDraftMentions,
    updateMentionAnchors,
} from './mentions';

const alice = {
    id: 12,
    kind: 'direct' as const,
    name: 'Alice <Admin>',
    email: 'alice@example.com',
};

describe('mention parsing', () => {
    it('parses direct and everyone tokens outside code', () => {
        const body =
            '<@12> <!everyone> ` <@13> `\n```\n<@14>\n```\n~~~\n<!everyone>\n~~~';

        expect(
            parseMentionTokens(body).map(({ kind, id, raw }) => ({
                kind,
                id,
                raw,
            })),
        ).toEqual([
            { kind: 'direct', id: '12', raw: '<@12>' },
            { kind: 'everyone', id: null, raw: '<!everyone>' },
        ]);
    });

    it('finds a composer query and excludes code ranges', () => {
        expect(findMentionQuery('hello @ali', 10)).toEqual({
            query: 'ali',
            start: 6,
            end: 10,
        });
        expect(findMentionQuery('`@ali` @bo', 10)).toEqual({
            query: 'bo',
            start: 7,
            end: 10,
        });
        expect(findMentionQuery('`@ali`', 5)).toBeNull();
    });
});

describe('draft mention anchors', () => {
    it('replaces a query, serializes the anchor, and restores it', () => {
        const replacement = replaceMentionRange(
            'Hello @ali',
            { start: 6, end: 10 },
            alice,
        );

        expect(replacement.value).toBe('Hello @Alice <Admin> ');
        expect(replacement.cursor).toBe(replacement.value.length);
        expect(
            serializeDraftMentions(replacement.value, replacement.anchors),
        ).toBe('Hello <@12> ');

        const restored = restoreDraftMentions('Hello <@12> <!everyone>', [
            alice,
        ]);

        expect(restored.value).toBe('Hello @Alice <Admin> @everyone');
        expect(serializeDraftMentions(restored.value, restored.anchors)).toBe(
            'Hello <@12> <!everyone>',
        );
    });

    it('drops an anchor when its display text is edited', () => {
        const replacement = replaceMentionRange(
            '@a',
            { start: 0, end: 2 },
            alice,
        );
        const changed = `${replacement.value.slice(0, 2)}X${replacement.value.slice(2)}`;
        const anchors = updateMentionAnchors(
            replacement.value,
            changed,
            replacement.anchors,
        );

        expect(anchors).toEqual([]);
        expect(serializeDraftMentions(changed, anchors)).toBe(changed);
    });
});

describe('safeMentionText', () => {
    it('never returns an unresolved direct token outside code', () => {
        expect(safeMentionText('<@999> `<!everyone>`', [])).toBe(
            '[deleted user] `<!everyone>`',
        );
    });
});

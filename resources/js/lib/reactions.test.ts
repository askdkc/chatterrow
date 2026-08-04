import { describe, expect, it } from 'vitest';
import {
    createStampReaction,
    isStampReaction,
    normalizeStampReaction,
    normalizeStampText,
    parseStampReaction,
    reactionDisplayLabel,
    stampReactionStyle,
    stampReactionText,
} from './reactions';

describe('stamp reactions', () => {
    it('normalizes and caps entered stamp text', () => {
        expect(normalizeStampText('  了解   です  ')).toBe('了解 で');
        expect(normalizeStampText('1234567')).toBe('1234');
        expect(isStampReaction('stamp:12345')).toBe(false);
    });

    it('creates and reads a namespaced stamp reaction', () => {
        expect(createStampReaction('それな')).toBe('stamp:それな');
        expect(stampReactionText('stamp:それな')).toBe('それな');
        expect(isStampReaction('stamp:それな')).toBe(true);
        expect(isStampReaction('👍')).toBe(false);
    });

    it('encodes and reads custom text and background colors', () => {
        const styled = createStampReaction('了解', {
            textColor: '#FF0000',
            backgroundColor: '#00FF00',
        });
        const transparent = createStampReaction('確認', {
            textColor: '#5865F2',
            backgroundColor: null,
        });

        expect(styled).toBe('stamp:v1:ff0000:00ff00:了解');
        expect(parseStampReaction(styled!)).toEqual({
            text: '了解',
            style: {
                textColor: '#ff0000',
                backgroundColor: '#00ff00',
            },
        });
        expect(stampReactionStyle(transparent!)).toEqual({
            textColor: '#5865f2',
            backgroundColor: null,
        });
        expect(normalizeStampReaction(styled!)).toBe(styled);
        expect(
            createStampReaction('不可', {
                textColor: 'red',
                backgroundColor: '#ffffff',
            }),
        ).toBeNull();
        expect(isStampReaction('stamp:v1:zzzzzz:ffffff:不可')).toBe(false);
    });

    it('provides an accessible display label', () => {
        expect(reactionDisplayLabel('stamp:たしかに')).toBe(
            'ハンコ「たしかに」',
        );
        expect(reactionDisplayLabel('😂')).toBe('😂');
    });
});

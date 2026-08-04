import { cleanup, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import StampReaction from './StampReaction.svelte';

afterEach(() => cleanup());

describe('StampReaction typography', () => {
    it.each([
        ['あ', 'text-[40px]', '1'],
        ['了解', 'text-[22px]', '2'],
        ['確認済', 'text-[20px]', '3'],
        ['たしかに', 'text-[18px]', '4'],
    ])('sizes picker text for %s', (text, expectedClass, count) => {
        const { container } = render(StampReaction, {
            props: { value: `stamp:${text}` },
        });
        const stamp = container.querySelector('[data-stamp-reaction]');
        const glyphs = container.querySelector('[data-stamp-glyphs]');

        expect(stamp?.getAttribute('data-stamp-character-count')).toBe(count);
        expect(glyphs?.classList.contains(expectedClass)).toBe(true);
        expect(glyphs?.classList.contains('size-full')).toBe(true);
        expect(glyphs?.classList.contains('leading-[0.78]')).toBe(true);
    });

    it.each([
        ['あ', 'text-[20px]'],
        ['了解', 'text-[11px]'],
        ['確認済', 'text-[10px]'],
        ['たしかに', 'text-[9px]'],
    ])('sizes reaction text for %s', (text, expectedClass) => {
        const { container } = render(StampReaction, {
            props: { value: `stamp:${text}`, size: 'reaction' },
        });
        const stamp = container.querySelector('[data-stamp-reaction]');
        const glyphs = container.querySelector('[data-stamp-glyphs]');

        expect(stamp?.classList.contains('size-[22px]')).toBe(true);
        expect(glyphs?.classList.contains(expectedClass)).toBe(true);
    });

    it.each([
        ['あ', 'text-[17px]'],
        ['了解', 'text-[9px]'],
        ['確認済', 'text-[8px]'],
        ['たしかに', 'text-[7px]'],
    ])('sizes compact preview text for %s', (text, expectedClass) => {
        const { container } = render(StampReaction, {
            props: { value: `stamp:${text}`, size: 'chip' },
        });
        const glyphs = container.querySelector('[data-stamp-glyphs]');

        expect(glyphs?.classList.contains(expectedClass)).toBe(true);
    });
});

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import EmojiPicker from './EmojiPicker.svelte';

afterEach(cleanup);

describe('EmojiPicker', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('opens the quick row, inserts an emoji, and closes', async () => {
        const onselect = vi.fn();
        render(EmojiPicker, { props: { onselect } });

        await fireEvent.click(
            screen.getByRole('button', { name: '絵文字を選ぶ' }),
        );

        expect(await screen.findByLabelText('クイック絵文字')).toBeTruthy();
        expect(screen.queryByLabelText('絵文字一覧')).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: '👍を挿入' }));

        expect(onselect).toHaveBeenCalledWith('👍');
        await waitFor(() => {
            expect(screen.queryByLabelText('クイック絵文字')).toBeNull();
        });
        expect(
            JSON.parse(localStorage.getItem('chatterrow.recent-emojis')!)[0],
        ).toBe('👍');
    });

    it('expands upward into searchable categories', async () => {
        render(EmojiPicker, { props: { onselect: vi.fn() } });

        await fireEvent.click(
            screen.getByRole('button', { name: '絵文字を選ぶ' }),
        );
        await fireEvent.click(
            await screen.findByRole('button', {
                name: 'すべての絵文字を表示',
            }),
        );

        expect(await screen.findByLabelText('絵文字一覧')).toBeTruthy();
        expect(
            screen
                .getByRole('button', { name: '顔と感情カテゴリー' })
                .getAttribute('aria-pressed'),
        ).toBe('true');

        await fireEvent.click(
            screen.getByRole('button', { name: '動物と自然カテゴリー' }),
        );
        expect(screen.getByRole('button', { name: '🐱を挿入' })).toBeTruthy();

        await fireEvent.input(screen.getByLabelText('絵文字を検索'), {
            target: { value: 'りんご' },
        });
        expect(screen.getByRole('button', { name: '🍎を挿入' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: '🐱を挿入' })).toBeNull();
    });
});

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
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

    it('creates a namespaced text stamp in reaction mode', async () => {
        const onselect = vi.fn();
        localStorage.setItem(
            'chatterrow.recent-emojis',
            JSON.stringify(['🖥️']),
        );
        render(EmojiPicker, { props: { mode: 'reaction', onselect } });

        await fireEvent.click(
            screen.getByRole('button', { name: 'リアクションを追加' }),
        );
        const stampTrigger = await screen.findByRole('button', {
            name: '文字ハンコを作る',
        });

        expect(stampTrigger.textContent).toContain('💬');
        expect(stampTrigger.classList.contains('text-[22px]')).toBe(true);
        expect(document.querySelector('.lucide-stamp')).toBeNull();
        expect(
            screen
                .getByRole('button', { name: '🖥️をリアクションに追加' })
                .classList.contains('text-[22px]'),
        ).toBe(true);

        await fireEvent.click(stampTrigger);

        expect(
            screen.queryByRole('button', {
                name: '💬をリアクションに追加',
            }),
        ).toBeNull();

        expect(await screen.findByLabelText('文字ハンコ')).toBeTruthy();
        const stampInput = screen.getByLabelText(
            'ハンコにする文字',
        ) as HTMLInputElement;
        expect(stampInput.maxLength).toBe(4);
        expect(stampInput.placeholder).toBe('文字を入力（4文字まで）');
        expect(
            screen.getByRole('button', {
                name: 'ハンコ「それな」をリアクションに追加',
            }),
        ).toBeTruthy();

        await fireEvent.input(screen.getByLabelText('ハンコにする文字'), {
            target: { value: '了解' },
        });
        await fireEvent.input(screen.getByLabelText('文字色'), {
            target: { value: '#e11d48' },
        });
        await fireEvent.input(screen.getByLabelText('背景色'), {
            target: { value: '#fef3c7' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '追加' }));

        const registeredReaction = 'stamp:v1:e11d48:fef3c7:了解';

        expect(onselect).toHaveBeenCalledWith(registeredReaction);
        expect(
            JSON.parse(
                localStorage.getItem('chatterrow.registered-stamp-reactions')!,
            ),
        ).toEqual([registeredReaction]);

        cleanup();

        render(EmojiPicker, {
            props: { mode: 'reaction', onselect: vi.fn() },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'リアクションを追加' }),
        );
        await fireEvent.click(
            await screen.findByRole('button', { name: '文字ハンコを作る' }),
        );

        const registeredButton = await screen.findByRole('button', {
            name: 'ハンコ「了解」をリアクションに追加',
        });
        const registeredStamp = registeredButton.querySelector(
            '[data-stamp-reaction]',
        );

        expect(registeredStamp?.getAttribute('data-stamp-text-color')).toBe(
            '#e11d48',
        );
        expect(
            registeredStamp?.getAttribute('data-stamp-background-color'),
        ).toBe('#fef3c7');
    });

    it('creates a text stamp without a background', async () => {
        const onselect = vi.fn();
        render(EmojiPicker, { props: { mode: 'reaction', onselect } });

        await fireEvent.click(
            screen.getByRole('button', { name: 'リアクションを追加' }),
        );
        await fireEvent.click(
            await screen.findByRole('button', { name: '文字ハンコを作る' }),
        );
        await fireEvent.input(screen.getByLabelText('ハンコにする文字'), {
            target: { value: '確認' },
        });
        await fireEvent.input(screen.getByLabelText('文字色'), {
            target: { value: '#5865f2' },
        });
        await fireEvent.click(
            screen.getByRole('checkbox', { name: '背景なし' }),
        );
        await fireEvent.click(screen.getByRole('button', { name: '追加' }));

        expect(onselect).toHaveBeenCalledWith('stamp:v1:5865f2:none:確認');
    });

    it('syncs a registered stamp to other mounted pickers', async () => {
        const firstPicker = render(EmojiPicker, {
            props: { mode: 'reaction', onselect: vi.fn() },
        });
        const secondPicker = render(EmojiPicker, {
            props: { mode: 'reaction', onselect: vi.fn() },
        });

        await fireEvent.click(
            within(firstPicker.container).getByRole('button', {
                name: 'リアクションを追加',
            }),
        );
        await fireEvent.click(
            await screen.findByRole('button', { name: '文字ハンコを作る' }),
        );
        await fireEvent.input(screen.getByLabelText('ハンコにする文字'), {
            target: { value: '確認済' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '追加' }));

        await fireEvent.click(
            within(secondPicker.container).getByRole('button', {
                name: 'リアクションを追加',
            }),
        );
        await fireEvent.click(
            await screen.findByRole('button', { name: '文字ハンコを作る' }),
        );

        expect(
            await screen.findByRole('button', {
                name: 'ハンコ「確認済」をリアクションに追加',
            }),
        ).toBeTruthy();
    });
});

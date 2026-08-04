import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { MessageResource } from '@/types';
import MessageItem from './MessageItem.svelte';

const originalClipboard = Object.getOwnPropertyDescriptor(
    window.navigator,
    'clipboard',
);

afterEach(() => {
    cleanup();

    if (originalClipboard) {
        Object.defineProperty(window.navigator, 'clipboard', originalClipboard);
    } else {
        Reflect.deleteProperty(window.navigator, 'clipboard');
    }
});

const baseMessage: MessageResource = {
    id: 1,
    server_id: 2,
    channel_id: 3,
    user_id: 4,
    parent_id: null,
    body: 'message',
    created_at: '2026-08-01T12:00:00Z',
    user: { id: 4, name: 'Test User', email: 'test@example.com' },
};

describe('MessageItem layout', () => {
    it.each([
        ['the current user', 4],
        ['another user', 9],
    ])('uses the same left-aligned layout for %s', (_, currentUserId) => {
        const { container } = render(MessageItem, {
            props: { message: baseMessage, currentUserId },
        });
        const item = container.querySelector('[data-message-id="1"]');
        const content = container.querySelector('[data-message-content]');
        const body = container.querySelector('[data-message-body]');
        const avatar = container.querySelector('[data-message-avatar]');

        expect(item?.hasAttribute('data-message-side')).toBe(false);
        expect(item?.classList.contains('justify-end')).toBe(false);
        expect(content?.classList.contains('flex-1')).toBe(true);
        expect(body?.classList.contains('rounded-2xl')).toBe(false);
        expect(body?.classList.contains('bg-secondary')).toBe(false);
        expect(body?.classList.contains('text-sm')).toBe(true);
        expect(body?.classList.contains('leading-5')).toBe(true);
        expect(body?.classList.contains('[&_pre]:m-0')).toBe(true);
        expect(body?.classList.contains('[&_pre]:rounded-none')).toBe(true);
        expect(body?.classList.contains('[&_pre]:border-0')).toBe(true);
        expect(body?.classList.contains('[&_pre]:bg-code-block')).toBe(true);
        expect(body?.classList.contains('[&_pre]:p-2')).toBe(true);
        expect(body?.classList.contains('[&_pre]:text-[13px]')).toBe(true);
        expect(body?.classList.contains('[&_pre]:leading-[1.45]')).toBe(true);
        expect(container.querySelector('[data-message-bubble]')).toBeNull();
        expect(avatar?.classList.contains('size-9')).toBe(true);
        expect(avatar?.getAttribute('data-avatar-tone')).toBe('4');
        expect(screen.getByLabelText('Test Userのアイコン')).toBeTruthy();
        expect(screen.getByText('Test User')).toBeTruthy();
    });

    it('uses a stable color for each initial', () => {
        const taro = render(MessageItem, {
            props: {
                message: {
                    ...baseMessage,
                    id: 2,
                    user: {
                        id: 5,
                        name: 'Taro Yamada',
                        email: 'taro@example.com',
                    },
                },
            },
        });
        const yokoyama = render(MessageItem, {
            props: {
                message: {
                    ...baseMessage,
                    id: 3,
                    user: {
                        id: 6,
                        name: 'yokoyama',
                        email: 'yokoyama@example.com',
                    },
                },
            },
        });
        const taroAvatar = taro.container.querySelector(
            '[data-message-avatar]',
        );
        const yokoyamaAvatar = yokoyama.container.querySelector(
            '[data-message-avatar]',
        );

        expect(taroAvatar?.textContent?.trim()).toBe('T');
        expect(taroAvatar?.getAttribute('data-avatar-tone')).toBe('4');
        expect(yokoyamaAvatar?.textContent?.trim()).toBe('Y');
        expect(yokoyamaAvatar?.getAttribute('data-avatar-tone')).toBe('1');
    });

    it('labels and copies an auto-detected HTML block', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(window.navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
        const message: MessageResource = {
            ...baseMessage,
            body: '```\n<h1>hello</h1>\n<p>子育て</p>\n```',
        };
        const { container } = render(MessageItem, { props: { message } });

        expect(screen.getByText('HTML')).toBeTruthy();
        expect(
            container.querySelector('[data-code-language="html"]'),
        ).toBeTruthy();

        const copyButton = screen.getByRole('button', {
            name: 'コードをコピー',
        });
        await fireEvent.click(copyButton);

        expect(writeText).toHaveBeenCalledWith('<h1>hello</h1>\n<p>子育て</p>');
        await waitFor(() =>
            expect(
                screen.getByRole('button', {
                    name: 'コードをコピーしました',
                }),
            ).toBeTruthy(),
        );
    });
});

describe('MessageItem reactions', () => {
    it('shows grouped counts and toggles the current user reaction', async () => {
        const onSetReaction = vi.fn().mockResolvedValue(undefined);
        const message: MessageResource = {
            ...baseMessage,
            reactions: [
                {
                    emoji: '👍',
                    count: 2,
                    user_ids: [4, 9],
                    user_names: ['Test User', 'Other User'],
                },
            ],
        };

        render(MessageItem, {
            props: { message, currentUserId: 4, onSetReaction },
        });

        const reaction = screen.getByRole('button', {
            name: '👍リアクション 2件、自分が追加済み',
        });
        expect(reaction.getAttribute('aria-pressed')).toBe('true');
        expect(reaction.classList.contains('h-7')).toBe(true);
        expect(reaction.classList.contains('rounded-full')).toBe(true);
        expect(reaction.classList.contains('text-sm')).toBe(true);
        expect(
            reaction
                .querySelector('[aria-hidden="true"]')
                ?.classList.contains('text-base'),
        ).toBe(true);
        expect(
            reaction
                .querySelector('.tabular-nums')
                ?.classList.contains('text-sm'),
        ).toBe(true);
        expect(reaction.getAttribute('title')).toBe(
            'Test User、Other Userが👍でリアクション',
        );

        await fireEvent.click(reaction);
        expect(onSetReaction).toHaveBeenCalledWith('👍', false);
    });

    it('renders and toggles a text stamp reaction', async () => {
        const onSetReaction = vi.fn().mockResolvedValue(undefined);
        const message: MessageResource = {
            ...baseMessage,
            reactions: [
                {
                    emoji: 'stamp:v1:e11d48:fef3c7:それな',
                    count: 1,
                    user_ids: [4],
                    user_names: ['Test User'],
                },
            ],
        };

        const { container } = render(MessageItem, {
            props: { message, currentUserId: 4, onSetReaction },
        });

        const reaction = screen.getByRole('button', {
            name: 'ハンコ「それな」リアクション 1件、自分が追加済み',
        });
        expect(reaction.getAttribute('title')).toBe(
            'Test Userがハンコ「それな」でリアクション',
        );
        const stamp = container.querySelector('[data-stamp-text="それな"]');

        expect(stamp?.getAttribute('data-stamp-text-color')).toBe('#e11d48');
        expect(stamp?.getAttribute('data-stamp-background-color')).toBe(
            '#fef3c7',
        );
        expect(stamp?.classList.contains('size-[22px]')).toBe(true);

        await fireEvent.click(reaction);
        expect(onSetReaction).toHaveBeenCalledWith(
            'stamp:v1:e11d48:fef3c7:それな',
            false,
        );
    });

    it('adds a reaction from the shared emoji picker', async () => {
        const onSetReaction = vi.fn().mockResolvedValue(undefined);

        render(MessageItem, {
            props: {
                message: baseMessage,
                currentUserId: 4,
                onSetReaction,
            },
        });

        const pickerTrigger = screen.getByRole('button', {
            name: 'リアクションを追加',
        });

        expect(pickerTrigger.classList.contains('size-7')).toBe(true);
        await fireEvent.click(pickerTrigger);
        await fireEvent.click(
            await screen.findByRole('button', {
                name: '👍をリアクションに追加',
            }),
        );

        expect(onSetReaction).toHaveBeenCalledWith('👍', true);
    });
});

describe('MessageItem attachments', () => {
    it('opens an image in the centered file preview dialog', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => undefined)),
        );
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'image',
            created_at: '2026-08-01T12:00:00Z',
            user: { id: 4, name: 'Test User', email: 'test@example.com' },
            attachments: [
                {
                    id: 5,
                    server_id: 2,
                    path: 'uploads/image.png',
                    original_name: 'image.png',
                    mime_type: 'image/png',
                    size: 100,
                    preview_status: null,
                    stream_url: '/servers/2/files/5/stream',
                    download_url: '/servers/2/files/5/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });
        await fireEvent.click(
            screen.getByRole('button', { name: 'image.pngをプレビュー' }),
        );

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText('image.png')).toBeTruthy();
    });

    it('shows a PDF thumbnail and closes its preview with Escape', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => undefined)),
        );
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'pdf',
            created_at: '2026-08-01T12:00:00Z',
            attachments: [
                {
                    id: 6,
                    server_id: 2,
                    path: 'uploads/report.pdf',
                    original_name: 'report.pdf',
                    mime_type: 'application/pdf',
                    size: 200,
                    preview_status: 'ready',
                    thumbnail_url: '/servers/2/files/6/thumbnail',
                    stream_url: '/servers/2/files/6/stream',
                    download_url: '/servers/2/files/6/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });

        const attachmentList = document.querySelector('[data-attachment-list]');
        expect(attachmentList?.classList.contains('items-start')).toBe(true);
        expect(
            screen.getByRole('img', { name: 'report.pdf' }).getAttribute('src'),
        ).toBe('/servers/2/files/6/thumbnail');
        expect(screen.getByText('PDF')).toBeTruthy();
        const fileBadge = screen
            .getByText('PDF')
            .closest('[data-slot="badge"]');
        expect(fileBadge?.classList.contains('text-xs')).toBe(true);
        expect(fileBadge?.classList.contains('py-1')).toBe(true);
        expect(
            document.querySelector('[data-file-type-icon="pdf"]'),
        ).toBeTruthy();
        expect(screen.getByText('プレビュー')).toBeTruthy();
        expect(document.querySelector('[data-preview-hint]')).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'report.pdfをプレビュー' }),
        );
        expect(screen.getByRole('dialog')).toBeTruthy();

        await fireEvent.keyDown(window, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    });

    it('opens Office files with the OnlyOffice viewer', async () => {
        const fetchMock = vi.fn(() => new Promise(() => undefined));
        vi.stubGlobal('fetch', fetchMock);
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'office',
            created_at: '2026-08-01T12:00:00Z',
            attachments: [
                {
                    id: 7,
                    server_id: 2,
                    path: 'uploads/report.docx',
                    original_name: 'report.docx',
                    mime_type:
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    size: 300,
                    preview_status: 'ready',
                    thumbnail_url: '/servers/2/files/7/thumbnail',
                    stream_url: '/servers/2/files/7/stream',
                    download_url: '/servers/2/files/7/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });
        await fireEvent.click(
            screen.getByRole('button', { name: 'report.docxをプレビュー' }),
        );

        expect(screen.getByText('DOCX')).toBeTruthy();
        expect(screen.getByRole('dialog')).toBeTruthy();
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/servers/2/files/7/onlyoffice/config',
                expect.objectContaining({ credentials: 'same-origin' }),
            ),
        );
    });

    it('uses a spreadsheet icon for Excel attachments', () => {
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'excel',
            created_at: '2026-08-01T12:00:00Z',
            attachments: [
                {
                    id: 8,
                    server_id: 2,
                    path: 'uploads/report.xlsx',
                    original_name: 'report.xlsx',
                    mime_type:
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    size: 400,
                    preview_status: 'ready',
                    thumbnail_url: '/servers/2/files/8/thumbnail',
                    stream_url: '/servers/2/files/8/stream',
                    download_url: '/servers/2/files/8/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });

        expect(screen.getByText('XLSX')).toBeTruthy();
        expect(
            document.querySelector('[data-file-type-icon="spreadsheet"]'),
        ).toBeTruthy();
    });
});

import type * as InertiaSvelte from '@inertiajs/svelte';
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    ChannelResource,
    MessageResource,
    ServerResource,
    UserResource,
} from '@/types';
import Chat from './Chat.svelte';

const echo = vi.hoisted(() => ({
    listen: vi.fn(),
    stopListening: vi.fn(),
    leaveChannel: vi.fn(),
}));

const inertia = vi.hoisted(() => ({
    visit: vi.fn(),
    props: {
        auth: {
            user: { id: 1 },
            servers: [],
        },
    },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaSvelte>();

    return {
        ...actual,
        router: { ...actual.router, visit: inertia.visit },
        usePage: () => inertia,
    };
});

vi.mock('@/lib/echo', () => ({
    getEcho: () => ({
        private: () => echo,
        leaveChannel: echo.leaveChannel,
    }),
}));

const channel: ChannelResource = {
    id: 2,
    server_id: 1,
    name: 'plan',
    description: null,
    starts_on: '2026-08-01',
    ends_on: '2026-08-07',
    created_by: 1,
};

const server: ServerResource = {
    id: 1,
    name: 'Test Server',
    description: null,
    starts_on: null,
    ends_on: null,
    created_by: 1,
    channels: [channel],
};

const message: MessageResource = {
    id: 10,
    server_id: 1,
    channel_id: 2,
    user_id: 1,
    parent_id: null,
    body: 'こんにちは',
    created_at: '2026-08-01T12:00:00Z',
    user: { id: 1, name: 'Test User', email: 'test@example.com' },
};

function renderChat(
    initialMessages: MessageResource[] = [],
    members: UserResource[] = [],
) {
    return render(Chat, {
        props: {
            server,
            channel,
            initialMessages,
            members,
        },
    });
}

function messagePosts(fetchMock: ReturnType<typeof vi.fn>) {
    return fetchMock.mock.calls.filter(
        ([url, init]) =>
            String(url).endsWith('/messages') && init?.method === 'POST',
    );
}

function fileUploadPosts(fetchMock: ReturnType<typeof vi.fn>) {
    return fetchMock.mock.calls.filter(
        ([url, init]) =>
            String(url).endsWith('/files') && init?.method === 'POST',
    );
}

function jsonResponse(payload: unknown, status = 200): Response {
    return new Response(JSON.stringify(payload), { status });
}

async function focusComposer(): Promise<HTMLTextAreaElement> {
    const compactComposer = screen.getByPlaceholderText('メッセージを入力');
    await fireEvent.focus(compactComposer);

    return screen.getByPlaceholderText(
        'メッセージを入力',
    ) as HTMLTextAreaElement;
}

afterEach(cleanup);

describe('Chat composer', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
        vi.stubGlobal(
            'requestAnimationFrame',
            (callback: FrameRequestCallback) =>
                window.setTimeout(() => callback(0), 0),
        );
        Element.prototype.scrollIntoView = vi.fn();
        echo.listen.mockReturnValue(echo);
    });

    it('shows the channel label and date-only period', () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(jsonResponse({ todos: [] }))),
        );

        renderChat();

        expect(screen.getByText('チャンネル')).toBeTruthy();
        expect(screen.getByText('2026-08-01 〜 2026-08-07')).toBeTruthy();
    });

    it('does not send for plain or IME composition Enter', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementation(() =>
                Promise.resolve(jsonResponse({ todos: [] })),
            );
        vi.stubGlobal('fetch', fetchMock);
        renderChat();

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: 'こんにちは' } });
        await fireEvent.keyDown(composer, { key: 'Enter' });
        await fireEvent.keyDown(composer, {
            key: 'Enter',
            metaKey: true,
            isComposing: true,
        });

        expect(messagePosts(fetchMock)).toHaveLength(0);
    });

    it.each([
        ['Cmd+Enter', { metaKey: true }],
        ['Ctrl+Enter', { ctrlKey: true }],
    ])('sends with %s', async (_, modifier) => {
        const fetchMock = vi.fn().mockImplementation((url: string) => {
            const payload = String(url).endsWith('/messages')
                ? { message }
                : { todos: [] };

            return Promise.resolve(
                new Response(JSON.stringify(payload), { status: 200 }),
            );
        });
        vi.stubGlobal('fetch', fetchMock);
        renderChat();

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: 'こんにちは' } });
        await fireEvent.keyDown(composer, { key: 'Enter', ...modifier });

        await waitFor(() => expect(messagePosts(fetchMock)).toHaveLength(1));
    });

    it('ignores repeated send shortcuts while a request is pending', async () => {
        let resolveMessage!: (response: Response) => void;
        const pendingMessage = new Promise<Response>((resolve) => {
            resolveMessage = resolve;
        });
        const fetchMock = vi.fn().mockImplementation((url: string) =>
            String(url).endsWith('/messages')
                ? pendingMessage
                : Promise.resolve(
                      new Response(JSON.stringify({ todos: [] }), {
                          status: 200,
                      }),
                  ),
        );
        vi.stubGlobal('fetch', fetchMock);
        renderChat();

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: 'こんにちは' } });
        await fireEvent.keyDown(composer, { key: 'Enter', metaKey: true });
        await fireEvent.keyDown(composer, { key: 'Enter', metaKey: true });

        expect(messagePosts(fetchMock)).toHaveLength(1);

        resolveMessage(
            new Response(JSON.stringify({ message }), { status: 201 }),
        );
    });

    it('adds a message reaction and updates its grouped count', async () => {
        const reactedMessage: MessageResource = {
            ...message,
            reactions: [
                {
                    emoji: '👍',
                    count: 1,
                    user_ids: [2],
                    user_names: ['Other User'],
                },
            ],
        };
        const updatedMessage: MessageResource = {
            ...reactedMessage,
            reactions: [
                {
                    emoji: '👍',
                    count: 2,
                    user_ids: [2, 1],
                    user_names: ['Other User', 'Test User'],
                },
            ],
        };
        const fetchMock = vi
            .fn()
            .mockImplementation((url: string, init?: RequestInit) => {
                if (
                    String(url).endsWith('/messages/10/reactions') &&
                    init?.method === 'PUT'
                ) {
                    return Promise.resolve(
                        jsonResponse({ message: updatedMessage }),
                    );
                }

                return Promise.resolve(jsonResponse({ todos: [] }));
            });
        vi.stubGlobal('fetch', fetchMock);
        renderChat([reactedMessage]);

        await fireEvent.click(
            screen.getByRole('button', { name: '👍リアクション 1件' }),
        );

        await waitFor(() =>
            expect(
                screen.getByRole('button', {
                    name: '👍リアクション 2件、自分が追加済み',
                }),
            ).toBeTruthy(),
        );

        const reactionRequest = fetchMock.mock.calls.find(
            ([url, init]) =>
                String(url).endsWith('/messages/10/reactions') &&
                init?.method === 'PUT',
        );
        expect(reactionRequest).toBeTruthy();
        expect(JSON.parse(reactionRequest![1].body as string)).toEqual({
            emoji: '👍',
        });
    });

    it('uploads and previews an image pasted from the clipboard before sending', async () => {
        const screenshot = new File(['screenshot'], 'Screenshot.png', {
            type: 'image/png',
        });
        const uploadedFile = {
            id: 42,
            server_id: server.id,
            path: 'uploads/1/2026/08/04/screenshot.png',
            original_name: screenshot.name,
            mime_type: screenshot.type,
            size: screenshot.size,
            preview_status: null,
            stream_url: '/servers/1/files/42/stream',
            download_url: '/servers/1/files/42/download',
            thumbnail_url: null,
        };
        const fetchMock = vi
            .fn()
            .mockImplementation((url: string, init?: RequestInit) => {
                if (String(url).endsWith('/files') && init?.method === 'POST') {
                    return Promise.resolve(
                        jsonResponse({ files: [uploadedFile] }, 201),
                    );
                }

                if (
                    String(url).endsWith('/messages') &&
                    init?.method === 'POST'
                ) {
                    return Promise.resolve(
                        jsonResponse(
                            {
                                message: {
                                    ...message,
                                    body: '',
                                    attachments: [uploadedFile],
                                },
                            },
                            201,
                        ),
                    );
                }

                return Promise.resolve(jsonResponse({ todos: [] }));
            });
        vi.stubGlobal('fetch', fetchMock);
        renderChat();

        const composer = await focusComposer();
        const pasteEvent = new Event('paste', {
            bubbles: true,
            cancelable: true,
        });
        Object.defineProperty(pasteEvent, 'clipboardData', {
            value: {
                items: [
                    {
                        kind: 'file',
                        type: screenshot.type,
                        getAsFile: () => screenshot,
                    },
                ],
                files: [screenshot],
            },
        });

        expect(composer.dispatchEvent(pasteEvent)).toBe(false);

        const preview = await screen.findByRole('img', {
            name: screenshot.name,
        });
        expect(preview.getAttribute('src')).toBe(uploadedFile.stream_url);
        expect(fileUploadPosts(fetchMock)).toHaveLength(1);

        const uploadBody = fileUploadPosts(fetchMock)[0][1].body as FormData;
        expect(uploadBody.getAll('files[]')).toEqual([screenshot]);

        await fireEvent.click(screen.getByTitle('送信'));
        await waitFor(() => expect(messagePosts(fetchMock)).toHaveLength(1));

        const messageBody = JSON.parse(
            messagePosts(fetchMock)[0][1].body as string,
        );
        expect(messageBody.body).toBe('');
        expect(messageBody.attachments).toEqual([
            {
                path: uploadedFile.path,
                original_name: uploadedFile.original_name,
                mime_type: uploadedFile.mime_type,
                size: uploadedFile.size,
            },
        ]);
        await waitFor(() =>
            expect(
                screen.queryByLabelText('送信予定の添付ファイル'),
            ).toBeNull(),
        );
    });

    it('leaves ordinary clipboard text to the textarea', async () => {
        const fetchMock = vi.fn(() =>
            Promise.resolve(jsonResponse({ todos: [] })),
        );
        vi.stubGlobal('fetch', fetchMock);
        renderChat();

        const composer = await focusComposer();
        const pasteEvent = new Event('paste', {
            bubbles: true,
            cancelable: true,
        });
        Object.defineProperty(pasteEvent, 'clipboardData', {
            value: {
                items: [
                    {
                        kind: 'string',
                        type: 'text/plain',
                        getAsFile: () => null,
                    },
                ],
                files: [],
            },
        });

        expect(composer.dispatchEvent(pasteEvent)).toBe(true);
        expect(fileUploadPosts(fetchMock)).toHaveLength(0);
    });

    it('loads and displays only the selected thread replies', async () => {
        const reply: MessageResource = {
            ...message,
            id: 11,
            parent_id: message.id,
            body: 'スレッドの返信',
        };
        const otherMessage: MessageResource = {
            ...message,
            id: 12,
            body: '別の通常メッセージ',
        };
        const fetchMock = vi.fn().mockImplementation((url: string) => {
            const payload = String(url).includes(`parent_id=${message.id}`)
                ? { messages: [reply] }
                : { todos: [] };

            return Promise.resolve(
                new Response(JSON.stringify(payload), { status: 200 }),
            );
        });
        vi.stubGlobal('fetch', fetchMock);
        renderChat([{ ...message, reply_count: 1 }, otherMessage]);

        expect(
            screen.getByRole('button', {
                name: 'スレッド「こんにちは」を開く',
            }),
        ).toBeTruthy();
        expect(
            screen.queryByRole('button', {
                name: 'スレッド「別の通常メッセージ」を開く',
            }),
        ).toBeNull();
        await fireEvent.click(
            screen.getByRole('button', {
                name: 'スレッド「こんにちは」を開く',
            }),
        );

        await waitFor(() =>
            expect(screen.getByText('スレッドの返信')).toBeTruthy(),
        );
        expect(screen.queryByText('別の通常メッセージ')).toBeNull();
        expect(screen.queryByRole('button', { name: 'スレッド 1' })).toBeNull();
    });

    it('edits and deletes an owned message', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementation((url: string, init?: RequestInit) => {
                if (init?.method === 'PATCH') {
                    return Promise.resolve(
                        new Response(
                            JSON.stringify({
                                message: { ...message, body: '編集後' },
                            }),
                            { status: 200 },
                        ),
                    );
                }

                if (init?.method === 'DELETE') {
                    return Promise.resolve(new Response(null, { status: 204 }));
                }

                return Promise.resolve(
                    new Response(JSON.stringify({ todos: [] }), {
                        status: 200,
                    }),
                );
            });
        vi.stubGlobal('fetch', fetchMock);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        renderChat([message]);

        await fireEvent.click(
            screen.getByRole('button', { name: 'メッセージを編集' }),
        );
        const editor = screen.getByDisplayValue('こんにちは');
        await fireEvent.input(editor, { target: { value: '編集後' } });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(screen.getByText('編集後')).toBeTruthy());
        await fireEvent.click(
            screen.getByRole('button', { name: 'メッセージを削除' }),
        );
        await waitFor(() => expect(screen.queryByText('編集後')).toBeNull());

        expect(
            fetchMock.mock.calls.some(([, init]) => init?.method === 'PATCH'),
        ).toBe(true);
        expect(
            fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE'),
        ).toBe(true);
    });

    it('applies composer formatting and toggles the toolbar', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn((url: string) =>
                Promise.resolve(
                    jsonResponse(
                        String(url).endsWith('/notifications')
                            ? { items: [], unread: 0 }
                            : { todos: [] },
                    ),
                ),
            ),
        );
        renderChat();

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: '選択文字' } });
        composer.setSelectionRange(0, 4);
        await fireEvent.click(screen.getByTitle('太字'));

        await waitFor(() => expect(composer.value).toBe('**選択文字**'));

        await fireEvent.click(
            screen.getByRole('button', { name: '書式設定を切り替え' }),
        );
        expect(screen.queryByLabelText('書式設定')).toBeNull();

        composer.setSelectionRange(
            composer.value.length,
            composer.value.length,
        );
        await fireEvent.click(screen.getByTitle('メンションを挿入'));
        await waitFor(() => expect(composer.value).toBe('**選択文字**@'));
    });

    it('keeps Safari from blurring the composer when its buttons are pressed', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(jsonResponse({ todos: [] }))),
        );
        renderChat();

        const composer = await focusComposer();
        composer.focus();
        const controls = [
            screen.getByTitle('太字'),
            screen.getByRole('button', { name: '書式設定を切り替え' }),
            screen.getByRole('button', { name: '絵文字を選ぶ' }),
            screen.getByTitle('メンションを挿入'),
        ];

        for (const control of controls) {
            const mouseDown = new MouseEvent('mousedown', {
                bubbles: true,
                button: 0,
                cancelable: true,
            });

            expect(control.dispatchEvent(mouseDown)).toBe(false);
            expect(mouseDown.defaultPrevented).toBe(true);
        }

        expect(document.activeElement).toBe(composer);
        expect(composer.rows).toBe(3);
    });

    it('filters candidates, replaces the display name, and serializes its anchor', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementation((url: string) =>
                Promise.resolve(
                    jsonResponse(
                        String(url).endsWith('/messages')
                            ? { message }
                            : { todos: [] },
                    ),
                ),
            );
        vi.stubGlobal('fetch', fetchMock);
        renderChat(
            [],
            [
                { id: 2, name: 'Alice', email: 'alice@example.com' },
                { id: 3, name: 'Bob', email: 'bob@example.com' },
            ],
        );

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: '@ali' } });
        composer.setSelectionRange(4, 4);
        await fireEvent.input(composer);

        expect(
            screen.getByRole('listbox', { name: 'メンション候補' }),
        ).toBeTruthy();
        expect(screen.getByRole('option', { name: /@Alice/ })).toBeTruthy();
        expect(screen.queryByRole('option', { name: /@Bob/ })).toBeNull();

        await fireEvent.keyDown(composer, { key: 'Enter' });
        await waitFor(() => expect(composer.value).toBe('@Alice '));
        await fireEvent.keyDown(composer, { key: 'Enter', metaKey: true });

        await waitFor(() => expect(messagePosts(fetchMock)).toHaveLength(1));
        expect(JSON.parse(messagePosts(fetchMock)[0][1].body).body).toBe(
            '<@2>',
        );
    });

    it('expands the composer for multiline content', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(jsonResponse({ todos: [] }))),
        );
        renderChat();

        const compactComposer = screen.getByPlaceholderText(
            'メッセージを入力',
        ) as HTMLTextAreaElement;

        expect(compactComposer.rows).toBe(1);
        expect(screen.queryByLabelText('書式設定')).toBeNull();

        let composer = await focusComposer();
        const chatColumn = screen.getByLabelText('チャット');
        const todoPanel = screen
            .getByText('タスクがありません')
            .closest('aside');

        expect(chatColumn.contains(composer)).toBe(true);
        expect(chatColumn.contains(todoPanel)).toBe(false);
        expect(chatColumn.parentElement).toBe(todoPanel?.parentElement);
        expect(composer.rows).toBe(3);
        expect(screen.getByLabelText('書式設定')).toBeTruthy();

        await fireEvent.focusOut(composer, { relatedTarget: null });
        await waitFor(() => {
            expect(
                (
                    screen.getByPlaceholderText(
                        'メッセージを入力',
                    ) as HTMLTextAreaElement
                ).rows,
            ).toBe(1);
        });

        composer = await focusComposer();
        Object.defineProperty(composer, 'scrollHeight', {
            configurable: true,
            value: 120,
        });
        await fireEvent.input(composer, {
            target: { value: '1行目\n2行目\n3行目' },
        });

        expect(composer.style.height).toBe('120px');
        expect(composer.style.overflowY).toBe('hidden');

        await fireEvent.focusOut(composer, { relatedTarget: null });
        await waitFor(() => {
            const collapsedComposer = screen.getByPlaceholderText(
                'メッセージを入力',
            ) as HTMLTextAreaElement;

            expect(collapsedComposer.rows).toBe(1);
            expect(collapsedComposer.value).toBe('1行目\n2行目\n3行目');
        });
    });

    it('keeps keyboard focus when the compact composer expands', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(jsonResponse({ todos: [] }))),
        );
        renderChat();

        const compactComposer = screen.getByPlaceholderText(
            'メッセージを入力',
        ) as HTMLTextAreaElement;
        compactComposer.focus();

        await waitFor(() => {
            const expandedComposer = screen.getByPlaceholderText(
                'メッセージを入力',
            ) as HTMLTextAreaElement;

            expect(expandedComposer.rows).toBe(3);
            expect(expandedComposer).toBe(compactComposer);
            expect(document.activeElement).toBe(expandedComposer);
        });

        const expandedComposer = document.activeElement as HTMLTextAreaElement;
        await fireEvent.input(expandedComposer, {
            target: { value: 'キーボード入力' },
        });

        expect(expandedComposer.value).toBe('キーボード入力');
    });

    it('inserts a picked emoji at the cursor without collapsing the composer', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(jsonResponse({ todos: [] }))),
        );
        renderChat();

        const composer = await focusComposer();
        await fireEvent.input(composer, { target: { value: '前後' } });
        composer.setSelectionRange(1, 1);

        await fireEvent.click(
            screen.getByRole('button', { name: '絵文字を選ぶ' }),
        );
        expect(await screen.findByLabelText('クイック絵文字')).toBeTruthy();
        expect(composer.rows).toBe(3);

        await fireEvent.click(screen.getByRole('button', { name: '👍を挿入' }));

        await waitFor(() => {
            expect(composer.value).toBe('前👍後');
            expect(document.activeElement).toBe(composer);
            expect(screen.queryByLabelText('クイック絵文字')).toBeNull();
        });
    });
});

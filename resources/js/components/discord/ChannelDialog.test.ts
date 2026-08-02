import {
    cleanup,
    render,
    screen,
    fireEvent,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ChannelResource } from '@/types';
import ChannelDialog from './ChannelDialog.svelte';

const server = { id: 1, name: 'Test Server' } as never;

afterEach(cleanup);

function stubFetch(status: number, body: unknown): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn().mockResolvedValue(
        new Response(JSON.stringify(body), {
            status,
            headers: { 'Content-Type': 'application/json' },
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

describe('ChannelDialog', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
        vi.stubGlobal('location', { href: '' });
    });

    it('does not create a channel when Enter is pressed', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        render(ChannelDialog, { props: { server, onClose: vi.fn() } });

        const nameInput = screen.getByPlaceholderText('例: プロジェクト進行');
        await fireEvent.input(nameInput, { target: { value: 'new-channel' } });
        await fireEvent.keyDown(nameInput, { key: 'Enter' });

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it.each([
        ['later project start', '2026-08-19', '2026-08-19'],
        ['past project start', '2026-08-01', '2026-08-02'],
    ])(
        'defaults channel start date to %s',
        (_, projectStart, expectedStart) => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date(2026, 7, 2, 10, 0));

            try {
                render(ChannelDialog, {
                    props: {
                        server: {
                            id: 1,
                            name: 'Test Server',
                            starts_on: projectStart,
                            ends_on: '2026-08-31',
                        } as never,
                        onClose: vi.fn(),
                    },
                });

                expect(
                    (screen.getByLabelText('開始日') as HTMLInputElement).value,
                ).toBe(expectedStart);
            } finally {
                vi.useRealTimers();
            }
        },
    );

    it('updates an existing channel from its settings dialog', async () => {
        const channel: ChannelResource = {
            id: 2,
            server_id: 1,
            name: 'old-name',
            description: 'old description',
            starts_on: '2026-08-02',
            ends_on: '2026-08-06',
            created_by: 1,
        };
        const updated = {
            ...channel,
            name: 'new-name',
            description: 'new description',
            starts_on: '2026-08-03',
            ends_on: '2026-08-07',
        };
        const fetchMock = stubFetch(200, { channel: updated });
        const onUpdated = vi.fn();
        const onClose = vi.fn();

        render(ChannelDialog, {
            props: { server, channel, onUpdated, onClose },
        });

        await fireEvent.input(screen.getByLabelText('チャンネル名'), {
            target: { value: 'new-name' },
        });
        await fireEvent.input(screen.getByLabelText('説明（任意）'), {
            target: { value: 'new description' },
        });
        await fireEvent.input(screen.getByLabelText('開始日'), {
            target: { value: '2026-08-03' },
        });
        await fireEvent.input(screen.getByLabelText('終了期限'), {
            target: { value: '2026-08-07' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1/channels/2');
        expect(init.method).toBe('PATCH');
        expect(JSON.parse(init.body)).toEqual({
            name: 'new-name',
            description: 'new description',
            starts_on: '2026-08-03',
            ends_on: '2026-08-07',
        });
        expect(onClose).toHaveBeenCalledOnce();
    });

    it('posts JSON and navigates only after a successful 201 with channel.id', async () => {
        const fetchMock = stubFetch(201, {
            channel: { id: 42, name: 'new-channel' },
        });

        render(ChannelDialog, { props: { server, onClose: vi.fn() } });

        await fireEvent.input(
            screen.getByPlaceholderText('例: プロジェクト進行'),
            {
                target: { value: 'new-channel' },
            },
        );
        await fireEvent.click(screen.getByRole('button', { name: /作成/ }));

        await waitFor(() => {
            expect(window.location.href).toBe('/servers/1/channels/42');
        });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1/channels');
        const headers = new Headers(init.headers);
        expect(headers.get('X-XSRF-TOKEN')).toBe('token-123');
        expect(headers.get('Accept')).toBe('application/json');
        expect(init.method).toBe('POST');
        expect(JSON.parse(init.body)).toMatchObject({ name: 'new-channel' });
    });

    it('does not navigate and shows the server error when the request fails', async () => {
        stubFetch(422, {
            message: 'The given data was invalid.',
            errors: { name: ['name は必須です。'] },
        });

        render(ChannelDialog, { props: { server, onClose: vi.fn() } });

        await fireEvent.input(
            screen.getByPlaceholderText('例: プロジェクト進行'),
            {
                target: { value: 'x' },
            },
        );
        await fireEvent.click(screen.getByRole('button', { name: /作成/ }));

        await waitFor(() => {
            expect(screen.getByRole('alert').textContent).toBe(
                'name は必須です。',
            );
        });
        expect(window.location.href).toBe('');
    });

    it('keeps the entered name and shows an alert on 500', async () => {
        stubFetch(500, { message: 'Server Error' });

        render(ChannelDialog, { props: { server, onClose: vi.fn() } });

        const input = screen.getByPlaceholderText('例: プロジェクト進行');
        await fireEvent.input(input, { target: { value: 'kept-name' } });
        await fireEvent.click(screen.getByRole('button', { name: /作成/ }));

        await waitFor(() => {
            expect(screen.getByRole('alert').textContent).toBe('Server Error');
        });
        expect((input as HTMLInputElement).value).toBe('kept-name');
    });
});

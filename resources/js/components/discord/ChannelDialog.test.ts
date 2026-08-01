import {
    cleanup,
    render,
    screen,
    fireEvent,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

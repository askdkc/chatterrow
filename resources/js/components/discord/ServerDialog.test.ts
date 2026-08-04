import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ServerResource } from '@/types';
import ServerDialog from './ServerDialog.svelte';

afterEach(cleanup);

describe('ServerDialog', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
        vi.stubGlobal('location', { href: '' });
    });

    it('does not create a project when Enter is pressed', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        render(ServerDialog, { props: { onClose: vi.fn() } });

        const nameInput = screen.getByPlaceholderText('例: プロジェクトA');
        await fireEvent.input(nameInput, { target: { value: 'new-project' } });
        await fireEvent.keyDown(nameInput, { key: 'Enter' });

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it.each([
        ['Cmd+Enter', { metaKey: true }],
        ['Ctrl+Enter', { ctrlKey: true }],
    ])('creates a project with %s', async (_, modifier) => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: { id: 42 } }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        render(ServerDialog, { props: { onClose: vi.fn() } });

        const nameInput = screen.getByPlaceholderText('例: プロジェクトA');
        await fireEvent.input(nameInput, { target: { value: 'new-project' } });
        await fireEvent.keyDown(nameInput, { key: 'Enter', ...modifier });

        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    });

    it('updates an existing project from its settings dialog', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Before',
            description: 'Old description',
            starts_on: '2026-08-19',
            ends_on: '2026-08-31',
            created_by: 1,
        };
        const updated = {
            ...server,
            name: 'After',
            description: 'New description',
            starts_on: '2026-08-20',
            ends_on: '2026-09-01',
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: updated }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onUpdated = vi.fn();
        const onClose = vi.fn();

        render(ServerDialog, {
            props: { server, onUpdated, onClose },
        });

        await fireEvent.input(screen.getByLabelText('プロジェクト名'), {
            target: { value: 'After' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1');
        expect(init.method).toBe('POST');
        const form = init.body as FormData;
        expect(form.get('_method')).toBe('PATCH');
        expect(form.get('name')).toBe('After');
        expect(onClose).toHaveBeenCalledOnce();
    });

    it('creates a project with a custom icon', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: { id: 42 } }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:project-icon'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        render(ServerDialog, { props: { onClose: vi.fn() } });

        await fireEvent.input(screen.getByLabelText('プロジェクト名'), {
            target: { value: 'Icon project' },
        });
        const icon = new File(['icon'], 'project.png', {
            type: 'image/png',
        });
        await fireEvent.change(screen.getByLabelText('アイコン画像'), {
            target: { files: [icon] },
        });

        expect(
            document.querySelector('img[src="blob:project-icon"]'),
        ).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: '作成' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

        const [, init] = fetchMock.mock.calls[0];
        const form = init.body as FormData;
        expect(init.method).toBe('POST');
        expect(form.get('name')).toBe('Icon project');
        expect(form.get('icon')).toBe(icon);
    });
});

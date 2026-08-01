import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TodoDialog from './TodoDialog.svelte';

afterEach(cleanup);

describe('TodoDialog', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
    });

    it('does not create a task when Enter is pressed', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        render(TodoDialog, {
            props: {
                serverId: 1,
                channelId: 2,
                onCreated: vi.fn(),
                onClose: vi.fn(),
            },
        });

        const title = screen.getByLabelText('タスク名');
        await fireEvent.input(title, { target: { value: 'new task' } });
        await fireEvent.keyDown(title, { key: 'Enter' });

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('creates a task with all dialog fields', async () => {
        const todo = {
            id: 3,
            title: 'new task',
            starts_at: '2026-08-02T09:00:00.000000Z',
            due_at: '2026-08-02T17:30:00.000000Z',
            priority: 'high',
            details: 'memo',
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ todo }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onCreated = vi.fn();
        const onClose = vi.fn();
        render(TodoDialog, {
            props: { serverId: 1, channelId: 2, onCreated, onClose },
        });

        await fireEvent.input(screen.getByLabelText('タスク名'), {
            target: { value: 'new task' },
        });
        await fireEvent.input(screen.getByLabelText('開始日'), {
            target: { value: '2026-08-02' },
        });
        await fireEvent.input(screen.getByLabelText('開始時刻'), {
            target: { value: '09:00' },
        });
        await fireEvent.input(screen.getByLabelText('終了日'), {
            target: { value: '2026-08-02' },
        });
        await fireEvent.input(screen.getByLabelText('終了時刻'), {
            target: { value: '17:30' },
        });
        await fireEvent.change(screen.getByLabelText('プライオリティ'), {
            target: { value: 'high' },
        });
        await fireEvent.input(screen.getByLabelText('メモ'), {
            target: { value: 'memo' },
        });
        await fireEvent.click(screen.getByRole('button', { name: /^作成$/ }));

        await waitFor(() => expect(onCreated).toHaveBeenCalledWith(todo));
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1/channels/2/todos');
        expect(JSON.parse(init.body)).toEqual({
            title: 'new task',
            starts_at: '2026-08-02T09:00',
            due_at: '2026-08-02T17:30',
            priority: 'high',
            details: 'memo',
        });
        expect(onClose).toHaveBeenCalledOnce();
    });

    it.each([
        ['Cmd+Enter', { metaKey: true }],
        ['Ctrl+Enter', { ctrlKey: true }],
    ])('creates a task with %s', async (_, modifier) => {
        const todo = { id: 3, title: 'shortcut task' };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ todo }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onCreated = vi.fn();
        render(TodoDialog, {
            props: {
                serverId: 1,
                channelId: 2,
                onCreated,
                onClose: vi.fn(),
            },
        });

        const title = screen.getByLabelText('タスク名');
        await fireEvent.input(title, { target: { value: 'shortcut task' } });
        await fireEvent.keyDown(title, { key: 'Enter', ...modifier });

        await waitFor(() => expect(onCreated).toHaveBeenCalledWith(todo));
        expect(fetchMock).toHaveBeenCalledOnce();
    });
});

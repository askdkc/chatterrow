import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { TodoResource } from '@/types';
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

    it('defaults to the next half-hour and ends 30 minutes later', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 7, 6, 9, 41));

        try {
            render(TodoDialog, {
                props: {
                    serverId: 1,
                    channelId: 2,
                    onCreated: vi.fn(),
                    onClose: vi.fn(),
                },
            });

            expect(
                (screen.getByLabelText('開始日') as HTMLInputElement).value,
            ).toBe('2026-08-06');
            expect(
                (screen.getByLabelText('開始時刻') as HTMLInputElement).value,
            ).toBe('10:00');
            expect(
                (screen.getByLabelText('終了日') as HTMLInputElement).value,
            ).toBe('2026-08-06');
            expect(
                (screen.getByLabelText('終了時刻') as HTMLInputElement).value,
            ).toBe('10:30');
        } finally {
            vi.useRealTimers();
        }
    });

    it('uses the channel start date when it is later than today', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 7, 2, 9, 41));

        try {
            render(TodoDialog, {
                props: {
                    serverId: 1,
                    channelId: 2,
                    channelStartsOn: '2026-08-06',
                    onCreated: vi.fn(),
                    onClose: vi.fn(),
                },
            });

            expect(
                (screen.getByLabelText('開始日') as HTMLInputElement).value,
            ).toBe('2026-08-06');
            expect(
                (screen.getByLabelText('終了日') as HTMLInputElement).value,
            ).toBe('2026-08-06');
        } finally {
            vi.useRealTimers();
        }
    });

    it('sets the end time 30 minutes after the start time', async () => {
        render(TodoDialog, {
            props: {
                serverId: 1,
                channelId: 2,
                onCreated: vi.fn(),
                onClose: vi.fn(),
            },
        });

        const startTime = screen.getByLabelText('開始時刻') as HTMLInputElement;
        const dueTime = screen.getByLabelText('終了時刻') as HTMLInputElement;

        expect(startTime.disabled).toBe(false);
        expect(dueTime.disabled).toBe(false);

        await fireEvent.input(startTime, {
            target: { value: '09:00' },
        });
        await fireEvent.input(screen.getByLabelText('開始日'), {
            target: { value: '2026-08-02' },
        });

        expect(dueTime.value).toBe('09:30');
        expect(
            (screen.getByLabelText('終了日') as HTMLInputElement).value,
        ).toBe('2026-08-02');

        await fireEvent.click(startTime);
        await fireEvent.click(screen.getByRole('option', { name: '09:30' }));

        expect(startTime.value).toBe('09:30');
        expect(dueTime.value).toBe('10:00');

        await fireEvent.input(dueTime, { target: { value: '11:00' } });
        await fireEvent.input(startTime, { target: { value: '12:00' } });

        expect(dueTime.value).toBe('11:00');
    });

    it('updates an existing task from the edit dialog', async () => {
        const todo = {
            id: 7,
            channel_id: 2,
            assignee_id: null,
            created_by: 1,
            title: 'old title',
            details: 'old details',
            starts_at: '2026-08-06T10:00:00.000000Z',
            due_at: '2026-08-06T10:30:00.000000Z',
            priority: 'normal',
            due_on: null,
            completed_at: null,
            completed_by: null,
            position: 0,
        } as TodoResource;
        const updated = { ...todo, title: 'new title', details: 'new details' };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ todo: updated }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onUpdated = vi.fn();
        const onClose = vi.fn();

        render(TodoDialog, {
            props: {
                serverId: 1,
                channelId: 2,
                todo,
                onUpdated,
                onClose,
            },
        });

        await fireEvent.input(screen.getByLabelText('タスク名'), {
            target: { value: 'new title' },
        });
        await fireEvent.input(screen.getByLabelText('メモ'), {
            target: { value: 'new details' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1/channels/2/todos/7');
        expect(init.method).toBe('PATCH');
        expect(JSON.parse(init.body)).toMatchObject({
            title: 'new title',
            details: 'new details',
        });
        expect(onClose).toHaveBeenCalledOnce();
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

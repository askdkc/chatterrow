import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import TodoPanel from './TodoPanel.svelte';

afterEach(cleanup);

describe('TodoPanel', () => {
    it('opens the full task dialog from the add button', async () => {
        render(TodoPanel, {
            props: {
                todos: [],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'タスクを追加' }),
        );

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByLabelText('タスク名')).toBeTruthy();
        expect(screen.getByLabelText('開始日')).toBeTruthy();
        expect(screen.getByLabelText('開始時刻')).toBeTruthy();
        expect(screen.getByLabelText('終了日')).toBeTruthy();
        expect(screen.getByLabelText('終了時刻')).toBeTruthy();
        expect(screen.getByLabelText('プライオリティ')).toBeTruthy();
        expect(screen.getByLabelText('メモ')).toBeTruthy();
    });

    it('does not render a new task as completed when completed_at is absent', () => {
        render(TodoPanel, {
            props: {
                todos: [
                    {
                        id: 1,
                        channel_id: 2,
                        assignee_id: null,
                        created_by: 1,
                        title: 'new task',
                        details: null,
                        starts_at: null,
                        due_at: null,
                        priority: 'normal',
                        due_on: null,
                        completed_by: null,
                        position: 0,
                    } as never,
                ],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        expect(
            screen.getByText('new task').classList.contains('line-through'),
        ).toBe(false);
    });
});

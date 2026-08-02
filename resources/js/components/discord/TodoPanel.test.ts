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

    it('collapses and expands the task panel', async () => {
        render(TodoPanel, {
            props: {
                todos: [],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'タスクを折りたたむ' }),
        );
        expect(
            screen.getByRole('button', { name: 'タスクを展開' }),
        ).toBeTruthy();

        await fireEvent.click(
            screen.getByRole('button', { name: 'タスクを展開' }),
        );
        expect(
            screen.getByRole('button', { name: 'タスクを折りたたむ' }),
        ).toBeTruthy();
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
        expect(
            screen.getByRole('button', {
                name: 'new taskの詳細を展開',
            }),
        ).toBeTruthy();
        expect(screen.queryByText('開始日')).toBeNull();
        expect(screen.queryByText('終了日')).toBeNull();
        expect(screen.queryByText('プライオリティ')).toBeNull();
        expect(screen.queryByText('担当者')).toBeNull();
    });

    it('starts collapsed and expands an individual task card', async () => {
        render(TodoPanel, {
            props: {
                todos: [
                    {
                        id: 1,
                        channel_id: 2,
                        assignee_id: null,
                        created_by: 1,
                        title: 'design task',
                        details: 'decide the visual direction',
                        starts_at: null,
                        due_at: null,
                        priority: 'normal',
                        due_on: null,
                        completed_at: null,
                        completed_by: null,
                        position: 0,
                    } as never,
                ],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        const expandButton = screen.getByRole('button', {
            name: 'design taskの詳細を展開',
        });
        expect(expandButton.getAttribute('aria-expanded')).toBe('false');
        expect(screen.queryByText('decide the visual direction')).toBeNull();
        expect(screen.queryByText('開始日')).toBeNull();

        await fireEvent.click(expandButton);

        const collapseButton = screen.getByRole('button', {
            name: 'design taskの詳細を折りたたむ',
        });
        expect(collapseButton.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByText('decide the visual direction')).toBeTruthy();
        expect(screen.getByText('開始日')).toBeTruthy();

        await fireEvent.click(collapseButton);

        expect(
            screen.getByRole('button', {
                name: 'design taskの詳細を展開',
            }),
        ).toBeTruthy();
        expect(screen.queryByText('decide the visual direction')).toBeNull();
        expect(screen.queryByText('開始日')).toBeNull();
    });

    it('expands and collapses all task cards from the panel header', async () => {
        render(TodoPanel, {
            props: {
                todos: [
                    {
                        id: 1,
                        channel_id: 2,
                        assignee_id: null,
                        created_by: 1,
                        title: 'first task',
                        details: 'first details',
                        starts_at: null,
                        due_at: null,
                        priority: 'normal',
                        due_on: null,
                        completed_at: null,
                        completed_by: null,
                        position: 0,
                    } as never,
                    {
                        id: 2,
                        channel_id: 2,
                        assignee_id: null,
                        created_by: 1,
                        title: 'second task',
                        details: 'second details',
                        starts_at: null,
                        due_at: null,
                        priority: 'normal',
                        due_on: null,
                        completed_at: null,
                        completed_by: null,
                        position: 1,
                    } as never,
                ],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        expect(screen.queryByText('first details')).toBeNull();
        expect(screen.queryByText('second details')).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'タスクをすべて展開',
            }),
        );

        expect(screen.getByText('first details')).toBeTruthy();
        expect(screen.getByText('second details')).toBeTruthy();

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'タスクをすべて折りたたむ',
            }),
        );

        expect(screen.queryByText('first details')).toBeNull();
        expect(screen.queryByText('second details')).toBeNull();
    });

    it('opens the edit dialog when a task card is clicked', async () => {
        render(TodoPanel, {
            props: {
                todos: [
                    {
                        id: 1,
                        channel_id: 2,
                        assignee_id: null,
                        created_by: 1,
                        title: 'editable task',
                        details: null,
                        starts_at: '2026-08-06T10:00:00.000000Z',
                        due_at: '2026-08-06T10:30:00.000000Z',
                        priority: 'normal',
                        due_on: null,
                        completed_at: null,
                        completed_by: null,
                        position: 0,
                    } as never,
                ],
                members: [],
                serverId: 1,
                channelId: 2,
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'editable taskを編集' }),
        );

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByRole('button', { name: '保存' })).toBeTruthy();
        expect(
            (screen.getByLabelText('タスク名') as HTMLInputElement).value,
        ).toBe('editable task');
    });
});

import type * as InertiaSvelte from '@inertiajs/svelte';
import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ChannelResource, GanttTask, ServerResource } from '@/types';
import Gantt from './Gantt.svelte';

const inertia = vi.hoisted(() => ({
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
        usePage: () => inertia,
    };
});

const server: ServerResource = {
    id: 1,
    name: 'Design Project',
    description: null,
    starts_on: '2026-08-01',
    ends_on: '2026-08-14',
    created_by: 1,
};

const channels: ChannelResource[] = [
    {
        id: 2,
        server_id: 1,
        name: 'Design',
        description: null,
        starts_on: '2026-08-03',
        ends_on: '2026-08-07',
        created_by: 1,
    },
    {
        id: 3,
        server_id: 1,
        name: 'Deploy',
        description: null,
        starts_on: '2026-08-04',
        ends_on: '2026-08-07',
        created_by: 1,
    },
];

const tasks: GanttTask[] = [
    {
        id: 'channel-2',
        type: 'channel',
        title: 'Design',
        start: '2026-08-03',
        end: '2026-08-07',
        channel_id: 2,
        channel_name: 'Design',
    },
    {
        id: 'todo-1',
        type: 'todo',
        title: 'Implement design',
        start: '2026-08-03',
        end: '2026-08-05',
        channel_id: 2,
        channel_name: 'Design',
        completed: false,
    },
    {
        id: 'channel-3',
        type: 'channel',
        title: 'Deploy',
        start: '2026-08-04',
        end: '2026-08-07',
        channel_id: 3,
        channel_name: 'Deploy',
    },
    {
        id: 'todo-2',
        type: 'todo',
        title: 'Verify deployment',
        start: '2026-08-05',
        end: '2026-08-07',
        channel_id: 3,
        channel_name: 'Deploy',
        completed: true,
    },
];

afterEach(cleanup);

describe('Gantt readability', () => {
    it('uses readable text sizes and theme-aware task colors', () => {
        render(Gantt, {
            props: {
                server,
                tasks,
                channels,
                members: [],
            },
        });

        expect(
            document
                .querySelector('[data-gantt-grid-header]')
                ?.classList.contains('text-sm'),
        ).toBe(true);
        expect(
            document
                .querySelector('[data-gantt-legend]')
                ?.classList.contains('text-sm'),
        ).toBe(true);

        const taskLabels = document.querySelectorAll('[data-gantt-task-label]');
        expect(taskLabels.length).toBe(4);
        expect(
            Array.from(taskLabels).every((label) =>
                label.classList.contains('text-sm'),
            ),
        ).toBe(true);

        const bars = Array.from(
            document.querySelectorAll<HTMLElement>('[data-gantt-bar]'),
        );
        expect(bars).toHaveLength(4);
        expect(
            bars.every(
                (bar) =>
                    bar.classList.contains('h-6') &&
                    bar.classList.contains('text-xs'),
            ),
        ).toBe(true);
        expect(
            bars.find((bar) => bar.textContent?.includes('Design'))?.classList,
        ).toContain('bg-gantt-channel');
        expect(
            bars.find((bar) => bar.textContent?.includes('Implement design'))
                ?.classList,
        ).toContain('bg-gantt-task');
        expect(
            bars.find((bar) => bar.textContent?.includes('Verify deployment'))
                ?.classList,
        ).toContain('bg-gantt-complete');
        expect(screen.getByRole('button', { name: 'PDF出力' })).toBeTruthy();
    });

    it('uses the project period for a server-wide gantt with one channel', () => {
        render(Gantt, {
            props: {
                server,
                tasks: tasks.slice(0, 2),
                channels: channels.slice(0, 1),
                members: [],
            },
        });

        expect(document.querySelectorAll('[data-gantt-tick]')).toHaveLength(14);
    });

    it('uses the channel period for a channel-scoped gantt', () => {
        render(Gantt, {
            props: {
                server,
                channel: channels[0],
                tasks: tasks.slice(0, 2),
                channels: channels.slice(0, 1),
                members: [],
            },
        });

        expect(document.querySelectorAll('[data-gantt-tick]')).toHaveLength(5);
    });

    it('alternates the background for each project month', () => {
        render(Gantt, {
            props: {
                server: {
                    ...server,
                    starts_on: '2026-08-01',
                    ends_on: '2026-10-31',
                },
                tasks,
                channels,
                members: [],
            },
        });

        const monthCells = Array.from(
            document.querySelectorAll<HTMLElement>('[data-gantt-month]'),
        );

        expect(monthCells).toHaveLength(92);
        expect(monthCells[0]?.classList.contains('bg-muted/10')).toBe(true);
        expect(
            monthCells[31]?.classList.contains('bg-brand/5') &&
                monthCells[31]?.classList.contains('border-l-2'),
        ).toBe(true);
        expect(
            monthCells[61]?.classList.contains('bg-muted/10') &&
                monthCells[61]?.classList.contains('border-l-2'),
        ).toBe(true);
    });
});

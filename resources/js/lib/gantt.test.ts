import { describe, expect, it } from 'vitest';
import {
    epochDay,
    exactGanttRange,
    formatDateOnly,
    formatEpochDay,
    getGanttRange,
    groupGanttTasks,
    gridColumn,
    singleChannelTitle,
} from './gantt';

describe('gantt date calculations', () => {
    it('keeps date-only values stable in local timezones', () => {
        expect(
            formatDateOnly('2026-08-02', { month: 'numeric', day: 'numeric' }),
        ).toBe('8/2');
        expect(epochDay('2026-08-02')).toBe(epochDay(new Date(2026, 7, 2)));
        expect(
            formatEpochDay(epochDay('2026-08-02'), {
                month: 'numeric',
                day: 'numeric',
                weekday: 'short',
            }),
        ).toContain('日');
    });

    it('spans the full task date range after the task label column', () => {
        const rangeStart = epochDay('2026-08-02');

        expect(gridColumn('2026-08-02', '2026-08-06', rangeStart, 13)).toBe(
            '2 / 7',
        );
    });

    it('uses the project dates as the minimum gantt range', () => {
        const range = getGanttRange(
            '2026-08-02',
            '2026-08-14',
            [{ start: '2026-08-02', end: '2026-08-06' }],
            new Date(2026, 7, 2),
        );

        expect(range.end - range.start + 1).toBe(13);
        expect(range.start).toBe(epochDay('2026-08-02'));
        expect(range.end).toBe(epochDay('2026-08-14'));

        const channelRange = getGanttRange(
            '2026-08-06',
            '2026-08-13',
            [{ start: '2026-08-06', end: '2026-08-10' }],
            new Date(2026, 7, 2),
        );

        expect(channelRange.end - channelRange.start + 1).toBe(8);
    });

    it('moves a single channel title into the gantt header', () => {
        expect(
            singleChannelTitle([
                { type: 'channel', title: '実装トーク' },
                { type: 'todo', title: 'トップページ' },
            ]),
        ).toBe('実装トーク');
        expect(
            singleChannelTitle([
                { type: 'channel', title: '実装トーク' },
                { type: 'channel', title: 'デザイン' },
            ]),
        ).toBeNull();
    });

    it('groups each channel directly before its tasks', () => {
        const grouped = groupGanttTasks([
            { id: 'channel-1', type: 'channel', channel_id: 1 },
            { id: 'channel-2', type: 'channel', channel_id: 2 },
            { id: 'todo-2', type: 'todo', channel_id: 2 },
            { id: 'todo-1', type: 'todo', channel_id: 1 },
        ]);

        expect(grouped.map((task) => task.id)).toEqual([
            'channel-1',
            'todo-1',
            'channel-2',
            'todo-2',
        ]);
    });

    it('keeps an empty channel gantt within the channel period', () => {
        const range = exactGanttRange('2026-08-19', '2026-08-28');

        expect(range.start).toBe(epochDay('2026-08-19'));
        expect(range.end).toBe(epochDay('2026-08-28'));
    });
});

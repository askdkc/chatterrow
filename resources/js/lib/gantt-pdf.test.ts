import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { epochDay } from './gantt';
import { buildGanttPdf } from './gantt-pdf';

const fontPath = join(
    process.cwd(),
    'public/fonts/SourceHanSansJP-Regular.ttf',
);

describe('buildGanttPdf', () => {
    it('produces a single landscape A4 page from gantt data', async () => {
        const rangeStart = epochDay('2026-08-02');
        const rangeEnd = epochDay('2026-08-14');

        const doc = await buildGanttPdf({
            title: '実装トーク',
            rangeStart,
            rangeEnd,
            today: epochDay('2026-08-06'),
            fontData: null,
            tasks: [
                {
                    id: 'channel-1',
                    type: 'channel',
                    title: '実装トーク',
                    start: '2026-08-02',
                    end: '2026-08-14',
                },
                {
                    id: 'todo-1',
                    type: 'todo',
                    title: 'トップページの設計',
                    start: '2026-08-02',
                    end: '2026-08-06',
                },
                {
                    id: 'todo-2',
                    type: 'todo',
                    title: 'API実装',
                    start: '2026-08-07',
                    end: '2026-08-10',
                    completed: true,
                },
            ],
        });

        expect(doc.getNumberOfPages()).toBe(1);
        expect(doc.internal.pageSize.getWidth()).toBeCloseTo(297);
        expect(doc.internal.pageSize.getHeight()).toBeCloseTo(210);

        const blob = doc.output('blob');
        expect(blob.size).toBeGreaterThan(1000);
    });

    it('embeds the bundled Source Han Sans JP font into the PDF', async () => {
        const fontData = readFileSync(fontPath).toString('base64');

        expect(fontData.length).toBeGreaterThan(0);

        const doc = await buildGanttPdf({
            title: '実装トーク',
            rangeStart: epochDay('2026-08-02'),
            rangeEnd: epochDay('2026-08-06'),
            tasks: [],
            fontData,
        });

        expect(doc.getNumberOfPages()).toBe(1);

        const blob = doc.output('blob');
        expect(blob.size).toBeGreaterThan(50_000);
    });

    it('handles an empty task list', async () => {
        const rangeStart = epochDay('2026-08-19');
        const rangeEnd = epochDay('2026-08-28');

        const doc = await buildGanttPdf({
            title: '空のチャンネル',
            rangeStart,
            rangeEnd,
            tasks: [],
            fontData: null,
        });

        expect(doc.getNumberOfPages()).toBe(1);
        expect(doc.output('blob').size).toBeGreaterThan(500);
    });
});

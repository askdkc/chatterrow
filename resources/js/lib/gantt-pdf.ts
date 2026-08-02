import { jsPDF } from 'jspdf';
import { epochDay, formatEpochDay } from './gantt';

export interface GanttPdfTask {
    id: string;
    type: 'channel' | 'todo';
    title: string;
    start: string | null;
    end: string | null;
    completed?: boolean;
}

export interface GanttPdfOptions {
    title: string;
    subtitle?: string | null;
    rangeStart: number;
    rangeEnd: number;
    tasks: GanttPdfTask[];
    today?: number;
    fontData?: string | null;
}

const PAGE_W = 297;
const PAGE_H = 210;
const MARGIN = 10;
const HEADER_H = 18;
const DATE_ROW_H = 9;
const ROW_H = 9;
const LABEL_W = 72;

type Rgb = [number, number, number];

const COLORS: Record<string, Rgb> = {
    channel: [88, 101, 242],
    todo: [240, 178, 50],
    completed: [35, 165, 89],
    grid: [230, 230, 235],
    weekend: [243, 244, 247],
    text: [30, 34, 40],
    muted: [110, 115, 125],
};

const JAPANESE_FONT_URL = '/fonts/SourceHanSansJP-Regular.ttf';
const JAPANESE_FONT_NAME = 'SourceHanSansJP';

let japaneseFontPromise: Promise<string | null> | null = null;

async function loadJapaneseFont(): Promise<string | null> {
    if (typeof window === 'undefined' || typeof window.fetch !== 'function') {
        return null;
    }

    japaneseFontPromise ??= window
        .fetch(JAPANESE_FONT_URL)
        .then((response) => {
            if (!response.ok) {
                throw new Error(
                    `Japanese PDF font failed to load: ${response.status}`,
                );
            }

            return response.arrayBuffer();
        })
        .then((buffer) => {
            const bytes = new Uint8Array(buffer);
            let binary = '';

            for (let offset = 0; offset < bytes.length; offset += 0x8000) {
                binary += String.fromCharCode(
                    ...bytes.subarray(offset, offset + 0x8000),
                );
            }

            return btoa(binary);
        });

    return japaneseFontPromise;
}

export async function buildGanttPdf(options: GanttPdfOptions): Promise<jsPDF> {
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4',
        compress: true,
    });
    const japaneseFont =
        options.fontData === undefined
            ? await loadJapaneseFont()
            : options.fontData;

    if (japaneseFont) {
        doc.addFileToVFS(JAPANESE_FONT_URL, japaneseFont);
        doc.addFont(JAPANESE_FONT_URL, JAPANESE_FONT_NAME, 'normal');
        doc.setFont(JAPANESE_FONT_NAME, 'normal');
    }

    const dayCount = options.rangeEnd - options.rangeStart + 1;
    const tableX = MARGIN;
    const tableY = MARGIN + HEADER_H;
    const tableW = PAGE_W - MARGIN * 2;
    const chartX = tableX + LABEL_W;
    const chartW = tableW - LABEL_W;
    const dayW = chartW / dayCount;
    const maxRows = Math.floor((PAGE_H - MARGIN - tableY - DATE_ROW_H) / ROW_H);
    const visibleTasks = options.tasks.slice(0, maxRows);
    const tableH = DATE_ROW_H + visibleTasks.length * ROW_H;

    doc.setFont(japaneseFont ? JAPANESE_FONT_NAME : 'helvetica', 'normal');
    doc.setFontSize(16);
    doc.setTextColor(...COLORS.text);
    doc.text(options.title, tableX, MARGIN + 7);

    doc.setFont(japaneseFont ? JAPANESE_FONT_NAME : 'helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...COLORS.muted);
    const period = `${formatEpochDay(options.rangeStart, { month: 'short', day: 'numeric' })} 〜 ${formatEpochDay(options.rangeEnd, { month: 'short', day: 'numeric' })}`;
    doc.text(period, tableX, MARGIN + 12);

    const drawLegend = (): void => {
        const items = [
            { label: 'チャンネル', color: COLORS.channel },
            { label: 'タスク', color: COLORS.todo },
            { label: '完了', color: COLORS.completed },
        ];
        let x = tableX + tableW;

        doc.setFontSize(7);

        for (const item of [...items].reverse()) {
            const labelWidth = doc.getTextWidth(item.label);
            const itemWidth = labelWidth + 7;
            x -= itemWidth;
            doc.setFillColor(...item.color);
            doc.rect(x, MARGIN + 9, 3, 3, 'F');
            doc.setTextColor(...COLORS.muted);
            doc.text(item.label, x + 4, MARGIN + 12);
            x -= 4;
        }
    };

    const dayX = (day: number): number =>
        chartX + (day - options.rangeStart) * dayW;

    const drawTableFrame = (): void => {
        doc.setDrawColor(...COLORS.grid);
        doc.setLineWidth(0.15);
        doc.rect(tableX, tableY, tableW, tableH);

        for (let day = options.rangeStart; day <= options.rangeEnd; day += 1) {
            const date = new Date(day * 86_400_000);
            const weekday = date.getUTCDay();

            if (weekday === 0 || weekday === 6) {
                doc.setFillColor(...COLORS.weekend);
                doc.rect(dayX(day), tableY, dayW, tableH, 'F');
            }

            doc.setDrawColor(...COLORS.grid);
            doc.line(dayX(day), tableY, dayX(day), tableY + tableH);
        }

        doc.line(chartX, tableY, chartX, tableY + tableH);
        doc.line(
            tableX,
            tableY + DATE_ROW_H,
            tableX + tableW,
            tableY + DATE_ROW_H,
        );
    };

    const drawDateRow = (): void => {
        doc.setFontSize(5.5);
        doc.setTextColor(...COLORS.muted);

        for (let day = options.rangeStart; day <= options.rangeEnd; day += 1) {
            const x = dayX(day);
            const date = new Date(day * 86_400_000);
            const label = `${date.getUTCMonth() + 1}/${date.getUTCDate()}(${'日月火水木金土'.charAt(date.getUTCDay())})`;
            const isToday = options.today === day;

            if (isToday) {
                doc.setDrawColor(...COLORS.channel);
                doc.setLineWidth(0.3);
                doc.rect(x, tableY, dayW, DATE_ROW_H);
                doc.setLineWidth(0.15);
            }

            doc.text(label, x + dayW / 2, tableY + DATE_ROW_H / 2 + 1, {
                align: 'center',
            });
        }
    };

    const drawTasks = (): void => {
        visibleTasks.forEach((task, index) => {
            const rowY = tableY + DATE_ROW_H + index * ROW_H;

            doc.setFont(
                japaneseFont ? JAPANESE_FONT_NAME : 'helvetica',
                'normal',
            );
            doc.setFontSize(7);
            doc.setTextColor(...COLORS.text);
            const title =
                task.title.length > 26
                    ? `${task.title.slice(0, 25)}…`
                    : task.title;
            doc.text(title, tableX + 2, rowY + ROW_H / 2 + 1.5, {
                maxWidth: LABEL_W - 4,
            });

            if (task.start === null || task.end === null) {
                return;
            }

            const startX = Math.max(dayX(epochDay(task.start)), chartX);
            const endX = Math.min(
                dayX(epochDay(task.end)) + dayW,
                chartX + chartW,
            );
            const barX = startX + 0.6;
            const barW = Math.max(endX - startX - 1.2, 2);

            doc.setFillColor(
                ...(task.type === 'channel'
                    ? COLORS.channel
                    : task.completed
                      ? COLORS.completed
                      : COLORS.todo),
            );
            doc.roundedRect(barX, rowY + 1.6, barW, ROW_H - 3.2, 1, 1, 'F');

            const titleChars = Array.from(task.title);
            const maxChars = Math.max(1, Math.floor((barW - 2) / 1.75));
            const barTitle =
                titleChars.length > maxChars
                    ? `${titleChars.slice(0, Math.max(1, maxChars - 1)).join('')}…`
                    : task.title;
            doc.setFontSize(barW < 10 ? 4 : 5.5);
            doc.setTextColor(255, 255, 255);
            doc.text(barTitle, barX + 1, rowY + ROW_H / 2 + 1);
        });
    };

    drawTableFrame();
    drawDateRow();
    drawLegend();
    drawTasks();

    return doc;
}

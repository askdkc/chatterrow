import { jsPDF } from 'jspdf';
import { currentLocale } from '@/lib/dates';
import { t } from '@/lib/i18n';
import { epochDay, formatEpochDay, monthSegments } from './gantt';

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
const MONTH_ROW_H = 9;
const ROW_H = 9;
const LABEL_W = 72;
const GRID_LINE_W = 0.2;
const GRID_STRONG_LINE_W = 0.3;

type Rgb = [number, number, number];

const COLORS: Record<string, Rgb> = {
    channel: [88, 101, 242],
    todo: [240, 178, 50],
    completed: [35, 165, 89],
    grid: [185, 190, 200],
    gridStrong: [145, 152, 165],
    monthEven: [249, 250, 253],
    monthOdd: [242, 246, 252],
    text: [30, 34, 40],
    muted: [110, 115, 125],
};

interface PdfFontProfile {
    url: string;
    name: string;
}

const PDF_FONT_PROFILES: Record<string, PdfFontProfile> = {
    ja: {
        url: '/fonts/SourceHanSansJP-Regular.ttf',
        name: 'SourceHanSansJP',
    },
    'zh-CN': {
        url: '/fonts/SourceHanSansCN-VF.ttf',
        name: 'SourceHanSansCN',
    },
    'zh-TW': {
        url: '/fonts/SourceHanSansTW-VF.ttf',
        name: 'SourceHanSansTW',
    },
    ko: {
        url: '/fonts/SourceHanSansKR-VF.ttf',
        name: 'SourceHanSansKR',
    },
};

const fontPromises = new Map<string, Promise<string | null>>();

export function pdfFontProfile(locale = currentLocale()): PdfFontProfile {
    const normalized = locale?.replaceAll('_', '-') ?? 'ja';

    return PDF_FONT_PROFILES[normalized] ?? PDF_FONT_PROFILES.ja;
}

async function loadPdfFont(profile: PdfFontProfile): Promise<string | null> {
    if (typeof window === 'undefined' || typeof window.fetch !== 'function') {
        return null;
    }

    let promise = fontPromises.get(profile.url);

    if (promise) {
        return promise;
    }

    promise = window
        .fetch(profile.url)
        .then((response) => {
            if (!response.ok) {
                throw new Error(
                    t('Failed to load PDF font: :status', {
                        status: String(response.status),
                    }),
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
    fontPromises.set(profile.url, promise);

    return promise;
}

export async function buildGanttPdf(options: GanttPdfOptions): Promise<jsPDF> {
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4',
        compress: true,
    });
    const fontProfile = pdfFontProfile();
    const pdfFont =
        options.fontData === undefined
            ? await loadPdfFont(fontProfile)
            : options.fontData;

    if (pdfFont) {
        doc.addFileToVFS(fontProfile.url, pdfFont);
        doc.addFont(fontProfile.url, fontProfile.name, 'normal');
        doc.setFont(fontProfile.name, 'normal');
    }

    const months = monthSegments(options.rangeStart, options.rangeEnd);
    const tableX = MARGIN;
    const tableY = MARGIN + HEADER_H;
    const tableW = PAGE_W - MARGIN * 2;
    const chartX = tableX + LABEL_W;
    const chartW = tableW - LABEL_W;
    const monthW = chartW / months.length;
    const maxRows = Math.floor(
        (PAGE_H - MARGIN - tableY - MONTH_ROW_H) / ROW_H,
    );
    const visibleTasks = options.tasks.slice(0, maxRows);
    const tableH = MONTH_ROW_H + visibleTasks.length * ROW_H;

    doc.setFont(pdfFont ? fontProfile.name : 'helvetica', 'normal');
    doc.setFontSize(16);
    doc.setTextColor(...COLORS.text);
    doc.text(options.title, tableX, MARGIN + 7);

    doc.setFont(pdfFont ? fontProfile.name : 'helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...COLORS.muted);
    const period = t('Date range: :start - :end', {
        start: formatEpochDay(options.rangeStart, {
            month: 'short',
            day: 'numeric',
        }),
        end: formatEpochDay(options.rangeEnd, {
            month: 'short',
            day: 'numeric',
        }),
    });
    doc.text(period, tableX, MARGIN + 12);

    const drawLegend = (): void => {
        const items = [
            { label: t('Channels'), color: COLORS.channel },
            { label: t('Tasks'), color: COLORS.todo },
            { label: t('Completed'), color: COLORS.completed },
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

    const xForDay = (day: number): number => {
        if (day <= options.rangeStart) {
            return chartX;
        }

        if (day > options.rangeEnd) {
            return chartX + chartW;
        }

        const monthIndex = months.findIndex(
            (month) => day >= month.startDay && day <= month.endDay,
        );
        const month = months[monthIndex];
        const monthDayCount = month.endDay - month.startDay + 1;

        return (
            chartX +
            monthIndex * monthW +
            ((day - month.startDay) / monthDayCount) * monthW
        );
    };

    const drawTableFrame = (): void => {
        doc.setDrawColor(...COLORS.gridStrong);
        doc.setLineWidth(GRID_STRONG_LINE_W);
        doc.rect(tableX, tableY, tableW, tableH);

        doc.setDrawColor(...COLORS.grid);
        doc.setLineWidth(GRID_LINE_W);

        months.forEach((_, index) => {
            const x = chartX + index * monthW;

            doc.setFillColor(
                ...(index % 2 === 0 ? COLORS.monthEven : COLORS.monthOdd),
            );
            doc.rect(x, tableY, monthW, tableH, 'F');
            doc.line(x, tableY, x, tableY + tableH);
        });

        for (let row = 1; row < visibleTasks.length; row += 1) {
            const y = tableY + MONTH_ROW_H + row * ROW_H;
            doc.line(tableX, y, tableX + tableW, y);
        }

        doc.setDrawColor(...COLORS.gridStrong);
        doc.setLineWidth(GRID_STRONG_LINE_W);
        doc.line(chartX, tableY, chartX, tableY + tableH);
        doc.line(
            tableX,
            tableY + MONTH_ROW_H,
            tableX + tableW,
            tableY + MONTH_ROW_H,
        );
    };

    const drawMonthRow = (): void => {
        doc.setFontSize(months.length > 12 ? 5.5 : 7);
        doc.setTextColor(...COLORS.muted);

        months.forEach((month, index) => {
            const x = chartX + index * monthW;
            const isOnlyMonth = months.length === 1;
            const isFirstMonth = index === 0;
            const isLastMonth = index === months.length - 1;
            const label =
                isFirstMonth || isLastMonth
                    ? formatEpochDay(
                          isFirstMonth ? options.rangeStart : options.rangeEnd,
                          {
                              month: 'numeric',
                              day: 'numeric',
                          },
                      )
                    : formatEpochDay(month.startDay, {
                          year: 'numeric',
                          month: 'short',
                      });

            if (
                options.today !== undefined &&
                options.today >= month.startDay &&
                options.today <= month.endDay
            ) {
                doc.setDrawColor(...COLORS.channel);
                doc.setLineWidth(0.3);
                doc.rect(x, tableY, monthW, MONTH_ROW_H);
                doc.setLineWidth(GRID_LINE_W);
            }

            if (isOnlyMonth && options.rangeStart !== options.rangeEnd) {
                doc.text(label, x + 2, tableY + MONTH_ROW_H / 2 + 1, {
                    align: 'left',
                });
                doc.text(
                    formatEpochDay(options.rangeEnd, {
                        month: 'numeric',
                        day: 'numeric',
                    }),
                    x + monthW - 2,
                    tableY + MONTH_ROW_H / 2 + 1,
                    { align: 'right' },
                );
            } else {
                doc.text(label, x + monthW / 2, tableY + MONTH_ROW_H / 2 + 1, {
                    align: 'center',
                });
            }
        });
    };

    const drawTasks = (): void => {
        visibleTasks.forEach((task, index) => {
            const rowY = tableY + MONTH_ROW_H + index * ROW_H;

            doc.setFont(pdfFont ? fontProfile.name : 'helvetica', 'normal');
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

            const startX = Math.max(xForDay(epochDay(task.start)), chartX);
            const endX = Math.min(
                xForDay(epochDay(task.end) + 1),
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
    drawMonthRow();
    drawLegend();
    drawTasks();

    return doc;
}

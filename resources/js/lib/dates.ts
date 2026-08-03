/**
 * Shared date/time formatting helpers (ja-JP locale).
 *
 * These centralise the formatters that previously lived in each component:
 *   - formatDate / formatDateTime  (Tasks, TodoPanel, MessageItem, Files)
 *   - dateInputValue / timeInputValue (TodoDialog input serialisation)
 * Options default to the most common variant so call sites stay readable.
 */

const pad = (part: number): string => String(part).padStart(2, '0');

/** Format a Date as YYYY-MM-DD (local time). */
export function dateValue(value: Date): string {
    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
}

/** Format a Date as HH:mm (local time). */
export function timeValue(value: Date): string {
    return `${pad(value.getHours())}:${pad(value.getMinutes())}`;
}

/** Parse an ISO string (or null) into YYYY-MM-DD for <input type="date">. */
export function dateInputValue(value: string | null | undefined): string {
    const parsed = parseOrNull(value);

    return parsed ? dateValue(parsed) : '';
}

/** Parse an ISO string (or null) into HH:mm for <input type="time">. */
export function timeInputValue(value: string | null | undefined): string {
    const parsed = parseOrNull(value);

    return parsed ? timeValue(parsed) : '';
}

interface DateFormatOptions {
    /** Include the year in the output. Default: true. */
    year?: boolean;
    /** Month style. Default: 'short' (e.g. 8月). */
    month?: 'numeric' | 'short' | 'long';
    /** Fallback text when iso is empty/invalid. Default: ''. */
    fallback?: string;
}

function parseOrNull(iso: string | null | undefined): Date | null {
    if (!iso) {
        return null;
    }

    // Date-only strings (YYYY-MM-DD) are parsed as UTC midnight by Date();
    // normalise to local midnight to match the previous `T00:00:00` handling.
    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(iso)
        ? `${iso}T00:00:00`
        : iso;
    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/** e.g. 2026年8月3日 (year on, month short) or 8月3日 (year off). */
export function localDateTimeIso(date: string, time: string): string | null {
    if (!date) {
        return null;
    }

    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = (time || '00:00').split(':').map(Number);
    const value = new Date(year, month - 1, day, hour, minute);

    return Number.isNaN(value.getTime()) ? null : value.toISOString();
}

export function formatDate(
    iso: string | null | undefined,
    options: DateFormatOptions = {},
): string {
    const parsed = parseOrNull(iso);

    if (!parsed) {
        return options.fallback ?? '';
    }

    return parsed.toLocaleDateString('ja-JP', {
        year: options.year === false ? undefined : 'numeric',
        month: options.month ?? 'short',
        day: 'numeric',
    });
}

/** e.g. 2026年8月3日 14:05 (year on, month short) or 8/3 14:05 (year off, numeric). */
export function formatDateTime(
    iso: string | null | undefined,
    options: DateFormatOptions = {},
): string {
    const parsed = parseOrNull(iso);

    if (!parsed) {
        return options.fallback ?? '';
    }

    return parsed.toLocaleString('ja-JP', {
        year: options.year === false ? undefined : 'numeric',
        month: options.month ?? 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** e.g. 14:05 */
export function formatTime(iso: string | null | undefined): string {
    const parsed = parseOrNull(iso);

    if (!parsed) {
        return '';
    }

    return parsed.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

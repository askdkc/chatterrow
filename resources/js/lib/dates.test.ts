import { describe, expect, it } from 'vitest';
import {
    dateInputValue,
    dateValue,
    formatDate,
    formatDateTime,
    formatTime,
    localDateTimeIso,
    timeInputValue,
    timeValue,
} from './dates';

describe('date helpers', () => {
    it('serialises local dates and times for form inputs', () => {
        const value = new Date(2026, 7, 3, 5, 7);

        expect(dateValue(value)).toBe('2026-08-03');
        expect(timeValue(value)).toBe('05:07');
        expect(dateInputValue(value.toISOString())).toBe(dateValue(value));
        expect(timeInputValue(value.toISOString())).toBe(timeValue(value));
    });

    it('keeps date-only values on the same local calendar day', () => {
        expect(dateInputValue('2026-08-03')).toBe('2026-08-03');
        expect(
            formatDate('2026-08-03', {
                year: false,
                month: 'numeric',
            }),
        ).toBe('8/3');
    });

    it('supports each display variant used by the components', () => {
        const value = new Date(2026, 7, 3, 14, 5).toISOString();

        expect(formatDate(value)).toContain('2026');
        expect(formatDate(value, { month: 'long' })).toContain('8月');
        expect(
            formatDateTime(value, { year: false, month: 'numeric' }),
        ).toContain('14:05');
        expect(formatTime(value)).toContain('14:05');
    });

    it('converts local date and time inputs to an absolute ISO instant', () => {
        expect(localDateTimeIso('2026-08-03', '19:00')).toBe(
            new Date(2026, 7, 3, 19, 0).toISOString(),
        );
        expect(localDateTimeIso('', '19:00')).toBeNull();
    });

    it('returns configured fallbacks for missing or invalid values', () => {
        expect(formatDate(null, { fallback: '未設定' })).toBe('未設定');
        expect(formatDateTime('invalid', { fallback: 'なし' })).toBe('なし');
        expect(dateInputValue('invalid')).toBe('');
        expect(timeInputValue(undefined)).toBe('');
        expect(formatTime('invalid')).toBe('');
    });
});

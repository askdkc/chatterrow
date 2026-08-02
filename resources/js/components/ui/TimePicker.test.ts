import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import TimePicker from './TimePicker.svelte';

afterEach(cleanup);

describe('TimePicker', () => {
    it('shows 30-minute options and updates the input', async () => {
        render(TimePicker, { props: { id: 'time', value: '09:00' } });

        const input = screen.getByRole('combobox');
        await fireEvent.click(input);

        expect(screen.getByRole('option', { name: '09:30' })).toBeTruthy();
        expect(screen.queryByRole('option', { name: '09:15' })).toBeNull();

        await fireEvent.click(screen.getByRole('option', { name: '09:30' }));

        expect((input as HTMLInputElement).value).toBe('09:30');
        expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('closes when clicking outside', async () => {
        render(TimePicker, { props: { id: 'time' } });

        await fireEvent.click(screen.getByRole('combobox'));
        expect(screen.getByRole('listbox')).toBeTruthy();

        await fireEvent.pointerDown(document.body);

        expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('normalizes a typed half-hour value', async () => {
        render(TimePicker, { props: { id: 'time' } });

        const input = screen.getByRole('combobox') as HTMLInputElement;
        await fireEvent.input(input, { target: { value: '9:30' } });
        await fireEvent.blur(input);

        expect(input.value).toBe('09:30');
    });
});

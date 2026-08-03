import { describe, expect, it } from 'vitest';
import { priorityLabel } from './todos';

describe('todo helpers', () => {
    it.each([
        ['low', '低'],
        ['normal', '通常'],
        ['high', '高'],
        ['urgent', '緊急'],
    ] as const)('formats %s priority', (priority, label) => {
        expect(priorityLabel(priority)).toBe(label);
    });
});

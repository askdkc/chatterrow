import type { TodoResource } from '@/types';

const priorityLabels: Record<TodoResource['priority'], string> = {
    low: '低',
    normal: '通常',
    high: '高',
    urgent: '緊急',
};

export function priorityLabel(priority: TodoResource['priority']): string {
    return priorityLabels[priority];
}

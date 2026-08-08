import { t } from '@/lib/i18n';
import type { TodoResource } from '@/types';

const priorityKeys: Record<TodoResource['priority'], string> = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
    urgent: 'Urgent',
};

export function priorityLabel(priority: TodoResource['priority']): string {
    return t(priorityKeys[priority]);
}

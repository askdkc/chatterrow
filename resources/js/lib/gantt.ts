const DAY_MS = 86_400_000;

export interface GanttRangeTask {
    start: string | null;
    end: string | null;
}

export function singleChannelTitle(
    tasks: { type: 'channel' | 'todo'; title: string }[],
): string | null {
    const channels = tasks.filter((task) => task.type === 'channel');

    return channels.length === 1 ? channels[0].title : null;
}

export function groupGanttTasks<
    T extends { type: 'channel' | 'todo'; channel_id: number },
>(tasks: T[]): T[] {
    const channels = tasks.filter((task) => task.type === 'channel');
    const todos = tasks.filter((task) => task.type === 'todo');
    const groupedTodoIds = new Set<number>();
    const grouped: T[] = [];

    for (const channel of channels) {
        grouped.push(channel);

        for (const todo of todos) {
            if (todo.channel_id === channel.channel_id) {
                grouped.push(todo);
                groupedTodoIds.add(todo.channel_id);
            }
        }
    }

    return [
        ...grouped,
        ...todos.filter((todo) => !groupedTodoIds.has(todo.channel_id)),
    ];
}

export function epochDay(value: string | Date): number {
    if (typeof value === 'string') {
        const [year, month, day] = value.slice(0, 10).split('-').map(Number);

        return Math.floor(Date.UTC(year, month - 1, day) / DAY_MS);
    }

    return Math.floor(
        Date.UTC(value.getFullYear(), value.getMonth(), value.getDate()) /
            DAY_MS,
    );
}

export function formatEpochDay(
    day: number,
    options: Intl.DateTimeFormatOptions,
): string {
    return new Date(day * DAY_MS).toLocaleDateString('ja-JP', {
        ...options,
        timeZone: 'UTC',
    });
}

export function formatDateOnly(
    value: string,
    options: Intl.DateTimeFormatOptions,
): string {
    return formatEpochDay(epochDay(value), options);
}

export function gridColumn(
    start: string,
    end: string,
    rangeStart: number,
    dayCount: number,
): string {
    const first = Math.max(0, epochDay(start) - rangeStart);
    const last = Math.min(dayCount - 1, epochDay(end) - rangeStart);

    // Column 1 contains the task label; timeline columns start at 2.
    return `${first + 2} / ${last + 3}`;
}

export function exactGanttRange(
    start: string,
    end: string,
): { start: number; end: number } {
    return { start: epochDay(start), end: epochDay(end) };
}

export function getGanttRange(
    projectStart: string | null,
    projectEnd: string | null,
    tasks: GanttRangeTask[],
    today = new Date(),
): { start: number; end: number } {
    const datedTasks = tasks.filter(
        (task): task is { start: string; end: string } =>
            task.start !== null && task.end !== null,
    );
    const taskStart =
        datedTasks.length > 0
            ? Math.min(...datedTasks.map((task) => epochDay(task.start)))
            : epochDay(today);
    const taskEnd =
        datedTasks.length > 0
            ? Math.max(...datedTasks.map((task) => epochDay(task.end)))
            : taskStart + 6;
    const start = Math.min(
        taskStart,
        projectStart ? epochDay(projectStart) : taskStart,
    );
    const end = Math.max(
        taskEnd,
        projectEnd ? epochDay(projectEnd) : start + 6,
    );

    return { start, end };
}

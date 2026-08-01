<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        CalendarRange,
        Hash,
        CheckCircle2,
    } from 'lucide-svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import type {
        ServerResource,
        ChannelResource,
        UserResource,
    } from '@/types';

    interface GanttTask {
        id: string;
        type: 'channel' | 'todo';
        title: string;
        start: string | null;
        end: string | null;
        channel_id: number;
        channel_name: string;
        completed?: boolean;
    }

    let {
        server,
        tasks,
        channels,
        members,
        authServers,
    }: {
        server: ServerResource;
        tasks: GanttTask[];
        channels: ChannelResource[];
        members: UserResource[];
        authServers: ServerResource[];
    } = $props();

    const epochDay = (value: string | Date): number =>
        Math.floor(new Date(`${value}T00:00:00`).getTime() / 86_400_000);

    const dated = tasks.filter((t) => t.start !== null && t.end !== null);
    const minDay = $derived(
        dated.length > 0
            ? Math.min(...dated.map((t) => epochDay(t.start as string)))
            : epochDay(new Date()),
    );
    const maxDay = $derived(
        dated.length > 0
            ? Math.max(...dated.map((t) => epochDay(t.end as string)))
            : epochDay(new Date()) + 6,
    );
    const rangeStart = $derived(minDay);
    const rangeEnd = $derived(Math.max(maxDay, minDay + 6));
    const dayCount = $derived(rangeEnd - rangeStart + 1);

    // Ticks: weekly for <=120 days, monthly beyond.
    const ticks = $derived.by(() => {
        if (dayCount <= 14) {
            return Array.from({ length: dayCount }, (_, i) => rangeStart + i);
        }

        if (dayCount <= 120) {
            const result: number[] = [];

            for (let d = rangeStart; d <= rangeEnd; d++) {
                if (new Date(d * 86_400_000).getDay() === 1) {
                    result.push(d);
                }
            }

            return result.length ? result : [rangeStart];
        }

        const result: number[] = [];

        for (let d = rangeStart; d <= rangeEnd; d++) {
            const date = new Date(d * 86_400_000);

            if (date.getDate() === 1) {
                result.push(d);
            }
        }

        return result.length ? result : [rangeStart];
    });

    const today = epochDay(new Date());

    const gridColumn = (start: string, end: string): string => {
        const first = Math.max(0, epochDay(start) - rangeStart);
        const last = Math.min(dayCount - 1, epochDay(end) - rangeStart);

        return `${first + 1} / ${last + 2}`;
    };

    const formatTick = (day: number): string => {
        const d = new Date(day * 86_400_000);

        if (dayCount <= 14) {
            return d.toLocaleDateString('ja-JP', {
                month: 'numeric',
                day: 'numeric',
            });
        }

        if (dayCount <= 120) {
            return d.toLocaleDateString('ja-JP', {
                month: 'numeric',
                day: 'numeric',
            });
        }

        return d.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'short',
        });
    };

    const formatRange = (iso: string | null): string => {
        if (!iso) {
            return '未設定';
        }

        return new Date(`${iso}T00:00:00`).toLocaleDateString('ja-JP', {
            month: 'short',
            day: 'numeric',
        });
    };

    const barClass = (t: GanttTask): string => {
        if (t.type === 'todo') {
            return t.completed ? 'bg-[#23a559]/80' : 'bg-[#f0b232]';
        }

        return 'bg-[#5865f2]';
    };

    function onAddServer() {
        window.location.href = '/servers';
    }

    function onBrowse() {
        window.location.href = '/servers';
    }

    function onAddChannel() {}

    function onManageMembers() {}
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={authServers}
        activeServerId={server.id}
        {onAddServer}
        {onBrowse}
    />

    <ChannelList
        {server}
        {channels}
        {members}
        activeChannelId={null}
        {onAddChannel}
        {onManageMembers}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b border-black/10 bg-[#313338] px-4 dark:border-black/20"
        >
            <Link
                href={`/servers/${server.id}`}
                class="rounded p-1 transition hover:bg-white/10"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <CalendarRange class="h-4 w-4 text-[#5865f2]" />
            <h1 class="text-[15px] font-bold">ガントチャート</h1>
            <div class="ml-auto flex items-center gap-4 text-xs text-[#80848e]">
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-[#5865f2]"></span>
                    チャンネル
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-[#f0b232]"></span>
                    タスク
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-[#23a559]/80"></span>
                    完了
                </span>
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-auto">
            <div
                class="grid"
                style={`grid-template-columns: 260px repeat(${dayCount}, minmax(24px, 1fr)); min-width: ${
                    260 + dayCount * 24
                }px;`}
            >
                <!-- Header row: blank + ticks -->
                <div
                    class="sticky left-0 top-0 z-20 flex h-10 items-center border-b border-r border-black/10 bg-[#2b2d31] px-3 text-xs font-semibold text-[#80848e]"
                >
                    タスク
                </div>
                {#each ticks as day (day)}
                    <div
                        class="sticky top-0 z-10 flex h-10 items-center justify-center border-b border-l border-black/10 bg-[#2b2d31] text-[10px] text-[#80848e]"
                        class:bg-[#232428]={day % 7 === 0}
                        class:text-[#5865f2]={day === today}
                        title={day === today ? '今日' : undefined}
                    >
                        {formatTick(day)}
                    </div>
                {/each}

                <!-- Task rows -->
                {#each tasks as task (task.id)}
                    <div
                        class="sticky left-0 z-10 flex min-h-10 items-center gap-2 border-b border-r border-black/10 bg-[#313338] px-3"
                    >
                        <Link
                            href={`/servers/${server.id}/channels/${task.channel_id}`}
                            class={`truncate text-xs font-medium hover:underline ${
                                task.completed ? 'text-[#23a559]' : ''
                            }`}
                        >
                            {#if task.type === 'channel'}
                                <span class="flex items-center gap-1">
                                    <Hash
                                        class="h-3 w-3 shrink-0 text-[#80848e]"
                                    />
                                    <span class="truncate">{task.title}</span>
                                </span>
                            {:else if task.completed}
                                <span class="flex items-center gap-1">
                                    <CheckCircle2 class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{task.title}</span>
                                </span>
                            {:else}
                                {task.title}
                            {/if}
                        </Link>
                    </div>
                    {#if task.start !== null && task.end !== null}
                        <div
                            class="relative flex min-h-10 items-center border-b border-l border-black/10"
                            style={`grid-column: ${gridColumn(task.start, task.end)};`}
                        >
                            <div
                                class={`mx-0.5 h-4 truncate rounded px-1.5 text-[10px] font-medium leading-4 text-white ${barClass(task)}`}
                                title={`${task.title} (${formatRange(task.start)} 〜 ${formatRange(task.end)})`}
                            >
                                {task.title}
                            </div>
                        </div>
                    {:else}
                        <div
                            class="min-h-10 border-b border-l border-black/10"
                        ></div>
                    {/if}
                {/each}
            </div>
        </div>
    </main>
</div>

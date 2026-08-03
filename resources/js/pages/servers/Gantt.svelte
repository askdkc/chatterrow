<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        CalendarRange,
        FileDown,
        Hash,
        CheckCircle2,
    } from 'lucide-svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import GanttPdfPreviewDialog from '@/components/files/GanttPdfPreviewDialog.svelte';
    import {
        epochDay,
        exactGanttRange,
        formatDateOnly,
        formatEpochDay,
        getGanttRange,
        groupGanttTasks,
        gridColumn,
        singleChannelTitle,
    } from '@/lib/gantt';
    import { buildGanttPdf } from '@/lib/gantt-pdf';
    import type {
        ChannelResource,
        GanttTask,
        ServerResource,
        UserResource,
    } from '@/types';

    let {
        server,
        tasks,
        channels,
        members,
    }: {
        server: ServerResource;
        tasks: GanttTask[];
        channels: ChannelResource[];
        members: UserResource[];
    } = $props();

    const page = usePage();

    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );
    let showMemberDialog = $state(false);

    const channelTask = $derived(
        tasks.find((task) => task.type === 'channel') ?? null,
    );
    const channelHeaderTitle = $derived(singleChannelTitle(tasks));
    const hasChannelRange = $derived(
        Boolean(channelHeaderTitle && channelTask?.start && channelTask.end),
    );
    const channelStart = $derived(channelTask?.start ?? '');
    const channelEnd = $derived(channelTask?.end ?? '');
    const visibleTasks = $derived(
        hasChannelRange
            ? tasks.filter((task) => task.type !== 'channel')
            : tasks,
    );
    const displayTasks = $derived(groupGanttTasks(visibleTasks));
    const range = $derived(
        hasChannelRange
            ? exactGanttRange(channelStart, channelEnd)
            : getGanttRange(server.starts_on, server.ends_on, visibleTasks),
    );
    const rangeStart = $derived(range.start);
    const rangeEnd = $derived(range.end);
    const dayCount = $derived(rangeEnd - rangeStart + 1);

    // Ticks: weekly for <=120 days, monthly beyond.
    const ticks = $derived.by(() => {
        if (dayCount <= 14) {
            return Array.from({ length: dayCount }, (_, i) => rangeStart + i);
        }

        if (dayCount <= 120) {
            const result: number[] = [];

            for (let d = rangeStart; d <= rangeEnd; d++) {
                if (new Date(d * 86_400_000).getUTCDay() === 1) {
                    result.push(d);
                }
            }

            return result.length ? result : [rangeStart];
        }

        const result: number[] = [];

        for (let d = rangeStart; d <= rangeEnd; d++) {
            const date = new Date(d * 86_400_000);

            if (date.getUTCDate() === 1) {
                result.push(d);
            }
        }

        return result.length ? result : [rangeStart];
    });

    const today = epochDay(new Date());

    const formatTick = (day: number): string => {
        if (dayCount <= 14) {
            return formatEpochDay(day, {
                month: 'numeric',
                day: 'numeric',
                weekday: 'short',
            });
        }

        if (dayCount <= 120) {
            return formatEpochDay(day, {
                month: 'numeric',
                day: 'numeric',
                weekday: 'short',
            });
        }

        return formatEpochDay(day, {
            year: 'numeric',
            month: 'short',
        });
    };

    const formatRange = (iso: string | null): string => {
        if (!iso) {
            return '未設定';
        }

        return formatDateOnly(iso, {
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

    function onManageMembers() {
        showMemberDialog = true;
    }

    let pdfPreviewUrl = $state<string | null>(null);
    let pdfFileName = $state('');

    async function exportPdf() {
        const doc = await buildGanttPdf({
            title: channelHeaderTitle ?? server.name,
            subtitle: server.name,
            rangeStart,
            rangeEnd,
            tasks: displayTasks,
            today,
        });

        if (pdfPreviewUrl) {
            URL.revokeObjectURL(pdfPreviewUrl);
        }

        pdfPreviewUrl = URL.createObjectURL(doc.output('blob'));
        pdfFileName = `${channelHeaderTitle ?? server.name}-ガント.pdf`;
    }

    function closePdfPreview() {
        if (pdfPreviewUrl) {
            URL.revokeObjectURL(pdfPreviewUrl);
            pdfPreviewUrl = null;
        }
    }
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
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-2 py-1.5 font-medium transition hover:bg-white/10 hover:text-[#dbdee1]"
                    onclick={exportPdf}
                    title="PDF出力"
                >
                    <FileDown class="h-4 w-4" />
                    PDF出力
                </button>
                {#if !channelHeaderTitle}
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-sm bg-[#5865f2]"
                        ></span>
                        チャンネル
                    </span>
                {/if}
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
                class="relative grid min-h-[calc(100vh-3rem)] content-start"
                style={`grid-template-columns: 260px repeat(${dayCount}, minmax(24px, 1fr)); min-width: ${
                    260 + dayCount * 24
                }px;`}
            >
                <div
                    class="pointer-events-none absolute inset-0 z-0 grid"
                    style={`grid-template-columns: 260px repeat(${dayCount}, minmax(24px, 1fr));`}
                    aria-hidden="true"
                >
                    <div class="border-r border-black/10"></div>
                    {#each Array.from( { length: dayCount } ) as _, dayIndex (dayIndex)}
                        <div
                            class="border-l border-black/10"
                            style={`grid-column: ${dayIndex + 2};`}
                        ></div>
                    {/each}
                </div>

                <!-- Header row: blank + ticks -->
                <div
                    class="sticky left-0 top-0 z-20 flex h-12 items-center border-b border-r border-black/10 bg-[#2b2d31] px-3 text-xs font-semibold text-[#80848e]"
                    style="grid-column: 1; grid-row: 1;"
                >
                    {#if channelHeaderTitle}
                        <span class="flex items-center gap-1">
                            <Hash class="h-3.5 w-3.5" />
                            {channelHeaderTitle}
                        </span>
                    {:else}
                        タスク
                    {/if}
                </div>
                {#each ticks as day (day)}
                    <div
                        class="sticky top-0 z-10 flex h-12 items-center justify-center border-b border-l border-black/10 bg-[#2b2d31] px-1 text-xs font-medium whitespace-nowrap text-[#80848e]"
                        class:bg-[#232428]={day % 7 === 0}
                        class:text-[#5865f2]={day === today}
                        style={`grid-column: ${day - rangeStart + 2}; grid-row: 1;`}
                        title={day === today ? '今日' : undefined}
                    >
                        {formatTick(day)}
                    </div>
                {/each}

                <!-- Task rows -->
                {#each displayTasks as task, taskIndex (task.id)}
                    <div
                        class="sticky left-0 z-10 flex min-h-10 items-center gap-2 border-b border-r border-black/10 bg-[#313338] px-3"
                        style={`grid-column: 1; grid-row: ${taskIndex + 2};`}
                    >
                        <Link
                            href={`/servers/${server.id}/channels/${task.channel_id}`}
                            class={`truncate text-xs font-medium hover:underline ${
                                task.completed ? 'text-[#23a559]' : ''
                            }`}
                        >
                            {#if channelHeaderTitle && task.type === 'channel'}
                                <span class="sr-only">{task.title}</span>
                            {:else if task.type === 'channel'}
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
                            style={`grid-column: ${gridColumn(task.start, task.end, rangeStart, dayCount)}; grid-row: ${taskIndex + 2};`}
                        >
                            <div
                                class={`mx-0.5 h-4 w-full min-w-0 truncate rounded px-1.5 text-[10px] font-medium leading-4 text-white ${barClass(task)}`}
                                title={`${task.title} (${formatRange(task.start)} 〜 ${formatRange(task.end)})`}
                            >
                                {task.title}
                            </div>
                        </div>
                    {:else}
                        <div
                            class="min-h-10 border-b border-l border-black/10"
                            style={`grid-column: 2 / -1; grid-row: ${taskIndex + 2};`}
                        ></div>
                    {/if}
                {/each}
            </div>
        </div>
    </main>
</div>

{#if showMemberDialog}
    <MemberDialog
        {server}
        {members}
        onUpdated={(updated) => (server = { ...server, ...updated })}
        onClose={() => (showMemberDialog = false)}
    />
{/if}

{#if pdfPreviewUrl}
    <GanttPdfPreviewDialog
        name={pdfFileName}
        url={pdfPreviewUrl}
        onClose={closePdfPreview}
    />
{/if}

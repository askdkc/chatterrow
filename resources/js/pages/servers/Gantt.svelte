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
    import { Button } from '@/components/ui/button';
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
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import { cn } from '@/lib/utils';
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
            return t.completed
                ? 'bg-gantt-complete text-gantt-complete-foreground'
                : 'bg-gantt-task text-gantt-task-foreground';
        }

        return 'bg-gantt-channel text-gantt-channel-foreground';
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

<div class="flex h-screen w-full overflow-hidden bg-background text-foreground">
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
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b border-border bg-background px-4"
        >
            <Link
                href={`/servers/${server.id}`}
                class="rounded p-1 transition hover:bg-accent hover:text-accent-foreground"
                aria-label="プロジェクトへ戻る"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <CalendarRange class="size-4 text-brand-accent" />
            <h1 class="text-base font-bold">ガントチャート</h1>
            <div
                data-gantt-legend
                class="ml-auto flex items-center gap-3 text-sm text-muted-foreground"
            >
                <Button variant="ghost" onclick={exportPdf} title="PDF出力">
                    <FileDown data-icon="inline-start" />
                    PDF出力
                </Button>
                {#if !channelHeaderTitle}
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-gantt-channel"
                        ></span>
                        チャンネル
                    </span>
                {/if}
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-gantt-task"></span>
                    タスク
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-gantt-complete"></span>
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
                    <div class="border-r border-border/70"></div>
                    {#each Array.from( { length: dayCount } ) as _, dayIndex (dayIndex)}
                        <div
                            class="border-l border-border/70"
                            style={`grid-column: ${dayIndex + 2};`}
                        ></div>
                    {/each}
                </div>

                {#if today >= rangeStart && today <= rangeEnd}
                    <div
                        class="pointer-events-none absolute inset-x-0 top-12 bottom-0 z-10 grid"
                        style={`grid-template-columns: 260px repeat(${dayCount}, minmax(24px, 1fr));`}
                        aria-hidden="true"
                    >
                        <div
                            class="h-full w-0 justify-self-center border-l-2 border-gantt-today-marker"
                            style={`grid-column: ${today - rangeStart + 2};`}
                        ></div>
                    </div>
                {/if}

                <!-- Header row: blank + ticks -->
                <div
                    data-gantt-grid-header
                    class="sticky left-0 top-0 z-20 flex h-12 items-center border-b border-r border-border bg-card px-3 text-sm font-semibold text-foreground"
                    style="grid-column: 1; grid-row: 1;"
                >
                    {#if channelHeaderTitle}
                        <span class="flex items-center gap-1">
                            <Hash class="size-4" />
                            {channelHeaderTitle}
                        </span>
                    {:else}
                        タスク
                    {/if}
                </div>
                {#each ticks as day (day)}
                    <div
                        class={cn(
                            'sticky top-0 z-10 flex h-12 items-center justify-center whitespace-nowrap border-b border-l border-border bg-card px-1 text-sm font-semibold text-muted-foreground',
                            day === today &&
                                'flex-col gap-0.5 bg-brand/15 py-1 text-brand-accent',
                            day !== today && day % 7 === 0 && 'bg-muted/50',
                        )}
                        style={`grid-column: ${day - rangeStart + 2}; grid-row: 1;`}
                        title={day === today ? '今日' : undefined}
                    >
                        {#if day === today}
                            <span
                                class="rounded-md border border-brand-accent bg-background px-1.5 py-0.5 text-[11px] font-semibold leading-none text-brand-accent"
                            >
                                今日
                            </span>
                        {/if}
                        <span>{formatTick(day)}</span>
                    </div>
                {/each}

                <!-- Task rows -->
                {#each displayTasks as task, taskIndex (task.id)}
                    <div
                        class="sticky left-0 z-10 flex min-h-10 items-center gap-2 border-b border-r border-border/70 bg-background px-3"
                        style={`grid-column: 1; grid-row: ${taskIndex + 2};`}
                    >
                        <Link
                            href={`/servers/${server.id}/channels/${task.channel_id}`}
                            data-gantt-task-label
                            class={cn(
                                'truncate text-sm font-medium text-foreground hover:underline',
                                task.completed && 'text-gantt-complete',
                            )}
                        >
                            {#if channelHeaderTitle && task.type === 'channel'}
                                <span class="sr-only">{task.title}</span>
                            {:else if task.type === 'channel'}
                                <span class="flex items-center gap-1">
                                    <Hash
                                        class="size-4 shrink-0 text-muted-foreground"
                                    />
                                    <span class="truncate">{task.title}</span>
                                </span>
                            {:else if task.completed}
                                <span class="flex items-center gap-1">
                                    <CheckCircle2 class="size-4 shrink-0" />
                                    <span class="truncate">{task.title}</span>
                                </span>
                            {:else}
                                {task.title}
                            {/if}
                        </Link>
                    </div>
                    {#if task.start !== null && task.end !== null}
                        <div
                            class="relative flex min-h-10 items-center border-b border-l border-border/70"
                            style={`grid-column: ${gridColumn(task.start, task.end, rangeStart, dayCount)}; grid-row: ${taskIndex + 2};`}
                        >
                            <div
                                data-gantt-bar
                                data-task-type={task.type}
                                data-task-completed={task.completed
                                    ? 'true'
                                    : 'false'}
                                class={`mx-1 h-6 w-full min-w-0 truncate rounded-md px-2 text-xs font-semibold leading-6 ${barClass(task)}`}
                                title={`${task.title} (${formatRange(task.start)} 〜 ${formatRange(task.end)})`}
                            >
                                {task.title}
                            </div>
                        </div>
                    {:else}
                        <div
                            class="min-h-10 border-b border-l border-border/70"
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
        canManage={isProjectAdministrator(
            server,
            members,
            page.props.auth?.user?.id,
        )}
        onUpdated={(updated) => (server = { ...server, ...updated })}
        onMembersUpdated={(updated) => (members = updated)}
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

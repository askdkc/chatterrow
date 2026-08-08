<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import {
        Hash,
        ListTodo,
        CalendarDays,
        Clock3,
        Flag,
        CheckCircle2,
        Circle,
        User,
        ArrowLeft,
    } from 'lucide-svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import TodoDialog from '@/components/discord/TodoDialog.svelte';
    import { formatDate, formatDateTime } from '@/lib/dates';
    import { t } from '@/lib/i18n';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import { priorityLabel } from '@/lib/todos';
    import type {
        ServerResource,
        ChannelResource,
        TodoResource,
        TodoWithChannelSummaryResource,
        UserResource,
    } from '@/types';

    let {
        server,
        channels,
        todos,
        members,
    }: {
        server: ServerResource;
        channels: ChannelResource[];
        todos: TodoWithChannelSummaryResource[];
        members: UserResource[];
    } = $props();

    const page = usePage();

    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );

    let showMemberDialog = $state(false);
    let editingTodo = $state<TodoResource | null>(null);

    function onAddServer() {
        window.location.href = '/servers';
    }

    function onBrowse() {
        window.location.href = '/servers';
    }

    function updateTodo(updated: TodoResource) {
        todos = todos.map((todo) =>
            todo.id === updated.id
                ? { ...todo, ...updated, channel: todo.channel }
                : todo,
        );
    }

    function openTodoFromClick(event: MouseEvent, todo: TodoResource) {
        if ((event.target as HTMLElement).closest('a')) {
            return;
        }

        editingTodo = todo;
    }

    function todosForChannel(channelId: number) {
        return todos.filter((todo) => todo.channel_id === channelId);
    }

    function channelDateRange(channel: ChannelResource): string {
        return t('Date range: :start - :end', {
            start: channel.starts_on
                ? formatDate(channel.starts_on)
                : t('Channel start date undecided'),
            end: channel.ends_on
                ? formatDate(channel.ends_on)
                : t('Channel end date undecided'),
        });
    }

    function handleTodoKeydown(event: KeyboardEvent, todo: TodoResource) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            editingTodo = todo;
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
        onManageMembers={() => (showMemberDialog = true)}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-y-auto">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b border-black/10 bg-[#313338] px-4 dark:border-black/20"
        >
            <Link
                href={`/servers/${server.id}`}
                class="rounded p-1 transition hover:bg-white/10"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <ListTodo class="h-4 w-4 text-[#5865f2]" />
            <h1 class="text-[15px] font-bold">{t('Task list')}</h1>
            <span class="ml-auto text-sm font-semibold text-[#4e5058]">
                {t('Incomplete tasks: :incomplete / :total', {
                    incomplete: String(
                        todos.filter((todo) => !todo.completed_at).length,
                    ),
                    total: String(todos.length),
                })}
            </span>
        </header>

        <div class="flex-1 space-y-6 p-6">
            <section>
                <h2
                    class="mb-2 text-xs font-bold uppercase tracking-wide text-[#80848e]"
                >
                    {t('Channels (tasks)')}
                </h2>
                {#if channels.length === 0}
                    <p
                        class="rounded-lg bg-[#2b2d31] p-6 text-center text-sm text-[#80848e]"
                    >
                        {t('No channels')}
                    </p>
                {:else}
                    <div class="space-y-4">
                        {#each channels as channel (channel.id)}
                            <div>
                                <div
                                    class="flex items-center gap-3 rounded-lg bg-[#dbeafe] p-3 text-[#1e3a8a] transition hover:bg-[#bfdbfe] dark:bg-[#243b5a] dark:text-[#dbeafe] dark:hover:bg-[#2f4d73]"
                                >
                                    <Hash
                                        class="h-4 w-4 shrink-0 text-[#80848e]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <Link
                                            href={`/servers/${server.id}/channels/${channel.id}`}
                                            class="font-medium hover:underline"
                                        >
                                            {channel.name}
                                        </Link>
                                        {#if channel.description}
                                            <p
                                                class="truncate text-xs text-[#80848e]"
                                            >
                                                {channel.description}
                                            </p>
                                        {/if}
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-4 text-xs text-[#80848e]"
                                    >
                                        <span class="flex items-center gap-1">
                                            <CalendarDays class="h-3.5 w-3.5" />
                                            {channelDateRange(channel)}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <ListTodo class="h-3.5 w-3.5" />
                                            {channel.open_todos_count ??
                                                0}/{channel.todos_count ?? 0}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 ml-8 space-y-2">
                                    {#each todosForChannel(channel.id) as todo (todo.id)}
                                        <div
                                            class="flex cursor-pointer items-center gap-3 rounded-lg bg-[#383a40] px-3 py-2 transition hover:bg-[#404249]"
                                            class:opacity-60={Boolean(
                                                todo.completed_at,
                                            )}
                                            role="button"
                                            tabindex="0"
                                            aria-label={t('Edit :name', {
                                                name: todo.title,
                                            })}
                                            onclick={(event) =>
                                                openTodoFromClick(event, todo)}
                                            onkeydown={(event) =>
                                                handleTodoKeydown(event, todo)}
                                        >
                                            {#if todo.completed_at}
                                                <CheckCircle2
                                                    class="h-4 w-4 shrink-0 text-[#23a559]"
                                                />
                                            {:else}
                                                <Circle
                                                    class="h-4 w-4 shrink-0 text-[#80848e]"
                                                />
                                            {/if}
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate text-sm font-medium"
                                                    class:line-through={Boolean(
                                                        todo.completed_at,
                                                    )}
                                                >
                                                    {todo.title}
                                                </p>
                                                {#if todo.details}
                                                    <p
                                                        class="mt-0.5 line-clamp-1 text-xs text-[#80848e]"
                                                    >
                                                        {todo.details}
                                                    </p>
                                                {/if}
                                            </div>
                                            <div
                                                class="flex min-w-0 shrink-0 flex-wrap items-center justify-end gap-2 text-sm font-medium text-[#4e5058] dark:text-[#b5bac1]"
                                            >
                                                {#if todo.starts_at}
                                                    <span
                                                        class="flex items-center gap-1 rounded-md bg-white/60 px-2 py-1 dark:bg-white/10"
                                                    >
                                                        <Clock3
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span
                                                            class="text-xs text-[#6a6f78] dark:text-[#949ba4]"
                                                            >{t('Start')}</span
                                                        >
                                                        {formatDateTime(
                                                            todo.starts_at,
                                                        )}
                                                    </span>
                                                {/if}
                                                {#if todo.due_at}
                                                    <span
                                                        class="flex items-center gap-1 rounded-md bg-white/60 px-2 py-1 dark:bg-white/10"
                                                    >
                                                        <CalendarDays
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span
                                                            class="text-xs text-[#6a6f78] dark:text-[#949ba4]"
                                                            >{t(
                                                                'Deadline',
                                                            )}</span
                                                        >
                                                        {formatDateTime(
                                                            todo.due_at,
                                                        )}
                                                    </span>
                                                {/if}
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <Flag class="h-3.5 w-3.5" />
                                                    {priorityLabel(
                                                        todo.priority,
                                                    )}
                                                </span>
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <User class="h-3.5 w-3.5" />
                                                    {todo.assignee?.name ??
                                                        t('Unassigned')}
                                                </span>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </section>
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

{#if editingTodo}
    <TodoDialog
        serverId={server.id}
        channelId={editingTodo.channel_id}
        todo={editingTodo}
        onUpdated={updateTodo}
        onClose={() => (editingTodo = null)}
    />
{/if}

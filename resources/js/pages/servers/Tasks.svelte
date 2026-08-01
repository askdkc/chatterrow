<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        Hash,
        ListTodo,
        CalendarDays,
        CheckCircle2,
        Circle,
        User,
        ArrowLeft,
    } from 'lucide-svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import type {
        ServerResource,
        ChannelResource,
        TodoResource,
        UserResource,
    } from '@/types';

    let {
        server,
        channels,
        todos,
        members,
        authServers,
    }: {
        server: ServerResource;
        channels: ChannelResource[];
        todos: (TodoResource & { channel: { id: number; name: string } })[];
        members: UserResource[];
        authServers: ServerResource[];
    } = $props();

    let showMemberDialog = $state(false);

    function onAddServer() {
        window.location.href = '/servers';
    }

    function onBrowse() {
        window.location.href = '/servers';
    }

    function onAddChannel() {}

    function formatDue(iso: string | null): string {
        if (!iso) {
            return '未設定';
        }

        return new Date(iso).toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
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
        {onAddChannel}
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
            <h1 class="text-[15px] font-bold">タスク一覧</h1>
            <span class="ml-auto text-xs text-[#80848e]">
                {todos.filter((t) => !t.completed_at).length} 未完了 / {todos.length}
                件
            </span>
        </header>

        <div class="flex-1 space-y-6 p-6">
            <!-- Channels as tasks -->
            <section>
                <h2
                    class="mb-2 text-xs font-bold uppercase tracking-wide text-[#80848e]"
                >
                    チャンネル（タスク）
                </h2>
                <div class="space-y-2">
                    {#each channels as channel (channel.id)}
                        <div
                            class="flex items-center gap-3 rounded-lg bg-[#2b2d31] p-3 transition hover:bg-[#383a40]"
                        >
                            <Hash class="h-4 w-4 shrink-0 text-[#80848e]" />
                            <div class="min-w-0 flex-1">
                                <Link
                                    href={`/servers/${server.id}/channels/${channel.id}`}
                                    class="font-medium hover:underline"
                                >
                                    {channel.name}
                                </Link>
                                {#if channel.description}
                                    <p class="truncate text-xs text-[#80848e]">
                                        {channel.description}
                                    </p>
                                {/if}
                            </div>
                            <div
                                class="flex shrink-0 items-center gap-4 text-xs text-[#80848e]"
                            >
                                <span class="flex items-center gap-1">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {channel.starts_on
                                        ? formatDue(channel.starts_on)
                                        : '開始未定'} 〜
                                    {channel.ends_on
                                        ? formatDue(channel.ends_on)
                                        : '期限未定'}
                                </span>
                                <span class="flex items-center gap-1">
                                    <ListTodo class="h-3.5 w-3.5" />
                                    {channel.open_todos_count ??
                                        0}/{channel.todos_count ?? 0}
                                </span>
                            </div>
                        </div>
                    {/each}
                </div>
            </section>

            <!-- All todos across channels -->
            <section>
                <h2
                    class="mb-2 text-xs font-bold uppercase tracking-wide text-[#80848e]"
                >
                    全タスク
                </h2>
                {#if todos.length === 0}
                    <p
                        class="rounded-lg bg-[#2b2d31] p-6 text-center text-sm text-[#80848e]"
                    >
                        タスクがありません。チャンネル内で todo
                        を作成してください。
                    </p>
                {:else}
                    <div class="space-y-2">
                        {#each todos as todo (todo.id)}
                            <div
                                class="flex items-center gap-3 rounded-lg bg-[#2b2d31] p-3 transition hover:bg-[#383a40]"
                                class:opacity-60={todo.completed_at !== null}
                            >
                                {#if todo.completed_at}
                                    <CheckCircle2
                                        class="h-5 w-5 shrink-0 text-[#23a559]"
                                    />
                                {:else}
                                    <Circle
                                        class="h-5 w-5 shrink-0 text-[#80848e]"
                                    />
                                {/if}
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium"
                                        class:line-through={todo.completed_at !==
                                            null}
                                    >
                                        {todo.title}
                                    </p>
                                    <Link
                                        href={`/servers/${server.id}/channels/${todo.channel.id}`}
                                        class="text-xs text-[#5865f2] hover:underline"
                                    >
                                        #{todo.channel.name}
                                    </Link>
                                </div>
                                <div
                                    class="flex shrink-0 items-center gap-4 text-xs text-[#80848e]"
                                >
                                    {#if todo.due_on}
                                        <span class="flex items-center gap-1">
                                            <CalendarDays class="h-3.5 w-3.5" />
                                            {formatDue(todo.due_on)}
                                        </span>
                                    {/if}
                                    <span class="flex items-center gap-1">
                                        <User class="h-3.5 w-3.5" />
                                        {todo.assignee?.name ?? '未割当'}
                                    </span>
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
        onClose={() => (showMemberDialog = false)}
    />
{/if}

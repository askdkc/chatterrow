<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        Hash,
        Plus,
        Users,
        ListTodo,
        CalendarRange,
        FileText,
        ChevronDown,
        Settings,
    } from 'lucide-svelte';
    import type {
        ChannelResource,
        ServerResource,
        UserResource,
    } from '@/types';

    let {
        server,
        channels,
        members,
        activeChannelId,
        onAddChannel,
        onManageMembers,
    }: {
        server: ServerResource;
        channels: ChannelResource[];
        members: UserResource[];
        activeChannelId: number | null;
        onAddChannel: () => void;
        onManageMembers: () => void;
    } = $props();
</script>

<aside
    class="flex w-60 shrink-0 flex-col bg-[#2b2d31] text-[#949ba4] dark:bg-[#2b2d31] light:bg-[#f2f3f5]"
    aria-label="チャンネル一覧"
>
    <button
        type="button"
        class="flex h-12 items-center justify-between border-b border-black/10 px-4 text-[15px] font-semibold text-[#dbdee1] shadow-sm transition hover:bg-white/5 dark:border-black/20"
        onclick={onManageMembers}
    >
        <span class="truncate">{server.name}</span>
        <Settings class="h-4 w-4 opacity-60" />
    </button>

    <div class="flex-1 overflow-y-auto py-3">
        <div class="mb-1 flex items-center justify-between px-3">
            <span
                class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide"
            >
                <ChevronDown class="h-3 w-3" />
                テキストチャンネル
            </span>
            <button
                type="button"
                class="rounded p-1 transition hover:bg-white/10 hover:text-white"
                onclick={onAddChannel}
                title="チャンネルを作成"
            >
                <Plus class="h-4 w-4" />
            </button>
        </div>

        {#each channels as channel (channel.id)}
            <Link
                href={`/servers/${server.id}/channels/${channel.id}`}
                class={`group mx-2 mb-0.5 flex items-center gap-2 rounded-md px-2 py-1.5 text-[15px] font-medium transition hover:bg-white/10 hover:text-[#dbdee1] ${
                    channel.id === activeChannelId
                        ? 'bg-white/10 text-[#dbdee1]'
                        : ''
                }`}
            >
                <Hash class="h-5 w-5 shrink-0 opacity-70" />
                <span class="truncate">{channel.name}</span>
                {#if channel.starts_on || channel.ends_on}
                    <span
                        class="ml-auto h-2 w-2 shrink-0 rounded-full bg-[#f0b232]"
                        title="タスク期間設定あり"
                    ></span>
                {/if}
            </Link>
        {/each}

        {#if channels.length === 0}
            <p class="px-4 py-2 text-sm text-[#80848e]">
                チャンネルがありません
            </p>
        {/if}
    </div>

    <!-- Server utility links -->
    <div class="border-t border-black/10 px-2 py-2 dark:border-black/20">
        <Link
            href={`/servers/${server.id}/tasks`}
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[15px] font-medium transition hover:bg-white/10 hover:text-[#dbdee1]"
        >
            <ListTodo class="h-5 w-5" />
            タスク一覧
        </Link>
        <Link
            href={`/servers/${server.id}/gantt`}
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[15px] font-medium transition hover:bg-white/10 hover:text-[#dbdee1]"
        >
            <CalendarRange class="h-5 w-5" />
            ガントチャート
        </Link>
        <Link
            href={`/servers/${server.id}/files`}
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[15px] font-medium transition hover:bg-white/10 hover:text-[#dbdee1]"
        >
            <FileText class="h-5 w-5" />
            ファイル
        </Link>
    </div>

    <!-- Member list footer -->
    <div class="border-t border-black/10 px-4 py-2 dark:border-black/20">
        <div
            class="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide"
        >
            <Users class="h-3.5 w-3.5" />
            メンバー {members.length}
        </div>
        <div class="flex flex-wrap gap-1">
            {#each members as member (member.id)}
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-[#5865f2] text-[11px] font-bold text-white"
                    title={member.name}
                >
                    {member.name.slice(0, 1).toUpperCase()}
                </span>
            {/each}
        </div>
    </div>
</aside>

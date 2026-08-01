<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Plus, Hash, Users, CalendarRange } from 'lucide-svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import type { ServerResource } from '@/types';

    let {
        servers,
        authServers,
    }: {
        servers: ServerResource[];
        authServers: ServerResource[];
    } = $props();

    let showServerDialog = $state(false);

    function onAddServer() {
        showServerDialog = true;
    }

    function onBrowse() {
        router.visit('/servers');
    }
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={authServers}
        activeServerId={null}
        {onAddServer}
        {onBrowse}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-y-auto">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center justify-between border-b border-black/10 bg-[#313338] px-6 dark:border-black/20"
        >
            <h1 class="text-[15px] font-bold">サーバー一覧</h1>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-md bg-[#5865f2] px-3 py-1.5 text-sm font-medium text-white transition hover:bg-[#4752c4]"
                onclick={onAddServer}
            >
                <Plus class="h-4 w-4" />
                サーバーを作成
            </button>
        </header>

        <div class="mx-auto w-full max-w-3xl flex-1 p-6">
            {#if servers.length === 0}
                <div
                    class="flex flex-col items-center justify-center py-24 text-center"
                >
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#5865f2]/20"
                    >
                        <Hash class="h-8 w-8 text-[#5865f2]" />
                    </div>
                    <h2 class="text-lg font-bold">まだサーバーがありません</h2>
                    <p class="mt-1 text-sm text-[#80848e]">
                        サーバーを作成して、チームのチャットとタスクを始めましょう
                    </p>
                    <button
                        type="button"
                        class="mt-4 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4]"
                        onclick={onAddServer}
                    >
                        サーバーを作成
                    </button>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each servers as server (server.id)}
                        <button
                            type="button"
                            class="flex w-full items-center gap-4 rounded-xl bg-[#2b2d31] p-4 text-left transition hover:bg-[#383a40]"
                            onclick={() =>
                                router.visit(`/servers/${server.id}`)}
                        >
                            <span
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#5865f2] text-base font-bold text-white"
                            >
                                {server.name.slice(0, 2).toUpperCase()}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">
                                    {server.name}
                                </p>
                                {#if server.description}
                                    <p
                                        class="mt-0.5 truncate text-sm text-[#80848e]"
                                    >
                                        {server.description}
                                    </p>
                                {/if}
                                <div
                                    class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-[#80848e]"
                                >
                                    <span class="flex items-center gap-1">
                                        <Hash class="h-3 w-3" />
                                        チャンネル {server.channels_count ?? 0}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Users class="h-3 w-3" />
                                        メンバー {server.members_count ?? 0}
                                    </span>
                                    {#if server.starts_on || server.ends_on}
                                        <span class="flex items-center gap-1">
                                            <CalendarRange class="h-3 w-3" />
                                            {server.starts_on ?? '?'} 〜 {server.ends_on ??
                                                '未定'}
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        </button>
                    {/each}
                </div>
            {/if}
        </div>
    </main>
</div>

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Plus, Settings } from 'lucide-svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import type { ServerResource } from '@/types';

    let {
        servers,
        activeServerId,
        onAddServer,
        onBrowse,
    }: {
        servers: ServerResource[];
        activeServerId: number | null;
        onAddServer: () => void;
        onBrowse: () => void;
    } = $props();

    let collapsed = $state(true);

    function expandRail() {
        collapsed = false;
    }

    function collapseRail() {
        collapsed = true;
    }
</script>

<nav
    class={`flex h-full shrink-0 flex-col gap-2 overflow-y-auto bg-[#1e1f22] py-3 transition-[width] duration-200 dark:bg-[#1e1f22] light:bg-[#e3e5e8] ${collapsed ? 'w-[72px] items-center' : 'w-60 items-stretch'}`}
    aria-label="プロジェクト一覧"
    onmouseenter={expandRail}
    onmouseleave={collapseRail}
>
    <!-- Home / browse -->
    <button
        type="button"
        class={`group relative mx-2 flex h-10 items-center rounded-2xl text-[#dbdee1] transition-all hover:rounded-xl ${collapsed ? 'w-12 justify-center' : 'justify-start gap-2 px-3'} ${!activeServerId ? 'bg-white/80 shadow-sm dark:bg-[#5865f2] dark:text-white' : 'hover:bg-white/70 dark:hover:bg-[#5865f2] dark:hover:text-white'}`}
        class:rounded-xl={!activeServerId}
        onclick={onBrowse}
        title="プロジェクト一覧"
    >
        <AppLogoIcon class="size-8 rounded-lg" />
        {#if !collapsed}
            <span class="truncate text-sm font-semibold">プロジェクト一覧</span>
        {/if}
    </button>

    <div
        class={`mx-4 rounded-full bg-white/20 ${collapsed ? 'h-0.5 w-8' : 'h-px w-auto'}`}
    ></div>

    {#each servers as server (server.id)}
        <Link
            href={`/servers/${server.id}`}
            class={`group relative mx-2 flex h-12 items-center rounded-2xl transition-all hover:rounded-xl ${collapsed ? 'w-12 justify-center' : 'w-auto gap-3 px-3'} ${server.id === activeServerId ? 'bg-white/80 shadow-sm dark:bg-white/10' : 'hover:bg-white/60 dark:hover:bg-white/5'}`}
        >
            <span
                class="absolute -left-3 h-2 w-2 rounded-full bg-white transition-all"
                class:opacity-100={server.id === activeServerId}
                class:opacity-0={server.id !== activeServerId}
            ></span>
            <span
                class={`flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl text-sm font-bold transition-all group-hover:rounded-xl ${server.id === activeServerId ? 'rounded-xl bg-white text-[#4e5058] dark:bg-[#5865f2] dark:text-white' : 'bg-[#313338] text-[#dbdee1] group-hover:bg-[#dfe1e5] group-hover:text-[#2e3338] dark:group-hover:bg-[#404249] dark:group-hover:text-[#dbdee1]'}`}
                title={server.name}
            >
                {collapsed
                    ? server.name.slice(0, 2).toUpperCase()
                    : server.name.slice(0, 1).toUpperCase()}
            </span>
            {#if !collapsed}
                <span
                    class="min-w-0 truncate text-sm font-medium text-[#dbdee1]"
                >
                    {server.name}
                </span>
            {/if}
        </Link>
    {/each}

    <button
        type="button"
        class={`group relative flex h-9 items-center rounded-lg bg-[#313338] text-[#23a559] transition-all hover:bg-[#23a559] hover:text-white ${collapsed ? 'w-12 justify-center self-center' : 'w-48 justify-center gap-1.5 self-center px-3'}`}
        onclick={onAddServer}
        title="プロジェクトを作成"
    >
        <Plus class="h-5 w-5" />
        {#if !collapsed}
            <span class="text-sm font-medium">プロジェクトを作成</span>
        {/if}
    </button>

    <div class="mt-auto w-full">
        <a
            href="/settings/profile"
            class={`mx-2 flex h-12 items-center rounded-2xl bg-[#313338] text-[#dbdee1] transition-all hover:rounded-xl hover:bg-white/70 dark:hover:bg-[#5865f2] dark:hover:text-white ${collapsed ? 'w-12 justify-center' : 'justify-start gap-2 px-3'}`}
            title="設定"
        >
            <Settings class="h-6 w-6" />
            {#if !collapsed}
                <span class="text-sm font-medium">設定</span>
            {/if}
        </a>
    </div>
</nav>

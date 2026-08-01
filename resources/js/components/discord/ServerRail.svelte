<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import { Plus, Settings, Compass } from 'lucide-svelte';
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

    const page = usePage();
</script>

<nav
    class="flex h-full w-[72px] shrink-0 flex-col items-center gap-2 overflow-y-auto bg-[#1e1f22] py-3 dark:bg-[#1e1f22] light:bg-[#e3e5e8]"
    aria-label="サーバー一覧"
>
    <!-- Home / browse -->
    <button
        type="button"
        class="group relative flex h-12 w-12 items-center justify-center rounded-2xl text-[#dbdee1] transition-all hover:rounded-xl hover:bg-[#5865f2]"
        class:bg-[#5865f2]={!activeServerId}
        class:rounded-xl={!activeServerId}
        onclick={onBrowse}
        title="サーバー一覧"
    >
        <Compass class="h-6 w-6" />
    </button>

    <div class="h-0.5 w-8 rounded-full bg-white/20"></div>

    {#each servers as server (server.id)}
        <Link
            href={`/servers/${server.id}`}
            class={`group relative flex h-12 w-12 items-center justify-center rounded-2xl transition-all hover:rounded-xl ${
                server.id === activeServerId ? 'bg-[#5865f2]' : ''
            }`}
        >
            <span
                class="absolute -left-3 h-2 w-2 rounded-full bg-white transition-all"
                class:opacity-100={server.id === activeServerId}
                class:opacity-0={server.id !== activeServerId}
            ></span>
            <span
                class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-[#313338] text-sm font-bold text-[#dbdee1] transition-all group-hover:rounded-xl group-hover:bg-[#5865f2]"
                class:rounded-xl={server.id === activeServerId}
                class:bg-[#5865f2]={server.id === activeServerId}
                title={server.name}
            >
                {server.name.slice(0, 2).toUpperCase()}
            </span>
        </Link>
    {/each}

    <button
        type="button"
        class="group relative flex h-12 w-12 items-center justify-center rounded-2xl bg-[#313338] text-[#23a559] transition-all hover:rounded-xl hover:bg-[#23a559] hover:text-white"
        onclick={onAddServer}
        title="サーバーを作成"
    >
        <Plus class="h-6 w-6" />
    </button>

    <div class="mt-auto">
        <a
            href="/settings/profile"
            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#313338] text-[#dbdee1] transition-all hover:rounded-xl hover:bg-[#5865f2]"
            title="設定"
        >
            <Settings class="h-6 w-6" />
        </a>
    </div>
</nav>

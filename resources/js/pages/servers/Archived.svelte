<script lang="ts">
    import { router, usePage } from '@inertiajs/svelte';
    import Archive from 'lucide-svelte/icons/archive';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Hash from 'lucide-svelte/icons/hash';
    import Settings from 'lucide-svelte/icons/settings';
    import Users from 'lucide-svelte/icons/users';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ProjectIcon from '@/components/discord/ProjectIcon.svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type { ServerResource } from '@/types';

    let { servers }: { servers: ServerResource[] } = $props();

    const page = usePage();
    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );
    const currentUserId = $derived(page.props.auth?.user?.id ?? null);

    let editingServer = $state<ServerResource | null>(null);
    let showServerDialog = $state(false);

    function onBrowse() {
        router.visit('/servers');
    }
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={authServers}
        activeServerId={null}
        onAddServer={() => (showServerDialog = true)}
        {onBrowse}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-y-auto">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b border-black/10 bg-[#313338] px-6 dark:border-black/20"
        >
            <Button variant="ghost" size="icon" asChild>
                {#snippet children(props)}
                    <a
                        class={props.class}
                        href="/servers"
                        aria-label="プロジェクト一覧へ戻る"
                    >
                        <ArrowLeft />
                    </a>
                {/snippet}
            </Button>
            <h1 class="text-[15px] font-bold">アーカイブ済みプロジェクト</h1>
            <Badge variant="secondary">{servers.length}</Badge>
        </header>

        <div class="mx-auto w-full max-w-3xl flex-1 p-6">
            {#if servers.length === 0}
                <div
                    class="flex flex-col items-center justify-center py-24 text-center"
                >
                    <div
                        class="mb-4 flex size-16 items-center justify-center rounded-full bg-muted"
                    >
                        <Archive class="size-8 text-muted-foreground" />
                    </div>
                    <h2 class="text-lg font-bold">
                        アーカイブ済みプロジェクトはありません
                    </h2>
                    <p class="mt-1 text-sm text-[#80848e]">
                        アーカイブしたプロジェクトはここから復元または削除できます。
                    </p>
                    <Button class="mt-4" variant="outline" asChild>
                        {#snippet children(props)}
                            <a class={props.class} href="/servers">
                                <ArrowLeft data-icon="inline-start" />
                                プロジェクト一覧へ戻る
                            </a>
                        {/snippet}
                    </Button>
                </div>
            {:else}
                <div class="flex flex-col gap-3">
                    {#each servers as server (server.id)}
                        <div class="group relative">
                            <button
                                type="button"
                                class="flex w-full items-center gap-4 rounded-xl bg-[#2b2d31]/70 p-4 pr-14 text-left opacity-80 transition hover:bg-[#383a40] hover:opacity-100"
                                onclick={() =>
                                    router.visit(`/servers/${server.id}`)}
                            >
                                <ProjectIcon {server} class="grayscale-[35%]" />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <span class="truncate font-semibold">
                                            {server.name}
                                        </span>
                                        <Badge variant="outline">
                                            アーカイブ済み
                                        </Badge>
                                    </span>
                                    <span
                                        class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-[#80848e]"
                                    >
                                        <span class="flex items-center gap-1">
                                            <Hash class="size-3" />
                                            チャンネル {server.channels_count ??
                                                0}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <Users class="size-3" />
                                            メンバー {server.members_count ?? 0}
                                        </span>
                                    </span>
                                </span>
                            </button>

                            {#if isProjectAdministrator(server, server.members ?? [], currentUserId)}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
                                    aria-label={`${server.name}の設定`}
                                    title="復元または削除"
                                    onclick={(event) => {
                                        event.stopPropagation();
                                        editingServer = server;
                                    }}
                                >
                                    <Settings />
                                </Button>
                            {/if}
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </main>
</div>

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}

{#if editingServer}
    <MemberDialog
        server={editingServer}
        members={editingServer.members ?? []}
        canManage={isProjectAdministrator(
            editingServer,
            editingServer.members ?? [],
            currentUserId,
        )}
        onUpdated={(updated) => {
            servers = servers.map((server) =>
                server.id === updated.id
                    ? { ...server, ...updated, members: server.members }
                    : server,
            );
        }}
        onMembersUpdated={(members) => {
            servers = servers.map((server) =>
                server.id === editingServer?.id
                    ? { ...server, members, members_count: members.length }
                    : server,
            );
        }}
        onArchived={() => undefined}
        onRestored={(updated) => {
            servers = servers.filter((server) => server.id !== updated.id);
        }}
        onDeleted={(serverId) => {
            servers = servers.filter((server) => server.id !== serverId);
        }}
        onClose={() => (editingServer = null)}
    />
{/if}

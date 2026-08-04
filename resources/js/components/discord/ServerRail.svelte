<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import { Plus, Settings } from 'lucide-svelte';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import NotificationButton from '@/components/discord/NotificationButton.svelte';
    import ProjectFolderIcon from '@/components/discord/ProjectFolderIcon.svelte';
    import ProjectIcon from '@/components/discord/ProjectIcon.svelte';
    import {
        Collapsible,
        CollapsibleContent,
        CollapsibleTrigger,
    } from '@/components/ui/collapsible';
    import { mentionNotificationsState } from '@/lib/mention-notifications.svelte';
    import type { ProjectFolderResource, ServerResource } from '@/types';

    let {
        servers,
        folders,
        activeServerId,
        onAddServer,
        onBrowse,
    }: {
        servers: ServerResource[];
        folders?: ProjectFolderResource[];
        activeServerId: number | null;
        onAddServer: () => void;
        onBrowse: () => void;
    } = $props();

    let railHovered = $state(false);
    let notificationOpen = $state(false);
    let openFolderId = $state<number | null>(null);
    const page = usePage();
    const notificationState = mentionNotificationsState();
    const currentUserId = $derived(page.props.auth?.user?.id);
    const collapsed = $derived(!railHovered && !notificationOpen);
    const visibleFolders = $derived(folders ?? page.props.auth?.folders ?? []);
    const folderGroups = $derived(
        visibleFolders.map((folder) => ({
            folder,
            servers: servers.filter(
                (server) => server.project_folder_id === folder.id,
            ),
        })),
    );
    const groupedServerIds = $derived(
        new Set(
            folderGroups.flatMap((group) =>
                group.servers.map((server) => server.id),
            ),
        ),
    );
    const unfiledServers = $derived(
        servers.filter((server) => !groupedServerIds.has(server.id)),
    );

    $effect(() => {
        notificationState.initialize(currentUserId);
    });

    function expandRail() {
        railHovered = true;
    }

    function collapseRail() {
        railHovered = false;
        openFolderId = null;
    }

    function openFolder(folderId: number) {
        openFolderId = folderId;
    }

    function closeFolder(folderId: number) {
        if (openFolderId === folderId) {
            openFolderId = null;
        }
    }

    function toggleFolder(folderId: number) {
        railHovered = true;
        openFolderId = openFolderId === folderId ? null : folderId;
    }

    function folderHasActiveProject(groupServers: ServerResource[]) {
        return groupServers.some((server) => server.id === activeServerId);
    }

    function folderUnreadCount(groupServers: ServerResource[]) {
        return groupServers.reduce(
            (total, server) =>
                total + notificationState.getServerUnreadCount(server.id),
            0,
        );
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

    <NotificationButton {collapsed} bind:open={notificationOpen} />

    {#each folderGroups as group (group.folder.id)}
        <Collapsible open={openFolderId === group.folder.id && !collapsed}>
            <div
                data-project-folder={group.folder.id}
                role="group"
                aria-label={`${group.folder.name}フォルダ項目`}
                onmouseenter={() => openFolder(group.folder.id)}
                onmouseleave={() => closeFolder(group.folder.id)}
            >
                <CollapsibleTrigger
                    class={`group/folder relative mx-2 flex h-12 items-center rounded-2xl transition-all hover:rounded-xl ${collapsed ? 'w-12 justify-center' : 'w-auto gap-3 px-3'} ${folderHasActiveProject(group.servers) ? 'bg-white/80 shadow-sm dark:bg-white/10' : 'hover:bg-white/60 dark:hover:bg-white/5'}`}
                    aria-label={`${group.folder.name}フォルダ`}
                    aria-expanded={openFolderId === group.folder.id &&
                        !collapsed}
                    aria-controls={`project-folder-${group.folder.id}`}
                    title={group.folder.name}
                    onclick={() => toggleFolder(group.folder.id)}
                >
                    <ProjectFolderIcon
                        folder={group.folder}
                        size="rail"
                        class={folderHasActiveProject(group.servers)
                            ? 'rounded-xl ring-2 ring-white/70'
                            : ''}
                    />
                    {#if !collapsed}
                        <span
                            class="min-w-0 flex-1 truncate text-left text-sm font-medium text-[#dbdee1]"
                        >
                            {group.folder.name}
                        </span>
                        <span
                            class="text-xs tabular-nums text-[#949ba4]"
                            aria-label={`${group.servers.length}件`}
                        >
                            {group.servers.length}
                        </span>
                        <ChevronRight
                            class={`size-4 shrink-0 text-[#949ba4] transition-transform ${openFolderId === group.folder.id ? 'rotate-90' : ''}`}
                        />
                    {/if}
                    {#if folderUnreadCount(group.servers) > 0}
                        <span
                            class={`absolute flex min-w-4 items-center justify-center rounded-full bg-[#f0b232] px-1 text-[10px] font-bold leading-4 text-[#1e1f22] ${collapsed ? '-top-1 -right-1' : 'top-0.5 right-0.5'}`}
                            aria-label={`未読メンション ${folderUnreadCount(group.servers)}件`}
                        >
                            {folderUnreadCount(group.servers) > 99
                                ? '99+'
                                : folderUnreadCount(group.servers)}
                        </span>
                    {/if}
                </CollapsibleTrigger>

                {#if openFolderId === group.folder.id && !collapsed}
                    <CollapsibleContent
                        id={`project-folder-${group.folder.id}`}
                        class="mx-2 mt-1 flex flex-col gap-1 border-l border-white/10 pl-3"
                        role="group"
                        aria-label={`${group.folder.name}内のプロジェクト`}
                    >
                        {#if group.servers.length === 0}
                            <span class="px-2 py-2 text-xs text-[#949ba4]">
                                プロジェクトなし
                            </span>
                        {:else}
                            {#each group.servers as server (server.id)}
                                <Link
                                    href={`/servers/${server.id}`}
                                    class={`group relative flex h-10 items-center gap-2 rounded-lg px-2 transition-colors ${server.id === activeServerId ? 'bg-white/80 shadow-sm dark:bg-white/10' : 'hover:bg-white/60 dark:hover:bg-white/5'}`}
                                >
                                    <ProjectIcon
                                        {server}
                                        size="compact"
                                        initialsLength={1}
                                        class={server.id === activeServerId
                                            ? 'ring-2 ring-primary-foreground/60'
                                            : ''}
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm font-medium text-[#dbdee1]"
                                    >
                                        {server.name}
                                    </span>
                                    {#if notificationState.getServerUnreadCount(server.id) > 0}
                                        <span
                                            class="flex min-w-4 items-center justify-center rounded-full bg-[#f0b232] px-1 text-[10px] font-bold leading-4 text-[#1e1f22]"
                                            aria-label={`未読メンション ${notificationState.getServerUnreadCount(server.id)}件`}
                                        >
                                            {notificationState.getServerUnreadCount(
                                                server.id,
                                            ) > 99
                                                ? '99+'
                                                : notificationState.getServerUnreadCount(
                                                      server.id,
                                                  )}
                                        </span>
                                    {/if}
                                </Link>
                            {/each}
                        {/if}
                    </CollapsibleContent>
                {/if}
            </div>
        </Collapsible>
    {/each}

    {#each unfiledServers as server (server.id)}
        <Link
            href={`/servers/${server.id}`}
            class={`group relative mx-2 flex h-12 items-center rounded-2xl transition-all hover:rounded-xl ${collapsed ? 'w-12 justify-center' : 'w-auto gap-3 px-3'} ${server.id === activeServerId ? 'bg-white/80 shadow-sm dark:bg-white/10' : 'hover:bg-white/60 dark:hover:bg-white/5'}`}
        >
            <span
                class="absolute -left-3 h-2 w-2 rounded-full bg-white transition-all"
                class:opacity-100={server.id === activeServerId}
                class:opacity-0={server.id !== activeServerId}
            ></span>
            <ProjectIcon
                {server}
                size="rail"
                initialsLength={collapsed ? 2 : 1}
                class={server.id === activeServerId
                    ? 'rounded-xl ring-2 ring-primary-foreground/60'
                    : ''}
            />
            {#if !collapsed}
                <span
                    class="min-w-0 truncate text-sm font-medium text-[#dbdee1]"
                >
                    {server.name}
                </span>
            {/if}
            {#if notificationState.getServerUnreadCount(server.id) > 0}
                <span
                    class={`absolute flex min-w-4 items-center justify-center rounded-full bg-[#f0b232] px-1 text-[10px] font-bold leading-4 text-[#1e1f22] ${collapsed ? '-top-1 -right-1' : 'top-1 right-1'}`}
                    title="未読メンション"
                    aria-label={`未読メンション ${notificationState.getServerUnreadCount(server.id)}件`}
                >
                    {notificationState.getServerUnreadCount(server.id) > 99
                        ? '99+'
                        : notificationState.getServerUnreadCount(server.id)}
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

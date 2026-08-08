<script lang="ts">
    import { router, usePage } from '@inertiajs/svelte';
    import Archive from 'lucide-svelte/icons/archive';
    import MoreHorizontal from 'lucide-svelte/icons/ellipsis';
    import FolderPlus from 'lucide-svelte/icons/folder-plus';
    import Hash from 'lucide-svelte/icons/hash';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Users from 'lucide-svelte/icons/users';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ProjectCard from '@/components/discord/ProjectCard.svelte';
    import ProjectFolderDialog from '@/components/discord/ProjectFolderDialog.svelte';
    import ProjectFolderIcon from '@/components/discord/ProjectFolderIcon.svelte';
    import ProjectInvitationCard from '@/components/discord/ProjectInvitationCard.svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import * as AlertDialog from '@/components/ui/alert-dialog';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import * as DropdownMenu from '@/components/ui/dropdown-menu';
    import { currentLocale } from '@/lib/dates';
    import { apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type {
        ProjectFolderResource,
        ProjectInvitationResource,
        ServerResource,
    } from '@/types';

    let {
        servers,
        folders = [],
        archivedCount = 0,
        invitations = [],
    }: {
        servers: ServerResource[];
        folders?: ProjectFolderResource[];
        archivedCount?: number;
        invitations?: ProjectInvitationResource[];
    } = $props();

    const page = usePage();

    const currentUserId = $derived(page.props.auth?.user?.id ?? null);
    const activeServers = $derived(
        servers.filter((server) => !server.archived_at),
    );
    const folderGroups = $derived(
        folders.map((folder) => ({
            folder,
            servers: activeServers.filter(
                (server) => server.project_folder_id === folder.id,
            ),
        })),
    );
    const unfiledServers = $derived(
        activeServers.filter((server) => server.project_folder_id == null),
    );

    let showServerDialog = $state(false);
    let editingServer = $state<ServerResource | null>(null);
    let showFolderDialog = $state(false);
    let editingFolder = $state<ProjectFolderResource | null>(null);
    let deletingFolder = $state<ProjectFolderResource | null>(null);
    let folderActionPending = $state(false);
    let movingServerId = $state<number | null>(null);
    let draggingServerId = $state<number | null>(null);
    let dragOverDestination = $state<number | 'unfiled' | null>(null);
    let folderError = $state('');
    let currentInvitations = $state<ProjectInvitationResource[]>([]);
    let invitationsInitialized = false;

    $effect.pre(() => {
        if (!invitationsInitialized) {
            currentInvitations = [...invitations];
            invitationsInitialized = true;
        }
    });

    function removeInvitation(invitationId: number) {
        currentInvitations = currentInvitations.filter(
            (invitation) => invitation.id !== invitationId,
        );
    }

    function acceptedInvitation(invitationId: number) {
        removeInvitation(invitationId);
        router.reload({
            only: [
                'servers',
                'folders',
                'archivedCount',
                'invitations',
                'auth',
            ],
        });
    }

    function onAddServer() {
        showServerDialog = true;
    }

    function createFolder() {
        editingFolder = null;
        showFolderDialog = true;
    }

    function editFolder(folder: ProjectFolderResource) {
        editingFolder = folder;
        showFolderDialog = true;
    }

    function saveFolder(folder: ProjectFolderResource) {
        const existing = folders.some((item) => item.id === folder.id);

        folders = existing
            ? folders.map((item) => (item.id === folder.id ? folder : item))
            : [...folders, folder].sort(
                  (left, right) =>
                      left.position - right.position ||
                      left.name.localeCompare(right.name, currentLocale()),
              );
    }

    async function moveServer(serverId: number, folderId: number | null) {
        if (movingServerId !== null) {
            return;
        }

        movingServerId = serverId;
        folderError = '';

        try {
            await apiJson(`/servers/${serverId}/folder`, {
                method: 'PATCH',
                body: JSON.stringify({ project_folder_id: folderId }),
            });
            servers = servers.map((server) =>
                server.id === serverId
                    ? { ...server, project_folder_id: folderId }
                    : server,
            );
        } catch (exception) {
            folderError =
                exception instanceof HttpError
                    ? exception.messageText()
                    : t('Failed to move project');
        } finally {
            movingServerId = null;
        }
    }

    function startServerDrag(serverId: number) {
        draggingServerId = serverId;
        folderError = '';
    }

    function finishServerDrag() {
        draggingServerId = null;
        dragOverDestination = null;
    }

    function draggedServer(event: DragEvent): ServerResource | undefined {
        const transferredId = Number(
            event.dataTransfer?.getData(
                'application/x-chatterrow-project-id',
            ) || draggingServerId,
        );

        if (!Number.isInteger(transferredId)) {
            return undefined;
        }

        return activeServers.find((server) => server.id === transferredId);
    }

    function destinationKey(folderId: number | null): number | 'unfiled' {
        return folderId ?? 'unfiled';
    }

    function canDropServer(event: DragEvent, folderId: number | null) {
        const server = draggedServer(event);

        return (
            movingServerId === null &&
            server !== undefined &&
            (server.project_folder_id ?? null) !== folderId
        );
    }

    function dragServerOver(event: DragEvent, folderId: number | null) {
        if (!canDropServer(event, folderId)) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        dragOverDestination = destinationKey(folderId);
    }

    function leaveDropZone(event: DragEvent, folderId: number | null) {
        const currentTarget = event.currentTarget;
        const relatedTarget = event.relatedTarget;

        if (
            currentTarget instanceof HTMLElement &&
            relatedTarget instanceof Node &&
            currentTarget.contains(relatedTarget)
        ) {
            return;
        }

        if (dragOverDestination === destinationKey(folderId)) {
            dragOverDestination = null;
        }
    }

    function dropServer(event: DragEvent, folderId: number | null) {
        event.preventDefault();
        const server = draggedServer(event);
        const shouldMove = server && canDropServer(event, folderId);

        finishServerDrag();

        if (server && shouldMove) {
            void moveServer(server.id, folderId);
        }
    }

    async function deleteFolder() {
        if (!deletingFolder || folderActionPending) {
            return;
        }

        folderActionPending = true;
        folderError = '';

        try {
            await apiJson(`/project-folders/${deletingFolder.id}`, {
                method: 'DELETE',
            });
            const deletedFolderId = deletingFolder.id;
            folders = folders.filter((folder) => folder.id !== deletedFolderId);
            servers = servers.map((server) =>
                server.project_folder_id === deletedFolderId
                    ? { ...server, project_folder_id: null }
                    : server,
            );
            deletingFolder = null;
        } catch (exception) {
            folderError =
                exception instanceof HttpError
                    ? exception.messageText()
                    : t('Failed to delete folder');
        } finally {
            folderActionPending = false;
        }
    }

    function editServer(server: ServerResource) {
        editingServer = server;
        showServerDialog = false;
    }

    function onBrowse() {
        router.visit('/servers');
    }
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={activeServers}
        {folders}
        activeServerId={null}
        {onAddServer}
        {onBrowse}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-y-auto">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center justify-between border-b border-black/10 bg-[#313338] px-6 dark:border-black/20"
        >
            <h1 class="text-[15px] font-bold">{t('Project list')}</h1>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" onclick={createFolder}>
                    <FolderPlus data-icon="inline-start" />
                    {t('Create folder')}
                </Button>
                <Button size="sm" onclick={onAddServer}>
                    <Plus data-icon="inline-start" />
                    {t('Create project')}
                </Button>
            </div>
        </header>

        <div class="mx-auto w-full max-w-3xl flex-1 p-6 pb-24">
            {#if folderError}
                <p
                    class="mb-4 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    role="alert"
                >
                    {folderError}
                </p>
            {/if}

            {#if currentInvitations.length > 0}
                <section class="mb-8 flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <Users class="size-4" />
                        <h2 class="text-sm font-semibold">
                            {t('Project invitations')}
                        </h2>
                        <Badge variant="secondary">
                            {currentInvitations.length}
                        </Badge>
                    </div>
                    {#each currentInvitations as invitation (invitation.id)}
                        <ProjectInvitationCard
                            {invitation}
                            onAccepted={() => acceptedInvitation(invitation.id)}
                            onDeclined={removeInvitation}
                        />
                    {/each}
                </section>
            {/if}

            {#if activeServers.length === 0}
                <div
                    class="flex flex-col items-center justify-center py-24 text-center"
                >
                    <div
                        class="mb-4 flex size-16 items-center justify-center rounded-full bg-[#5865f2]/20"
                    >
                        <Hash class="size-8 text-[#5865f2]" />
                    </div>
                    <h2 class="text-lg font-bold">
                        {t('No projects yet')}
                    </h2>
                    <p class="mt-1 text-sm text-[#80848e]">
                        {t(
                            'Create a project to start chatting and managing tasks with your team',
                        )}
                    </p>
                    <Button class="mt-4" onclick={onAddServer}>
                        <Plus data-icon="inline-start" />
                        {t('Create project')}
                    </Button>
                </div>
            {:else}
                <div class="flex flex-col gap-7">
                    {#each folderGroups as group (group.folder.id)}
                        <section
                            class={`flex flex-col gap-3 rounded-2xl transition-[background-color,box-shadow] ${dragOverDestination === group.folder.id ? 'bg-[#5865f2]/10 ring-2 ring-[#5865f2]' : ''}`}
                            aria-label={t('Folder :name', {
                                name: group.folder.name,
                            })}
                            data-folder-drop-zone={group.folder.id}
                            ondragover={(event) =>
                                dragServerOver(event, group.folder.id)}
                            ondragleave={(event) =>
                                leaveDropZone(event, group.folder.id)}
                            ondrop={(event) =>
                                dropServer(event, group.folder.id)}
                        >
                            <div class="flex min-h-8 items-center gap-2 px-1">
                                <ProjectFolderIcon folder={group.folder} />
                                <h2
                                    class="min-w-0 truncate text-sm font-semibold"
                                >
                                    {group.folder.name}
                                </h2>
                                <Badge variant="secondary">
                                    {group.servers.length}
                                </Badge>
                                {#if dragOverDestination === group.folder.id}
                                    <span
                                        class="text-xs font-medium text-[#c9cdfb]"
                                    >
                                        {t('Drop here')}
                                    </span>
                                {/if}

                                <DropdownMenu.DropdownMenu class="ml-auto">
                                    <DropdownMenu.DropdownMenuTrigger asChild>
                                        {#snippet children(props)}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t(
                                                    'Actions for :name',
                                                    {
                                                        name: group.folder.name,
                                                    },
                                                )}
                                                onclick={props.onclick}
                                                aria-expanded={props[
                                                    'aria-expanded'
                                                ]}
                                                data-state={props['data-state']}
                                            >
                                                <MoreHorizontal />
                                            </Button>
                                        {/snippet}
                                    </DropdownMenu.DropdownMenuTrigger>
                                    <DropdownMenu.DropdownMenuContent
                                        align="end"
                                        class="w-44"
                                    >
                                        <DropdownMenu.DropdownMenuGroup>
                                            <DropdownMenu.DropdownMenuItem
                                                asChild
                                            >
                                                {#snippet children(props)}
                                                    <button
                                                        type="button"
                                                        class={props.class}
                                                        onclick={() => {
                                                            props.onClick?.();
                                                            editFolder(
                                                                group.folder,
                                                            );
                                                        }}
                                                    >
                                                        <Pencil
                                                            data-icon="inline-start"
                                                        />
                                                        {t('Edit folder')}
                                                    </button>
                                                {/snippet}
                                            </DropdownMenu.DropdownMenuItem>
                                            <DropdownMenu.DropdownMenuItem
                                                asChild
                                            >
                                                {#snippet children(props)}
                                                    <button
                                                        type="button"
                                                        class={`${props.class} text-destructive`}
                                                        onclick={() => {
                                                            props.onClick?.();
                                                            deletingFolder =
                                                                group.folder;
                                                        }}
                                                    >
                                                        <Trash2
                                                            data-icon="inline-start"
                                                        />
                                                        {t('Delete')}
                                                    </button>
                                                {/snippet}
                                            </DropdownMenu.DropdownMenuItem>
                                        </DropdownMenu.DropdownMenuGroup>
                                    </DropdownMenu.DropdownMenuContent>
                                </DropdownMenu.DropdownMenu>
                            </div>

                            {#if group.servers.length === 0}
                                <div
                                    class="rounded-xl border border-dashed border-white/10 px-4 py-5 text-center text-sm text-[#80848e]"
                                >
                                    {t('Drag and drop projects here.')}
                                </div>
                            {:else}
                                <div class="flex flex-col gap-3">
                                    {#each group.servers as server (server.id)}
                                        <ProjectCard
                                            {server}
                                            {folders}
                                            {currentUserId}
                                            moving={movingServerId ===
                                                server.id}
                                            onEdit={editServer}
                                            onMove={moveServer}
                                            onDragStart={startServerDrag}
                                            onDragEnd={finishServerDrag}
                                        />
                                    {/each}
                                </div>
                            {/if}
                        </section>
                    {/each}

                    {#if folders.length > 0 || unfiledServers.length > 0}
                        <section
                            class={`flex flex-col gap-3 rounded-2xl transition-[background-color,box-shadow] ${dragOverDestination === 'unfiled' ? 'bg-[#5865f2]/10 ring-2 ring-[#5865f2]' : ''}`}
                            aria-label={t('Uncategorized projects')}
                            data-folder-drop-zone="unfiled"
                            ondragover={(event) => dragServerOver(event, null)}
                            ondragleave={(event) => leaveDropZone(event, null)}
                            ondrop={(event) => dropServer(event, null)}
                        >
                            {#if folders.length > 0}
                                <div
                                    class="flex min-h-8 items-center gap-2 px-1"
                                >
                                    <Hash class="size-4 text-[#b5bac1]" />
                                    <h2 class="text-sm font-semibold">
                                        {t('Uncategorized')}
                                    </h2>
                                    <Badge variant="secondary">
                                        {unfiledServers.length}
                                    </Badge>
                                    {#if dragOverDestination === 'unfiled'}
                                        <span
                                            class="text-xs font-medium text-[#c9cdfb]"
                                        >
                                            {t('Drop here')}
                                        </span>
                                    {/if}
                                </div>
                            {/if}
                            {#if unfiledServers.length === 0}
                                <div
                                    class="rounded-xl border border-dashed border-white/10 px-4 py-5 text-center text-sm text-[#80848e]"
                                >
                                    {t(
                                        'Drag and drop projects here to remove them from folders.',
                                    )}
                                </div>
                            {:else}
                                <div class="flex flex-col gap-3">
                                    {#each unfiledServers as server (server.id)}
                                        <ProjectCard
                                            {server}
                                            {folders}
                                            {currentUserId}
                                            moving={movingServerId ===
                                                server.id}
                                            onEdit={editServer}
                                            onMove={moveServer}
                                            onDragStart={startServerDrag}
                                            onDragEnd={finishServerDrag}
                                        />
                                    {/each}
                                </div>
                            {/if}
                        </section>
                    {/if}
                </div>
            {/if}
        </div>

        <Button
            variant="outline"
            class="fixed right-6 bottom-6 shadow-lg"
            asChild
        >
            {#snippet children(props)}
                <a class={props.class} href="/servers/archived">
                    <Archive data-icon="inline-start" />
                    {t('Archived')}
                    {#if archivedCount > 0}
                        <Badge variant="secondary">{archivedCount}</Badge>
                    {/if}
                </a>
            {/snippet}
        </Button>
    </main>
</div>

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}

{#if showFolderDialog}
    <ProjectFolderDialog
        folder={editingFolder}
        onSaved={saveFolder}
        onClose={() => {
            showFolderDialog = false;
            editingFolder = null;
        }}
    />
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
            servers = servers.map((item) =>
                item.id === updated.id
                    ? { ...item, ...updated, members: item.members }
                    : item,
            );
        }}
        onMembersUpdated={(members) => {
            editingServer = editingServer
                ? { ...editingServer, members, members_count: members.length }
                : null;
            servers = servers.map((item) =>
                item.id === editingServer?.id
                    ? { ...item, members, members_count: members.length }
                    : item,
            );
        }}
        onArchived={(updated) => {
            servers = servers.filter((item) => item.id !== updated.id);
            archivedCount += 1;
        }}
        onRestored={(updated) => {
            servers = servers.map((item) =>
                item.id === updated.id ? { ...item, ...updated } : item,
            );
        }}
        onDeleted={(serverId) => {
            servers = servers.filter((item) => item.id !== serverId);
        }}
        onClose={() => (editingServer = null)}
    />
{/if}

<AlertDialog.Root
    open={deletingFolder !== null}
    onOpenChange={(open) => {
        if (!open && !folderActionPending) {
            deletingFolder = null;
        }
    }}
>
    <AlertDialog.Content>
        <AlertDialog.Header>
            <AlertDialog.Title>{t('Delete folder?')}</AlertDialog.Title>
            <AlertDialog.Description>
                {t(
                    'Projects in ":name" will not be deleted and will be moved to Uncategorized.',
                    { name: deletingFolder?.name ?? '' },
                )}
            </AlertDialog.Description>
        </AlertDialog.Header>
        <AlertDialog.Footer>
            <AlertDialog.Cancel disabled={folderActionPending}>
                {t('Cancel')}
            </AlertDialog.Cancel>
            <AlertDialog.Action
                variant="destructive"
                disabled={folderActionPending}
                onclick={deleteFolder}
            >
                {t('Delete')}
            </AlertDialog.Action>
        </AlertDialog.Footer>
    </AlertDialog.Content>
</AlertDialog.Root>

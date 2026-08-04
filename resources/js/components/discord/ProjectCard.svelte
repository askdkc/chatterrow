<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarRange from 'lucide-svelte/icons/calendar-range';
    import Check from 'lucide-svelte/icons/check';
    import FolderInput from 'lucide-svelte/icons/folder-input';
    import Hash from 'lucide-svelte/icons/hash';
    import Settings from 'lucide-svelte/icons/settings';
    import Users from 'lucide-svelte/icons/users';
    import ProjectIcon from '@/components/discord/ProjectIcon.svelte';
    import { Button } from '@/components/ui/button';
    import * as DropdownMenu from '@/components/ui/dropdown-menu';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type { ProjectFolderResource, ServerResource } from '@/types';

    let {
        server,
        folders,
        currentUserId,
        moving = false,
        onEdit,
        onMove,
        onDragStart,
        onDragEnd,
    }: {
        server: ServerResource;
        folders: ProjectFolderResource[];
        currentUserId: number | null;
        moving?: boolean;
        onEdit: (server: ServerResource) => void;
        onMove: (serverId: number, folderId: number | null) => void;
        onDragStart?: (serverId: number) => void;
        onDragEnd?: () => void;
    } = $props();

    const canManage = $derived(
        isProjectAdministrator(server, server.members ?? [], currentUserId),
    );

    function moveToFolder(folderId: number | null, closeMenu?: () => void) {
        closeMenu?.();

        if (folderId !== (server.project_folder_id ?? null)) {
            onMove(server.id, folderId);
        }
    }

    function startDragging(event: DragEvent) {
        if (moving || !event.dataTransfer) {
            event.preventDefault();

            return;
        }

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(
            'application/x-chatterrow-project-id',
            String(server.id),
        );
        event.dataTransfer.setData('text/plain', String(server.id));
        onDragStart?.(server.id);
    }
</script>

<div
    class={`group relative transition-opacity ${moving ? 'opacity-60' : ''}`}
    role="group"
    aria-label={`${server.name}プロジェクトカード`}
    draggable={!moving}
    data-project-card={server.id}
    ondragstart={startDragging}
    ondragend={onDragEnd}
>
    <button
        type="button"
        class="flex w-full items-center gap-4 rounded-xl bg-[#2b2d31] p-4 pr-24 text-left transition hover:bg-[#383a40]"
        onclick={() => router.visit(`/servers/${server.id}`)}
    >
        <ProjectIcon {server} />
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold">{server.name}</span>
            {#if server.description}
                <span class="mt-0.5 block truncate text-sm text-[#80848e]">
                    {server.description}
                </span>
            {/if}
            <span
                class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-[#80848e]"
            >
                <span class="flex items-center gap-1">
                    <Hash class="size-3" />
                    チャンネル {server.channels_count ?? 0}
                </span>
                <span class="flex items-center gap-1">
                    <Users class="size-3" />
                    メンバー {server.members_count ?? 0}
                </span>
                {#if server.starts_on || server.ends_on}
                    <span class="flex items-center gap-1">
                        <CalendarRange class="size-3" />
                        {server.starts_on ?? '?'} 〜 {server.ends_on ?? '未定'}
                    </span>
                {/if}
            </span>
        </span>
    </button>

    <div
        class="absolute top-1/2 right-3 flex -translate-y-1/2 items-center gap-1 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100"
    >
        <DropdownMenu.DropdownMenu>
            <DropdownMenu.DropdownMenuTrigger asChild>
                {#snippet children(props)}
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`${server.name}をフォルダへ移動`}
                        title="フォルダへ移動"
                        disabled={moving}
                        onclick={(event) => {
                            event.stopPropagation();
                            props.onclick?.(event);
                        }}
                        aria-expanded={props['aria-expanded']}
                        data-state={props['data-state']}
                    >
                        <FolderInput />
                    </Button>
                {/snippet}
            </DropdownMenu.DropdownMenuTrigger>
            <DropdownMenu.DropdownMenuContent align="end" class="w-56">
                <DropdownMenu.DropdownMenuLabel>
                    移動先のフォルダ
                </DropdownMenu.DropdownMenuLabel>
                <DropdownMenu.DropdownMenuSeparator />
                <DropdownMenu.DropdownMenuGroup>
                    <DropdownMenu.DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <button
                                type="button"
                                class={props.class}
                                onclick={(event) => {
                                    event.stopPropagation();
                                    moveToFolder(null, props.onClick);
                                }}
                            >
                                <span class="flex-1 text-left">未分類</span>
                                {#if server.project_folder_id == null}
                                    <Check data-icon="inline-end" />
                                {/if}
                            </button>
                        {/snippet}
                    </DropdownMenu.DropdownMenuItem>
                    {#each folders as folder (folder.id)}
                        <DropdownMenu.DropdownMenuItem asChild>
                            {#snippet children(props)}
                                <button
                                    type="button"
                                    class={props.class}
                                    onclick={(event) => {
                                        event.stopPropagation();
                                        moveToFolder(folder.id, props.onClick);
                                    }}
                                >
                                    <span class="flex-1 truncate text-left">
                                        {folder.name}
                                    </span>
                                    {#if server.project_folder_id === folder.id}
                                        <Check data-icon="inline-end" />
                                    {/if}
                                </button>
                            {/snippet}
                        </DropdownMenu.DropdownMenuItem>
                    {/each}
                </DropdownMenu.DropdownMenuGroup>
            </DropdownMenu.DropdownMenuContent>
        </DropdownMenu.DropdownMenu>

        {#if canManage}
            <Button
                variant="ghost"
                size="icon"
                aria-label={`${server.name}の設定`}
                title="プロジェクト設定"
                onclick={(event) => {
                    event.stopPropagation();
                    onEdit(server);
                }}
            >
                <Settings />
            </Button>
        {/if}
    </div>
</div>

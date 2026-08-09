<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        FileSpreadsheet,
        FileText,
        FileType2,
        Download,
        Eye,
        Upload,
        Trash2,
        Loader2,
    } from 'lucide-svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import OnlyOfficePreviewDialog from '@/components/files/OnlyOfficePreviewDialog.svelte';
    import StoredFilePreviewDialog from '@/components/files/StoredFilePreviewDialog.svelte';
    import GlobalSearch from '@/components/GlobalSearch.svelte';
    import { Badge } from '@/components/ui/badge';
    import { formatDate } from '@/lib/dates';
    import { filesFromDrop } from '@/lib/dropped-files';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type {
        ServerResource,
        ChannelResource,
        SearchSnippetSegment,
        UserResource,
    } from '@/types';

    interface StoredFileResource {
        id: number;
        original_name: string;
        mime_type: string | null;
        size: number | null;
        preview_status: string | null;
        created_at: string | null;
        uploader?: { id: number; name: string } | null;
        stream_url: string;
        download_url: string;
        thumbnail_url: string | null;
    }

    interface SearchResult extends StoredFileResource {
        snippet: string;
        snippet_segments: SearchSnippetSegment[];
    }

    const officeExtensions = new Set([
        'doc',
        'docx',
        'xls',
        'xlsx',
        'xlsm',
        'ppt',
        'pptx',
        'odt',
        'ods',
        'odp',
    ]);

    let {
        server,
        channel,
        files,
        channels,
        members,
    }: {
        server: ServerResource;
        channel?: ChannelResource | null;
        files: StoredFileResource[];
        channels: ChannelResource[];
        members: UserResource[];
    } = $props();

    const page = usePage();

    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );

    let showMemberDialog = $state(false);
    let uploadInput: HTMLInputElement | undefined = $state();
    let uploading = $state(false);
    let dragActive = $state(false);
    let dragDepth = 0;
    let previewFile = $state<StoredFileResource | null>(null);
    let onlyofficeFile = $state<StoredFileResource | null>(null);
    let error = $state('');
    let searchQuery = $state('');
    let searchResults = $state<SearchResult[]>([]);
    let searching = $state(false);
    let searchTimer: ReturnType<typeof setTimeout> | undefined = $state();
    let searchController: AbortController | undefined = $state();
    let searchSequence = 0;

    const isImage = (f: StoredFileResource): boolean =>
        (f.mime_type ?? '').startsWith('image/');
    const isVideo = (f: StoredFileResource): boolean =>
        (f.mime_type ?? '').startsWith('video/');
    const isOffice = (f: StoredFileResource): boolean =>
        officeExtensions.has(
            f.original_name.split('.').pop()?.toLowerCase() ?? '',
        );

    function fileTypeLabel(file: StoredFileResource): string {
        const extension = file.original_name.match(/\.([^.]+)$/)?.[1];

        return (extension?.toUpperCase() ?? 'FILE').slice(0, 8);
    }

    function fileTypeIcon(
        file: StoredFileResource,
    ): 'pdf' | 'spreadsheet' | 'document' | 'generic' {
        const extension =
            file.original_name.split('.').pop()?.toLocaleLowerCase('en-US') ??
            '';

        if (extension === 'pdf' || file.mime_type === 'application/pdf') {
            return 'pdf';
        }

        if (['xls', 'xlsx', 'xlsm', 'ods', 'csv'].includes(extension)) {
            return 'spreadsheet';
        }

        if (['doc', 'docx', 'odt', 'rtf', 'txt'].includes(extension)) {
            return 'document';
        }

        return 'generic';
    }

    function formatSize(bytes: number | null): string {
        if (bytes === null) {
            return '';
        }

        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    async function uploadFiles(fileList: FileList | File[] | null) {
        if (!fileList || fileList.length === 0 || uploading) {
            return;
        }

        uploading = true;
        error = '';

        try {
            const selected = Array.from(fileList);

            for (let index = 0; index < selected.length; index += 10) {
                const form = new FormData();

                for (const file of selected.slice(index, index + 10)) {
                    form.append('files[]', file);
                }

                await apiFetch(`/servers/${server.id}/files`, {
                    method: 'POST',
                    body: form,
                });
            }

            window.location.reload();
        } catch (e) {
            error =
                e instanceof HttpError
                    ? e.messageText()
                    : t('Failed to upload files');
        } finally {
            uploading = false;
        }
    }

    async function onUpload(event: Event) {
        const input = event.currentTarget as HTMLInputElement;

        await uploadFiles(input.files);
        input.value = '';
    }

    function isFileDrag(event: DragEvent): boolean {
        return Array.from(event.dataTransfer?.types ?? []).includes('Files');
    }

    function resetDragState() {
        dragDepth = 0;
        dragActive = false;
    }

    function onDragEnter(event: DragEvent) {
        if (!isFileDrag(event) || uploading) {
            return;
        }

        event.preventDefault();
        dragDepth += 1;
        dragActive = true;
    }

    function onDragOver(event: DragEvent) {
        if (!isFileDrag(event) || uploading) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'copy';
        }
    }

    function onDragLeave(event: DragEvent) {
        if (!dragActive) {
            return;
        }

        event.preventDefault();
        dragDepth = Math.max(0, dragDepth - 1);

        if (dragDepth === 0) {
            dragActive = false;
        }
    }

    async function onDrop(event: DragEvent) {
        event.preventDefault();
        resetDragState();

        if (!event.dataTransfer || uploading) {
            return;
        }

        error = '';

        try {
            await uploadFiles(await filesFromDrop(event.dataTransfer));
        } catch {
            error = t('Failed to read folder');
        }
    }

    async function removeFile(file: StoredFileResource) {
        if (
            !window.confirm(
                t('Are you sure you want to remove :name?', {
                    name: file.original_name,
                }),
            )
        ) {
            return;
        }

        try {
            await apiFetch(`/servers/${server.id}/files/${file.id}`, {
                method: 'DELETE',
            });
            files = files.filter((f) => f.id !== file.id);
        } catch (e) {
            error =
                e instanceof HttpError
                    ? e.messageText()
                    : t('Failed to delete file');
        }
    }

    function openPreview(file: StoredFileResource) {
        if (isOffice(file)) {
            onlyofficeFile = file;
        } else {
            previewFile = file;
        }
    }

    async function onSearchInput() {
        clearTimeout(searchTimer);
        searchController?.abort();
        searchController = undefined;
        searchSequence += 1;
        const sequence = searchSequence;
        const query = searchQuery.trim();

        if (!query) {
            searchResults = [];
            searching = false;

            return;
        }

        searching = true;
        searchTimer = setTimeout(async () => {
            const controller = new AbortController();
            searchController = controller;

            try {
                const channelQuery = channel
                    ? `&channel_id=${encodeURIComponent(String(channel.id))}`
                    : '';
                const data = await apiJson<{ results: SearchResult[] }>(
                    `/servers/${server.id}/files/search?q=${encodeURIComponent(query)}${channelQuery}`,
                    { signal: controller.signal },
                );

                if (sequence !== searchSequence) {
                    return;
                }

                searchResults = data.results;
            } catch (e) {
                if (
                    sequence !== searchSequence ||
                    (e instanceof Error && e.name === 'AbortError')
                ) {
                    return;
                }

                error =
                    e instanceof HttpError
                        ? e.messageText()
                        : t('Failed to search files');
                searchResults = [];
            } finally {
                if (sequence === searchSequence) {
                    searching = false;
                }

                if (searchController === controller) {
                    searchController = undefined;
                }
            }
        }, 300);
    }

    function snippetSegments(result: SearchResult): SearchSnippetSegment[] {
        if (result.snippet_segments.length > 0) {
            return result.snippet_segments;
        }

        return [
            {
                type: 'text',
                text: result.snippet.replace(/<\/?mark>/g, ''),
            },
        ];
    }

    function openSearchResult(result: SearchResult) {
        openPreview(result);
    }

    function onAddServer() {
        window.location.href = '/servers';
    }

    function onBrowse() {
        window.location.href = '/servers';
    }

    function onManageMembers() {
        showMemberDialog = true;
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
        activeChannelId={channel?.id ?? null}
        {onManageMembers}
    />

    <main class="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header
            class="sticky top-0 z-10 flex h-12 shrink-0 items-center gap-3 border-b border-black/10 bg-[#313338] px-4 dark:border-black/20"
        >
            <Link
                href={channel
                    ? `/servers/${server.id}/channels/${channel.id}`
                    : `/servers/${server.id}`}
                class="rounded p-1 transition hover:bg-white/10"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <FileText class="h-4 w-4 text-[#5865f2]" />
            <h1 class="text-[15px] font-bold">
                {channel
                    ? t('Files - #:channel', { channel: channel.name })
                    : t('File list')}
            </h1>
            <GlobalSearch class="ml-auto" />
            <span class="text-xs text-[#80848e]">
                {t('File count: :count', { count: String(files.length) })}
            </span>
        </header>

        <div class="border-b border-black/10 px-4 py-2 dark:border-black/20">
            <input
                type="search"
                bind:value={searchQuery}
                oninput={onSearchInput}
                placeholder={t(
                    'Search file contents (PDF / Word / Excel / PowerPoint)',
                )}
                class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                aria-label={t('Full-text file search')}
            />
        </div>

        {#if searchQuery.trim() && !searching}
            <div
                class="max-h-72 shrink-0 overflow-y-auto border-b border-black/10 p-3 dark:border-black/20"
            >
                {#if searchResults.length === 0}
                    <p class="py-4 text-center text-sm text-[#80848e]">
                        {t('No file search results')}
                    </p>
                {:else}
                    <p class="mb-2 text-xs text-[#80848e]">
                        {t('Matching files: :count', {
                            count: String(searchResults.length),
                        })}
                    </p>
                    <div class="space-y-2">
                        {#each searchResults as result (result.id)}
                            <button
                                type="button"
                                class="block w-full rounded-lg bg-[#2b2d31] p-3 text-left transition hover:bg-[#383a40]"
                                onclick={() => openSearchResult(result)}
                            >
                                <span class="flex items-center gap-2">
                                    <FileText
                                        class="h-4 w-4 shrink-0 text-[#5865f2]"
                                    />
                                    <span class="truncate text-sm font-medium">
                                        {result.original_name}
                                    </span>
                                </span>
                                <span
                                    class="mt-1 block text-xs leading-5 text-[#80848e]"
                                >
                                    {#each snippetSegments(result) as segment, segmentIndex (segmentIndex)}
                                        <span
                                            class={segment.type === 'hit'
                                                ? 'rounded-sm bg-[#f0b232]/40'
                                                : ''}>{segment.text}</span
                                        >
                                    {/each}
                                </span>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}

        <div
            role="region"
            aria-label={t('File list and upload drop area')}
            class="relative flex min-h-0 flex-1 flex-col overflow-hidden"
            ondragenter={onDragEnter}
            ondragover={onDragOver}
            ondragleave={onDragLeave}
            ondrop={onDrop}
            ondragend={resetDragState}
        >
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-6">
                <!-- Upload button -->
                <div class="mb-4">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4] disabled:opacity-50"
                        onclick={() => uploadInput?.click()}
                        disabled={uploading}
                    >
                        {#if uploading}
                            <Loader2 class="h-4 w-4 animate-spin" />
                        {:else}
                            <Upload class="h-4 w-4" />
                        {/if}
                        {t('Upload')}
                    </button>
                    <input
                        bind:this={uploadInput}
                        type="file"
                        multiple
                        class="hidden"
                        onchange={onUpload}
                    />
                    {#if error}
                        <p
                            class="mt-2 text-xs text-red-400"
                            role="alert"
                            aria-live="assertive"
                        >
                            {error}
                        </p>
                    {/if}
                </div>

                {#if files.length === 0}
                    <div
                        class="flex flex-1 flex-col items-center justify-center text-center"
                    >
                        <FileText class="mb-3 h-12 w-12 text-[#80848e]" />
                        <p class="font-medium">{t('No files')}</p>
                        <p class="mt-1 text-sm text-[#80848e]">
                            {t(
                                'Drag and drop files here or use the upload button to add them.',
                            )}
                        </p>
                    </div>
                {:else}
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        {#each files as file (file.id)}
                            {@const typeIcon = fileTypeIcon(file)}
                            <div
                                class="group relative overflow-hidden rounded-xl bg-[#2b2d31] transition hover:bg-[#383a40]"
                            >
                                <!-- Thumbnail / icon -->
                                <button
                                    type="button"
                                    class="flex aspect-video w-full items-center justify-center overflow-hidden bg-[#232428]"
                                    onclick={() => openPreview(file)}
                                    title={t('Preview')}
                                    aria-label={t('Preview :name', {
                                        name: file.original_name,
                                    })}
                                >
                                    {#if file.thumbnail_url}
                                        <img
                                            src={file.thumbnail_url}
                                            alt={file.original_name}
                                            class="h-full w-full object-cover transition group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    {:else if isImage(file) && file.stream_url}
                                        <img
                                            src={file.stream_url}
                                            alt={file.original_name}
                                            class="h-full w-full object-cover transition group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    {:else if isVideo(file) && file.stream_url}
                                        <video
                                            src={file.stream_url}
                                            preload="metadata"
                                            muted
                                            playsinline
                                            aria-hidden="true"
                                            class="pointer-events-none h-full w-full object-cover transition group-hover:scale-105"
                                        ></video>
                                    {:else}
                                        <div
                                            class="flex flex-col items-center gap-2 text-[#80848e]"
                                        >
                                            <FileText class="h-10 w-10" />
                                            <span class="text-xs"
                                                >{file.original_name
                                                    .split('.')
                                                    .pop()
                                                    ?.toUpperCase()}</span
                                            >
                                        </div>
                                    {/if}
                                </button>

                                {#if !isImage(file) && !isVideo(file)}
                                    <Badge
                                        title={t('File type: :type', {
                                            type: fileTypeLabel(file),
                                        })}
                                        data-file-kind={typeIcon}
                                        class="pointer-events-none absolute right-2 top-2 rounded-md px-2 py-1 text-xs font-bold tracking-wide shadow-md"
                                    >
                                        {#if typeIcon === 'pdf' || typeIcon === 'document'}
                                            <FileText
                                                data-file-type-icon={typeIcon}
                                                aria-hidden="true"
                                            />
                                        {:else if typeIcon === 'spreadsheet'}
                                            <FileSpreadsheet
                                                data-file-type-icon="spreadsheet"
                                                aria-hidden="true"
                                            />
                                        {:else}
                                            <FileType2
                                                data-file-type-icon="generic"
                                                aria-hidden="true"
                                            />
                                        {/if}
                                        <span class="max-w-24 truncate"
                                            >{fileTypeLabel(file)}</span
                                        >
                                    </Badge>
                                {/if}

                                <div class="p-3">
                                    <p
                                        class="truncate text-sm font-medium"
                                        title={file.original_name}
                                    >
                                        {file.original_name}
                                    </p>
                                    <div
                                        class="mt-1 flex items-center justify-between text-xs text-[#80848e]"
                                    >
                                        <span>{formatSize(file.size)}</span>
                                        <span
                                            >{formatDate(file.created_at)}</span
                                        >
                                    </div>
                                </div>

                                <!-- Hover actions -->
                                <div
                                    class="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition group-hover:opacity-100"
                                >
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#5865f2] px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-[#4752c4]"
                                        onclick={() => openPreview(file)}
                                    >
                                        <span class="flex items-center gap-1">
                                            <Eye class="h-3.5 w-3.5" />
                                            {t('Preview')}
                                        </span>
                                    </button>
                                    <a
                                        href={file.download_url}
                                        class="rounded-md bg-white/15 px-2.5 py-1.5 text-xs font-medium text-white backdrop-blur transition hover:bg-white/25"
                                        title={t('Download')}
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                    </a>
                                    <button
                                        type="button"
                                        class="rounded-md bg-red-500/80 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-500"
                                        onclick={() => removeFile(file)}
                                        title={t('Delete')}
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            {#if dragActive}
                <div
                    class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-[#5865f2]/20 p-6 backdrop-blur-sm"
                    role="status"
                    aria-live="polite"
                >
                    <div
                        class="flex items-center gap-3 rounded-2xl border-2 border-dashed border-[#5865f2] bg-[#313338]/95 px-8 py-6 text-lg font-semibold shadow-xl"
                    >
                        <Upload class="h-6 w-6" />
                        {t('Drop files to upload')}
                    </div>
                </div>
            {/if}
        </div>
    </main>
</div>

{#if previewFile}
    <StoredFilePreviewDialog
        serverId={server.id}
        file={{
            id: previewFile.id,
            name: previewFile.original_name,
            mimeType: previewFile.mime_type,
        }}
        onClose={() => (previewFile = null)}
    />
{/if}

{#if onlyofficeFile}
    <OnlyOfficePreviewDialog
        serverId={server.id}
        file={{
            id: onlyofficeFile.id,
            name: onlyofficeFile.original_name,
            mimeType: onlyofficeFile.mime_type,
        }}
        onClose={() => (onlyofficeFile = null)}
    />
{/if}

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

<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        FileText,
        Film,
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
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import type {
        ServerResource,
        ChannelResource,
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
    let previewFile = $state<StoredFileResource | null>(null);
    let onlyofficeFile = $state<StoredFileResource | null>(null);
    let error = $state('');
    let searchQuery = $state('');
    let searchResults = $state<SearchResult[]>([]);
    let searching = $state(false);
    let searchTimer: ReturnType<typeof setTimeout> | undefined = $state();

    const isImage = (f: StoredFileResource): boolean =>
        (f.mime_type ?? '').startsWith('image/');
    const isVideo = (f: StoredFileResource): boolean =>
        (f.mime_type ?? '').startsWith('video/');
    const isOffice = (f: StoredFileResource): boolean =>
        officeExtensions.has(
            f.original_name.split('.').pop()?.toLowerCase() ?? '',
        );

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

    function formatDate(iso: string | null): string {
        if (!iso) {
            return '';
        }

        return new Date(iso).toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    async function onUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        const selected = input.files;

        if (!selected || selected.length === 0) {
            return;
        }

        uploading = true;

        try {
            const form = new FormData();

            for (const file of Array.from(selected)) {
                form.append('files[]', file);
            }

            await apiFetch(`/servers/${server.id}/files`, {
                method: 'POST',
                body: form,
            });

            window.location.reload();
        } catch (e) {
            error =
                e instanceof HttpError
                    ? e.messageText()
                    : 'アップロードに失敗しました';
        } finally {
            uploading = false;
            input.value = '';
        }
    }

    async function removeFile(file: StoredFileResource) {
        if (!window.confirm(`${file.original_name} を削除しますか？`)) {
            return;
        }

        try {
            await apiFetch(`/servers/${server.id}/files/${file.id}`, {
                method: 'DELETE',
            });
            files = files.filter((f) => f.id !== file.id);
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '削除に失敗しました';
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
        const query = searchQuery.trim();

        if (!query) {
            searchResults = [];

            return;
        }

        searching = true;
        searchTimer = setTimeout(async () => {
            try {
                const data = await apiJson<{ results: SearchResult[] }>(
                    `/servers/${server.id}/files/search?q=${encodeURIComponent(query)}`,
                );
                searchResults = data.results;
            } catch (e) {
                error =
                    e instanceof HttpError
                        ? e.messageText()
                        : '検索に失敗しました';
                searchResults = [];
            } finally {
                searching = false;
            }
        }, 300);
    }

    function snippetParts(snippet: string): { text: string; hit: boolean }[] {
        return snippet.split(/<mark>|<\/mark>/).map((part, index) => ({
            text: part,
            hit: index % 2 === 1,
        }));
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

    function onAddChannel() {}

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
        {onAddChannel}
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
                {channel ? `ファイル - #${channel.name}` : 'ファイル一覧'}
            </h1>
            <span class="ml-auto text-xs text-[#80848e]">{files.length} 件</span
            >
        </header>

        <div class="border-b border-black/10 px-4 py-2 dark:border-black/20">
            <input
                type="search"
                bind:value={searchQuery}
                oninput={onSearchInput}
                placeholder="ファイルの全文を検索（PDF / Word / Excel / PowerPoint）"
                class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                aria-label="ファイル全文検索"
            />
        </div>

        {#if searchQuery.trim() && !searching}
            <div
                class="max-h-72 shrink-0 overflow-y-auto border-b border-black/10 p-3 dark:border-black/20"
            >
                {#if searchResults.length === 0}
                    <p class="py-4 text-center text-sm text-[#80848e]">
                        検索結果はありません
                    </p>
                {:else}
                    <p class="mb-2 text-xs text-[#80848e]">
                        {searchResults.length} 件のファイルが一致
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
                                    {#each snippetParts(result.snippet) as part (part.text + part.hit)}
                                        {#if part.hit}
                                            <mark
                                                class="rounded-sm bg-[#f0b232]/40 text-[#dbdee1]"
                                                >{part.text}</mark
                                            >
                                        {:else}
                                            {part.text}
                                        {/if}
                                    {/each}
                                </span>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}

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
                    アップロード
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
                    <p class="font-medium">ファイルがありません</p>
                    <p class="mt-1 text-sm text-[#80848e]">
                        チャットにドラッグ&ドロップするか、ここからアップロードできます
                    </p>
                </div>
            {:else}
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    {#each files as file (file.id)}
                        <div
                            class="group relative overflow-hidden rounded-xl bg-[#2b2d31] transition hover:bg-[#383a40]"
                        >
                            <!-- Thumbnail / icon -->
                            <button
                                type="button"
                                class="flex aspect-video w-full items-center justify-center overflow-hidden bg-[#232428]"
                                onclick={() => openPreview(file)}
                                title="プレビュー"
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
                                {:else if isVideo(file)}
                                    <div
                                        class="flex flex-col items-center gap-2 text-[#80848e]"
                                    >
                                        <Film class="h-10 w-10" />
                                        <span class="text-xs">動画</span>
                                    </div>
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
                                    <span>{formatDate(file.created_at)}</span>
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
                                        プレビュー
                                    </span>
                                </button>
                                <a
                                    href={file.download_url}
                                    class="rounded-md bg-white/15 px-2.5 py-1.5 text-xs font-medium text-white backdrop-blur transition hover:bg-white/25"
                                    title="ダウンロード"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                </a>
                                <button
                                    type="button"
                                    class="rounded-md bg-red-500/80 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-500"
                                    onclick={() => removeFile(file)}
                                    title="削除"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    {/each}
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
        onUpdated={(updated) => (server = { ...server, ...updated })}
        onClose={() => (showMemberDialog = false)}
    />
{/if}

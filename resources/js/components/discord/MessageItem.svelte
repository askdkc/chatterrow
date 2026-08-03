<script lang="ts">
    import {
        Check,
        Download,
        FileType2,
        Loader2,
        MessageSquare,
        Paperclip,
        Pencil,
        Trash2,
        X,
    } from 'lucide-svelte';
    import OnlyOfficePreviewDialog from '@/components/files/OnlyOfficePreviewDialog.svelte';
    import StoredFilePreviewDialog, {
        canPreviewStoredFile,
    } from '@/components/files/StoredFilePreviewDialog.svelte';
    import { formatDate, formatTime } from '@/lib/dates';
    import { HttpError } from '@/lib/http';
    import {
        renderHighlightedMessageMarkdown,
        renderMessageMarkdown,
    } from '@/lib/markdown';
    import type { MessageResource, StoredFileResource } from '@/types';

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
        message,
        onOpenThread,
        canEdit = false,
        canDelete = false,
        onEdit,
        onDelete,
    }: {
        message: MessageResource;
        onOpenThread?: () => void;
        canEdit?: boolean;
        canDelete?: boolean;
        onEdit?: (body: string) => Promise<void>;
        onDelete?: () => Promise<void>;
    } = $props();

    let editing = $state(false);
    let editBody = $state('');
    let saving = $state(false);
    let deleting = $state(false);
    let actionError = $state('');
    let previewFile = $state<StoredFileResource | null>(null);
    let onlyofficeFile = $state<StoredFileResource | null>(null);
    let highlightedBody = $state<{ source: string; html: string } | null>(null);
    let renderGeneration = 0;
    const renderedBody = $derived(
        highlightedBody?.source === message.body
            ? highlightedBody.html
            : renderMessageMarkdown(message.body),
    );

    $effect(() => {
        const body = message.body;
        const generation = ++renderGeneration;
        highlightedBody = null;

        void renderHighlightedMessageMarkdown(body).then((html) => {
            if (generation === renderGeneration) {
                highlightedBody = { source: body, html };
            }
        });
    });

    function startEditing() {
        editBody = message.body;
        actionError = '';
        editing = true;
    }

    async function saveEdit() {
        const body = editBody.trim();

        if (!onEdit || saving || !body) {
            return;
        }

        saving = true;
        actionError = '';

        try {
            await onEdit(body);
            editing = false;
        } catch (error) {
            actionError =
                error instanceof HttpError
                    ? error.messageText()
                    : '編集に失敗しました';
        } finally {
            saving = false;
        }
    }

    async function removeMessage() {
        if (
            !onDelete ||
            deleting ||
            !window.confirm('このメッセージを削除しますか？')
        ) {
            return;
        }

        deleting = true;
        actionError = '';

        try {
            await onDelete();
        } catch (error) {
            actionError =
                error instanceof HttpError
                    ? error.messageText()
                    : '削除に失敗しました';
        } finally {
            deleting = false;
        }
    }

    function isImage(file: StoredFileResource): boolean {
        return (file.mime_type ?? '').startsWith('image/');
    }

    function isVideo(file: StoredFileResource): boolean {
        return (file.mime_type ?? '').startsWith('video/');
    }

    function isOffice(file: StoredFileResource): boolean {
        return officeExtensions.has(
            file.original_name.split('.').pop()?.toLowerCase() ?? '',
        );
    }

    function fileTypeLabel(file: StoredFileResource): string {
        const extension = file.original_name.match(/\.([^.]+)$/)?.[1];

        return (extension?.toUpperCase() ?? 'FILE').slice(0, 8);
    }

    function openPreview(file: StoredFileResource) {
        if (isOffice(file)) {
            onlyofficeFile = file;
        } else {
            previewFile = file;
        }
    }
</script>

<div
    class="group relative flex gap-4 rounded-md px-2 py-2 transition hover:bg-white/5"
>
    {#if (canEdit && onEdit) || (canDelete && onDelete)}
        <div
            class="absolute -top-2 right-2 z-10 flex overflow-hidden rounded-md border border-black/20 bg-[#2b2d31] opacity-0 shadow transition group-focus-within:opacity-100 group-hover:opacity-100"
        >
            {#if canEdit && onEdit}
                <button
                    type="button"
                    class="p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-[#dbdee1]"
                    onclick={startEditing}
                    title="メッセージを編集"
                >
                    <Pencil class="h-3.5 w-3.5" />
                </button>
            {/if}
            {#if canDelete && onDelete}
                <button
                    type="button"
                    class="p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-red-400 disabled:opacity-50"
                    onclick={removeMessage}
                    disabled={deleting}
                    title="メッセージを削除"
                >
                    {#if deleting}
                        <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    {:else}
                        <Trash2 class="h-3.5 w-3.5" />
                    {/if}
                </button>
            {/if}
        </div>
    {/if}

    <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#5865f2] text-sm font-bold text-white"
    >
        {message.user?.name?.slice(0, 1).toUpperCase() ?? '?'}
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2">
            <span class="text-[15px] font-semibold text-[#dbdee1]"
                >{message.user?.name ?? '不明'}</span
            >
            <span class="text-xs text-[#80848e]"
                >{formatDate(message.created_at, { month: 'long' })}
                {formatTime(message.created_at)}</span
            >
        </div>
        {#if editing}
            <div class="mt-1">
                <textarea
                    bind:value={editBody}
                    rows={3}
                    class="max-h-48 min-h-20 w-full resize-y rounded-md bg-[#383a40] px-3 py-2 text-[15px] text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    onkeydown={(event) => {
                        if (event.key === 'Escape') {
                            event.stopPropagation();
                            editing = false;
                        } else if (
                            event.key === 'Enter' &&
                            !event.isComposing &&
                            (event.metaKey || event.ctrlKey)
                        ) {
                            event.preventDefault();
                            saveEdit();
                        }
                    }}
                ></textarea>
                <div class="mt-1.5 flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded bg-[#5865f2] px-2 py-1 text-xs font-medium text-white transition hover:bg-[#4752c4] disabled:opacity-50"
                        onclick={saveEdit}
                        disabled={saving || !editBody.trim()}
                    >
                        {#if saving}
                            <Loader2 class="h-3 w-3 animate-spin" />
                        {:else}
                            <Check class="h-3 w-3" />
                        {/if}
                        保存
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded px-2 py-1 text-xs text-[#b5bac1] transition hover:bg-white/10 hover:text-[#dbdee1]"
                        onclick={() => (editing = false)}
                    >
                        <X class="h-3 w-3" />
                        キャンセル
                    </button>
                </div>
            </div>
        {:else}
            <!-- Markdown escapes user input; Shiki only replaces fenced code blocks. -->
            <div
                class="break-words text-[15px] text-[#dbdee1] [&_a]:text-[#00a8fc] [&_a]:underline [&_blockquote]:my-1 [&_blockquote]:border-l-4 [&_blockquote]:border-[#4e5058] [&_blockquote]:pl-3 [&_code]:rounded [&_code]:bg-[#1e1f22] [&_code]:px-1 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-[#1e1f22] [&_pre]:p-3 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-6"
            >
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html renderedBody}
            </div>
        {/if}

        {#if actionError}
            <p class="mt-1 text-xs text-red-400" role="alert">
                {actionError}
            </p>
        {/if}

        {#if message.attachments && message.attachments.length > 0}
            <div class="mt-2 flex flex-wrap gap-2">
                {#each message.attachments as file (file.id)}
                    {#if isImage(file)}
                        <button
                            type="button"
                            aria-label={`${file.original_name}をプレビュー`}
                            onclick={() => openPreview(file)}
                            class="block max-w-72 overflow-hidden rounded-lg"
                        >
                            <img
                                src={file.stream_url}
                                alt={file.original_name}
                                class="max-h-64 w-auto rounded-lg object-cover transition hover:opacity-90"
                                loading="lazy"
                            />
                        </button>
                    {:else if isVideo(file)}
                        <!-- svelte-ignore a11y_media_has_caption (uploaded files do not include caption tracks) -->
                        <video
                            src={file.stream_url}
                            controls
                            preload="metadata"
                            class="max-h-64 max-w-72 rounded-lg"
                        ></video>
                    {:else if file.thumbnail_url || canPreviewStoredFile(file.original_name)}
                        <div
                            class="relative w-72 overflow-hidden rounded-lg bg-[#383a40]"
                        >
                            <button
                                type="button"
                                aria-label={`${file.original_name}をプレビュー`}
                                onclick={() => openPreview(file)}
                                class="block w-full text-left transition hover:bg-[#404249]"
                            >
                                {#if file.thumbnail_url}
                                    <img
                                        src={file.thumbnail_url}
                                        alt={file.original_name}
                                        class="h-40 w-full object-cover object-top"
                                        loading="lazy"
                                    />
                                {/if}
                                <span
                                    class="flex items-center gap-2 px-3 py-2 pr-10 text-sm"
                                >
                                    <Paperclip class="h-4 w-4 shrink-0" />
                                    <span class="truncate"
                                        >{file.original_name}</span
                                    >
                                </span>
                            </button>
                            <a
                                href={file.download_url ?? '#'}
                                aria-label={`${file.original_name}をダウンロード`}
                                class="absolute bottom-1.5 right-1.5 rounded p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
                            >
                                <Download class="h-3.5 w-3.5" />
                            </a>
                            <span
                                title={`${fileTypeLabel(file)}ファイル`}
                                class="pointer-events-none absolute right-2 top-2 inline-flex items-center gap-1 rounded-md border border-white/15 bg-[#2b2d31]/90 px-1.5 py-1 text-[10px] font-bold tracking-wide text-[#dbdee1] shadow-lg backdrop-blur-sm"
                            >
                                <FileType2
                                    class="h-3.5 w-3.5 text-[#5865f2]"
                                    aria-hidden="true"
                                />
                                <span class="max-w-24 truncate"
                                    >{fileTypeLabel(file)}</span
                                >
                            </span>
                        </div>
                    {:else}
                        <a
                            href={file.download_url ?? '#'}
                            class="flex items-center gap-2 rounded-lg bg-[#383a40] px-3 py-2 text-sm transition hover:bg-[#404249]"
                        >
                            <Paperclip class="h-4 w-4 shrink-0" />
                            <span class="max-w-48 truncate"
                                >{file.original_name}</span
                            >
                            <Download class="h-3.5 w-3.5 shrink-0 opacity-60" />
                        </a>
                    {/if}
                {/each}
            </div>
        {/if}

        {#if onOpenThread}
            <button
                type="button"
                class="mt-1.5 flex items-center gap-1.5 rounded px-1.5 py-0.5 text-xs font-medium text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1]"
                onclick={onOpenThread}
            >
                <MessageSquare class="h-3.5 w-3.5" />
                スレッド
                {#if message.reply_count}
                    <span>{message.reply_count}</span>
                {/if}
            </button>
        {/if}
    </div>
</div>

{#if previewFile}
    <StoredFilePreviewDialog
        serverId={message.server_id}
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
        serverId={message.server_id}
        file={{
            id: onlyofficeFile.id,
            name: onlyofficeFile.original_name,
            mimeType: onlyofficeFile.mime_type,
        }}
        onClose={() => (onlyofficeFile = null)}
    />
{/if}

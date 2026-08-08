<script lang="ts">
    import {
        Check,
        Download,
        Eye,
        FileSpreadsheet,
        FileText,
        FileType2,
        Loader2,
        MessageSquare,
        Paperclip,
        Pencil,
        Trash2,
        X,
    } from 'lucide-svelte';
    import EmojiPicker from '@/components/discord/EmojiPicker.svelte';
    import MessageMarkdown from '@/components/discord/MessageMarkdown.svelte';
    import StampReaction from '@/components/discord/StampReaction.svelte';
    import OnlyOfficePreviewDialog from '@/components/files/OnlyOfficePreviewDialog.svelte';
    import StoredFilePreviewDialog, {
        canPreviewStoredFile,
    } from '@/components/files/StoredFilePreviewDialog.svelte';
    import { Avatar, AvatarFallback } from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { currentLocale, formatDate, formatTime } from '@/lib/dates';
    import { HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import {
        restoreDraftMentions,
        serializeDraftMentions,
        updateMentionAnchors,
    } from '@/lib/mentions';
    import type { MentionAnchor } from '@/lib/mentions';
    import { isStampReaction, reactionDisplayLabel } from '@/lib/reactions';
    import { cn } from '@/lib/utils';
    import type {
        MessageReactionResource,
        MessageResource,
        StoredFileResource,
    } from '@/types';

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
    const avatarToneCount = 8;

    let {
        message,
        onOpenThread,
        canEdit = false,
        canDelete = false,
        onEdit,
        onDelete,
        onSetReaction,
        currentUserId,
        highlighted = false,
    }: {
        message: MessageResource;
        onOpenThread?: () => void;
        canEdit?: boolean;
        canDelete?: boolean;
        onEdit?: (body: string) => Promise<void>;
        onDelete?: () => Promise<void>;
        onSetReaction?: (emoji: string, reacted: boolean) => Promise<void>;
        currentUserId?: number | null;
        highlighted?: boolean;
    } = $props();

    let editing = $state(false);
    let editBody = $state('');
    let editPreviousBody = '';
    let editMentionAnchors = $state<MentionAnchor[]>([]);
    let saving = $state(false);
    let deleting = $state(false);
    let actionError = $state('');
    let previewFile = $state<StoredFileResource | null>(null);
    let onlyofficeFile = $state<StoredFileResource | null>(null);
    let reactingEmojis = $state<string[]>([]);
    const authorName = $derived(message.user?.name?.trim() || t('Unknown'));
    const authorInitial = $derived(getAuthorInitial(authorName));
    const authorAvatarTone = $derived(getAvatarTone(authorInitial));

    function startEditing() {
        const restored = restoreDraftMentions(
            message.body,
            message.mentions ?? [],
        );

        editBody = restored.value;
        editPreviousBody = restored.value;
        editMentionAnchors = restored.anchors;
        actionError = '';
        editing = true;
    }

    function handleEditInput(event: Event) {
        const nextBody = (event.currentTarget as HTMLTextAreaElement).value;

        editMentionAnchors = updateMentionAnchors(
            editPreviousBody,
            nextBody,
            editMentionAnchors,
        );
        editPreviousBody = nextBody;
        editBody = nextBody;
    }

    async function saveEdit() {
        const body = serializeDraftMentions(
            editBody,
            editMentionAnchors,
        ).trim();

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
                    : t('Failed to edit message');
        } finally {
            saving = false;
        }
    }

    async function removeMessage() {
        if (
            !onDelete ||
            deleting ||
            !window.confirm(t('Delete this message?'))
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
                    : t('Failed to delete message');
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

    function getAuthorInitial(name: string): string {
        return (Array.from(name)[0] ?? '?').toLocaleUpperCase();
    }

    function getAvatarTone(initial: string): string {
        const hash = Array.from(initial).reduce(
            (value, character) =>
                (value * 31 + (character.codePointAt(0) ?? 0)) >>> 0,
            0,
        );

        return String(hash % avatarToneCount);
    }

    function openPreview(file: StoredFileResource) {
        if (isOffice(file)) {
            onlyofficeFile = file;
        } else {
            previewFile = file;
        }
    }

    function reactedByCurrentUser(reaction: MessageReactionResource): boolean {
        return currentUserId !== null && currentUserId !== undefined
            ? reaction.user_ids.includes(currentUserId)
            : false;
    }

    function reactionTitle(reaction: MessageReactionResource): string {
        const names = new Intl.ListFormat(currentLocale(), {
            style: 'long',
            type: 'conjunction',
        }).format(reaction.user_names);
        const label = reactionDisplayLabel(reaction.emoji);

        return names
            ? t(':names reacted with :reaction', { names, reaction: label })
            : t(':reaction reaction', { reaction: label });
    }

    async function setReaction(emoji: string, reacted: boolean) {
        if (!onSetReaction || reactingEmojis.includes(emoji)) {
            return;
        }

        const existing = message.reactions?.find(
            (reaction) => reaction.emoji === emoji,
        );

        if (reacted && existing && reactedByCurrentUser(existing)) {
            return;
        }

        reactingEmojis = [...reactingEmojis, emoji];
        actionError = '';

        try {
            await onSetReaction(emoji, reacted);
        } catch (error) {
            actionError =
                error instanceof HttpError
                    ? error.messageText()
                    : t('Failed to update reaction');
        } finally {
            reactingEmojis = reactingEmojis.filter((item) => item !== emoji);
        }
    }
</script>

<div
    data-message-id={message.id}
    class={cn(
        'group relative flex w-full gap-3 rounded-md px-2 py-1.5 transition hover:bg-accent/40',
        highlighted && 'bg-brand/20 ring-1 ring-brand/60',
    )}
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
                    title={t('Edit message')}
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
                    title={t('Delete message')}
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

    <Avatar
        data-message-avatar
        data-avatar-tone={authorAvatarTone}
        aria-label={t('Avatar for :name', { name: authorName })}
        class="size-9"
    >
        <AvatarFallback data-message-avatar-fallback>
            {authorInitial}
        </AvatarFallback>
    </Avatar>
    <div data-message-content class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2">
            <span class="text-sm font-semibold text-foreground"
                >{authorName}</span
            >
            <time
                datetime={message.created_at}
                title={`${formatDate(message.created_at, { month: 'long' })} ${formatTime(message.created_at)}`}
                class="text-xs text-muted-foreground"
                >{formatDate(message.created_at, { month: 'long' })}
                {formatTime(message.created_at)}</time
            >
        </div>
        {#if editing}
            <div class="mt-1 w-full">
                <textarea
                    bind:value={editBody}
                    rows={3}
                    class="max-h-48 min-h-20 w-full resize-y rounded-md bg-[#383a40] px-3 py-2 text-[15px] text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    oninput={handleEditInput}
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
                        {t('Save')}
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded px-2 py-1 text-xs text-[#b5bac1] transition hover:bg-white/10 hover:text-[#dbdee1]"
                        onclick={() => (editing = false)}
                    >
                        <X class="h-3 w-3" />
                        {t('Cancel')}
                    </button>
                </div>
            </div>
        {:else}
            <!-- Markdown escapes user input; Shiki only replaces fenced code blocks. -->
            <div
                data-message-body
                class="break-words text-sm leading-5 text-foreground [&_.mention]:rounded [&_.mention]:px-1 [&_.mention]:font-medium [&_.mention-direct]:bg-mention-direct-background [&_.mention-direct]:text-mention-direct-foreground [&_.mention-everyone]:bg-mention-everyone-background [&_.mention-everyone]:text-mention-everyone-foreground [&_.mention-self]:ring-1 [&_.mention-self]:ring-brand-accent [&_a]:text-brand-accent [&_a]:underline [&_blockquote]:my-1 [&_blockquote]:border-l-4 [&_blockquote]:border-current/30 [&_blockquote]:pl-3 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_pre]:m-0 [&_pre]:overflow-x-auto [&_pre]:rounded-none [&_pre]:border-0 [&_pre]:bg-code-block [&_pre]:p-2 [&_pre]:text-[13px] [&_pre]:leading-[1.45] [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-6"
            >
                <MessageMarkdown
                    value={message.body}
                    mentions={message.mentions ?? []}
                    {currentUserId}
                />
            </div>
        {/if}

        {#if actionError}
            <p class="mt-1 text-xs text-red-400" role="alert">
                {actionError}
            </p>
        {/if}

        {#if message.attachments && message.attachments.length > 0}
            <div
                data-attachment-list
                class="mt-2 flex flex-wrap items-start gap-2"
            >
                {#each message.attachments as file (file.id)}
                    {@const typeIcon = fileTypeIcon(file)}
                    {#if isImage(file)}
                        <button
                            type="button"
                            aria-label={t('Preview :name', {
                                name: file.original_name,
                            })}
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
                            class="relative w-72 overflow-hidden rounded-lg border border-border bg-card text-card-foreground"
                        >
                            <button
                                type="button"
                                aria-label={t('Preview :name', {
                                    name: file.original_name,
                                })}
                                onclick={() => openPreview(file)}
                                class="group/preview block w-full text-left transition hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
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
                                    <span class="min-w-0 flex-1 truncate"
                                        >{file.original_name}</span
                                    >
                                    <span
                                        data-preview-hint
                                        class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-brand-accent"
                                    >
                                        <Eye
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {t('Preview')}
                                    </span>
                                </span>
                            </button>
                            <a
                                href={file.download_url ?? '#'}
                                aria-label={t('Download :name', {
                                    name: file.original_name,
                                })}
                                class="absolute bottom-1.5 right-1.5 rounded p-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                            >
                                <Download class="h-3.5 w-3.5" />
                            </a>
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

        {#if onSetReaction}
            <div
                class="mt-2 flex min-h-7 flex-wrap items-center gap-1.5"
                data-message-reactions
                aria-label={t('Message reactions')}
            >
                {#each message.reactions ?? [] as reaction (reaction.emoji)}
                    {@const reacted = reactedByCurrentUser(reaction)}
                    <Button
                        variant={reacted ? 'secondary' : 'outline'}
                        size="sm"
                        class="h-7 gap-1.5 rounded-full px-2.5 text-sm"
                        aria-label={t(
                            reacted
                                ? ':reaction reaction (:count), added by you'
                                : ':reaction reaction (:count)',
                            {
                                reaction: reactionDisplayLabel(reaction.emoji),
                                count: String(reaction.count),
                            },
                        )}
                        aria-pressed={reacted}
                        title={reactionTitle(reaction)}
                        disabled={reactingEmojis.includes(reaction.emoji)}
                        onclick={() => setReaction(reaction.emoji, !reacted)}
                    >
                        {#if isStampReaction(reaction.emoji)}
                            <StampReaction
                                value={reaction.emoji}
                                size="reaction"
                            />
                        {:else}
                            <span
                                class="text-base leading-none"
                                aria-hidden="true">{reaction.emoji}</span
                            >
                        {/if}
                        <span class="min-w-2 text-center text-sm tabular-nums"
                            >{reaction.count}</span
                        >
                    </Button>
                {/each}
                <EmojiPicker
                    mode="reaction"
                    align="start"
                    onselect={(emoji) => setReaction(emoji, true)}
                />
            </div>
        {/if}

        {#if onOpenThread}
            <button
                type="button"
                class="mt-1.5 flex items-center gap-1.5 rounded px-1.5 py-0.5 text-xs font-medium text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1]"
                onclick={onOpenThread}
            >
                <MessageSquare class="h-3.5 w-3.5" />
                {t('Thread')}
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

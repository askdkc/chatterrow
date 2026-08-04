<script lang="ts">
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        AtSign,
        Bold,
        Hash,
        CalendarRange,
        Code,
        Italic,
        FileText,
        Link2,
        List as ListIcon,
        ListOrdered,
        ListTodo,
        Loader2,
        MessageSquare,
        Paperclip,
        Plus,
        Send,
        SquareCode,
        Strikethrough,
        TextQuote,
        Underline,
        X,
    } from 'lucide-svelte';
    import { onMount } from 'svelte';
    import ChannelDialog from '@/components/discord/ChannelDialog.svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import EmojiPicker from '@/components/discord/EmojiPicker.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import MessageItem from '@/components/discord/MessageItem.svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import TodoPanel from '@/components/discord/TodoPanel.svelte';
    import { Button } from '@/components/ui/button';
    import { filesFromDrop } from '@/lib/dropped-files';
    import { getEcho } from '@/lib/echo';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import {
        findMentionQuery,
        replaceMentionRange,
        safeMentionText,
        serializeDraftMentions,
        updateMentionAnchors,
    } from '@/lib/mentions';
    import type { MentionAnchor, MentionCandidate } from '@/lib/mentions';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type {
        ServerResource,
        ChannelResource,
        MessageResource,
        UserResource,
        StoredFileResource,
        TodoResource,
    } from '@/types';

    let {
        server,
        channel,
        initialMessages,
        members,
        focus_message_id = null,
        open_thread_parent_id = null,
    }: {
        server: ServerResource;
        channel: ChannelResource;
        initialMessages: MessageResource[];
        members: UserResource[];
        focus_message_id?: number | null;
        open_thread_parent_id?: number | null;
    } = $props();

    const page = usePage();

    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );
    const currentUserId = $derived(page.props.auth?.user?.id);
    let messages: MessageResource[] = $derived(initialMessages);
    const threadMessages = $derived(
        messages.filter((message) => (message.reply_count ?? 0) > 0),
    );
    let draft = $state('');
    let draftBeforeInput = '';
    let draftMentionAnchors = $state<MentionAnchor[]>([]);
    let mentionQuery = $state('');
    let mentionMenuOpen = $state(false);
    let mentionCandidateIndex = $state(0);
    let mentionRange = $state<{ start: number; end: number } | null>(null);
    let mentionMenuPosition = $state({ left: 8, bottom: 80 });
    let composing = $state(false);
    let threadParent: MessageResource | null = $state(null);
    let threadReplies: MessageResource[] = $state([]);
    let loadingThread = $state(false);
    let threadError = $state('');
    let sending = $state(false);
    let composerExpanded = $state(false);
    let emojiPickerOpen = $state(false);
    let showFormatting = $state(true);
    let dragActive = $state(false);
    let pendingFiles: StoredFileResource[] = $state([]);
    let pendingUploadCount = $state(0);
    let showChannelDialog = $state(false);
    let editingChannel = $state<ChannelResource | null>(null);
    let showMemberDialog = $state(false);
    let showServerDialog = $state(false);
    let todos = $state<TodoResource[]>([]);
    let showTodos = $state(true);
    let sendError = $state('');
    let uploadError = $state('');
    let messagesEnd: HTMLDivElement;
    let fileInput = $state<HTMLInputElement>();
    let composer = $state<HTMLTextAreaElement>();
    let composerShell: HTMLDivElement | undefined;
    let isMac = $state(false);
    let highlightedMessageId = $state<number | null>(null);
    let focusRequestStarted = false;
    let focusTargetHandled = false;
    let focusHighlightTimer: ReturnType<typeof setTimeout> | undefined;
    const seenReplyIds: number[] = [];

    const channelId = $derived(channel.id);
    const serverId = $derived(server.id);
    const mentionCandidates = $derived.by((): MentionCandidate[] => {
        const query = mentionQuery.trim().toLocaleLowerCase();
        const everyone: MentionCandidate = {
            id: null,
            kind: 'everyone',
            name: 'everyone',
            email: '全メンバー',
        };
        const candidates: MentionCandidate[] = [
            everyone,
            ...members.map((member) => ({
                id: member.id,
                kind: 'direct' as const,
                name: member.name,
                email: member.email,
            })),
        ];

        if (!query) {
            return candidates;
        }

        return candidates.filter((candidate) =>
            `${candidate.name} ${candidate.email}`
                .toLocaleLowerCase()
                .includes(query),
        );
    });
    const mentionMenuStyle = $derived(
        `left: ${mentionMenuPosition.left}px; bottom: ${mentionMenuPosition.bottom}px; width: min(22rem, calc(100vw - 1rem));`,
    );
    const sendShortcutModifier = $derived(isMac ? '⌘' : 'Ctrl');
    const maxPendingFiles = 10;

    async function loadTodos() {
        const data = await apiJson<{ todos: TodoResource[] }>(
            `/servers/${serverId}/channels/${channelId}/todos`,
        );
        todos = data.todos;
    }

    onMount(() => {
        isMac = /Mac|iPhone|iPad|iPod/i.test(
            `${navigator.platform} ${navigator.userAgent}`,
        );
        loadTodos();
        scrollToBottom();
        const echo = getEcho();
        const broadcastChannel = echo.private(
            `server.${serverId}.channel.${channelId}`,
        );

        broadcastChannel.listen(
            '.MessageCreated',
            (e: { message: MessageResource }) => {
                appendMessage(e.message);
            },
        );
        broadcastChannel.listen(
            '.ReminderCreated',
            (e: { message: MessageResource }) => {
                appendMessage(e.message);
            },
        );
        broadcastChannel.listen('.TodoUpdated', (e: { todo: TodoResource }) => {
            upsertTodo(e.todo);
        });
        broadcastChannel.listen(
            '.MessageReactionUpdated',
            (e: { message: MessageResource }) => {
                applyMessageUpdate(e.message);
            },
        );

        const onKeydown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                closeThread();
            }
        };
        window.addEventListener('keydown', onKeydown);

        return () => {
            broadcastChannel.stopListening('.MessageCreated');
            broadcastChannel.stopListening('.ReminderCreated');
            broadcastChannel.stopListening('.TodoUpdated');
            broadcastChannel.stopListening('.MessageReactionUpdated');
            echo.leaveChannel(
                `private-server-${serverId}-channel-${channelId}`,
            );
            window.removeEventListener('keydown', onKeydown);

            if (focusHighlightTimer) {
                clearTimeout(focusHighlightTimer);
            }
        };
    });

    $effect(() => {
        const focusId = focus_message_id;
        const parentId = open_thread_parent_id;

        if (!focusId || focusRequestStarted) {
            return;
        }

        if (parentId) {
            const parent = messages.find((message) => message.id === parentId);

            if (!parent) {
                return;
            }

            focusRequestStarted = true;
            void openThread(parent);

            return;
        }

        focusRequestStarted = true;
        focusMessageElement(focusId);
    });

    $effect(() => {
        const focusId = focus_message_id;
        const parentId = open_thread_parent_id;

        if (
            !focusId ||
            !parentId ||
            !threadParent ||
            threadParent.id !== parentId ||
            focusTargetHandled ||
            !threadReplies.some((reply) => reply.id === focusId)
        ) {
            return;
        }

        focusMessageElement(focusId);
    });

    function appendMessage(message: MessageResource) {
        if (message.channel_id !== channelId) {
            return;
        }

        if (message.parent_id) {
            if (seenReplyIds.includes(message.id)) {
                return;
            }

            seenReplyIds.push(message.id);
            messages = messages.map((m) =>
                m.id === message.parent_id
                    ? { ...m, reply_count: (m.reply_count ?? 0) + 1 }
                    : m,
            );

            if (threadParent?.id === message.parent_id) {
                threadParent = {
                    ...threadParent,
                    reply_count: (threadParent.reply_count ?? 0) + 1,
                };
                threadReplies = [...threadReplies, message];
                scrollToBottom();
            }

            return;
        }

        if (!messages.some((m) => m.id === message.id)) {
            messages = [...messages, message];
        }

        scrollToBottom();
    }

    function upsertTodo(todo: TodoResource) {
        todos = todos.some((t) => t.id === todo.id)
            ? todos.map((t) => (t.id === todo.id ? todo : t))
            : [...todos, todo];
    }

    function applyMessageUpdate(updated: MessageResource) {
        messages = messages.map((message) =>
            message.id === updated.id ? { ...message, ...updated } : message,
        );
        threadReplies = threadReplies.map((message) =>
            message.id === updated.id ? { ...message, ...updated } : message,
        );

        if (threadParent?.id === updated.id) {
            threadParent = { ...threadParent, ...updated };
        }
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            messagesEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' });
        });
    }

    function focusMessageElement(messageId: number) {
        requestAnimationFrame(() => {
            const element = document.querySelector<HTMLElement>(
                `[data-message-id="${messageId}"]`,
            );

            if (!element) {
                return;
            }

            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            highlightedMessageId = messageId;
            focusTargetHandled = true;

            if (focusHighlightTimer) {
                clearTimeout(focusHighlightTimer);
            }

            focusHighlightTimer = setTimeout(() => {
                if (highlightedMessageId === messageId) {
                    highlightedMessageId = null;
                }
            }, 3000);
        });
    }

    async function sendMessage() {
        const body = serializeDraftMentions(draft, draftMentionAnchors).trim();

        if (
            sending ||
            composing ||
            pendingUploadCount > 0 ||
            (!body && pendingFiles.length === 0)
        ) {
            return;
        }

        sending = true;
        sendError = '';

        try {
            const data = await apiJson<{ message: MessageResource }>(
                `/servers/${serverId}/channels/${channelId}/messages`,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        body,
                        parent_id: threadParent?.id ?? null,
                        attachments: pendingFiles.map((f) => ({
                            path: f.path,
                            original_name: f.original_name,
                            mime_type: f.mime_type,
                            size: f.size,
                        })),
                    }),
                },
            );

            appendMessage(data.message);
            draft = '';
            draftBeforeInput = '';
            draftMentionAnchors = [];
            closeMentionMenu();
            pendingFiles = [];
            emojiPickerOpen = false;
            composerExpanded = false;
            resetComposerHeight();
        } catch (e) {
            sendError =
                e instanceof HttpError ? e.messageText() : '送信に失敗しました';
        } finally {
            sending = false;
        }
    }

    async function onFilesPicked(fileList: FileList | File[] | null) {
        if (!fileList || fileList.length === 0) {
            return;
        }

        const availableSlots = Math.max(
            maxPendingFiles - pendingFiles.length - pendingUploadCount,
            0,
        );
        const selectedFiles = Array.from(fileList).slice(0, availableSlots);

        uploadError =
            selectedFiles.length < fileList.length
                ? `添付できるファイルは${maxPendingFiles}件までです`
                : '';

        if (selectedFiles.length === 0) {
            return;
        }

        pendingUploadCount += selectedFiles.length;

        try {
            for (let index = 0; index < selectedFiles.length; index += 10) {
                const form = new FormData();

                for (const file of selectedFiles.slice(index, index + 10)) {
                    form.append('files[]', file);
                }

                const response = await apiFetch(`/servers/${serverId}/files`, {
                    method: 'POST',
                    body: form,
                });
                const json = (await response.json()) as {
                    files: StoredFileResource[];
                };
                pendingFiles = [...pendingFiles, ...json.files];
            }
        } catch (e) {
            uploadError =
                e instanceof HttpError
                    ? e.messageText()
                    : 'アップロードに失敗しました';
        } finally {
            pendingUploadCount = Math.max(
                pendingUploadCount - selectedFiles.length,
                0,
            );
        }
    }

    async function onFileInputChange(event: Event) {
        const input = event.currentTarget as HTMLInputElement;

        await onFilesPicked(input.files);
        input.value = '';
    }

    function onComposerPaste(event: ClipboardEvent) {
        if (!event.clipboardData) {
            return;
        }

        const itemImages = Array.from(event.clipboardData.items)
            .filter(
                (item) =>
                    item.kind === 'file' && item.type.startsWith('image/'),
            )
            .map((item) => item.getAsFile())
            .filter((file): file is File => file !== null);
        const imageFiles = (
            itemImages.length > 0
                ? itemImages
                : Array.from(event.clipboardData.files)
        ).filter((file) => file.type.startsWith('image/'));

        if (imageFiles.length === 0) {
            return;
        }

        event.preventDefault();
        void onFilesPicked(imageFiles);
    }

    function isImageFile(file: StoredFileResource): boolean {
        return (file.mime_type ?? '').startsWith('image/');
    }

    function pendingFileStreamUrl(file: StoredFileResource): string {
        return (
            file.stream_url ?? `/servers/${serverId}/files/${file.id}/stream`
        );
    }

    async function removePendingFile(file: StoredFileResource) {
        pendingFiles = pendingFiles.filter((item) => item.id !== file.id);

        try {
            await apiFetch(`/servers/${serverId}/files/${file.id}`, {
                method: 'DELETE',
            });
        } catch (e) {
            pendingFiles = [...pendingFiles, file];
            uploadError =
                e instanceof HttpError ? e.messageText() : '削除に失敗しました';
        }
    }

    async function onDrop(e: DragEvent) {
        e.preventDefault();
        dragActive = false;

        if (!e.dataTransfer) {
            return;
        }

        uploadError = '';

        try {
            await onFilesPicked(await filesFromDrop(e.dataTransfer));
        } catch {
            uploadError = 'フォルダの読み込みに失敗しました';
        }
    }

    function onComposerKeydown(e: KeyboardEvent) {
        if (e.isComposing || composing) {
            return;
        }

        if (mentionMenuOpen && e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeMentionMenu();

            return;
        }

        if (mentionMenuOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            if (mentionCandidates.length === 0) {
                return;
            }

            e.preventDefault();
            mentionCandidateIndex =
                e.key === 'ArrowDown'
                    ? (mentionCandidateIndex + 1) % mentionCandidates.length
                    : (mentionCandidateIndex - 1 + mentionCandidates.length) %
                      mentionCandidates.length;

            return;
        }

        if (
            mentionMenuOpen &&
            (e.key === 'Enter' || e.key === 'Tab') &&
            mentionCandidates[mentionCandidateIndex]
        ) {
            e.preventDefault();
            selectMentionCandidate(mentionCandidates[mentionCandidateIndex]);

            return;
        }

        if (e.key !== 'Enter' || (!e.metaKey && !e.ctrlKey)) {
            return;
        }

        e.preventDefault();
        sendMessage();
    }

    function handleComposerInput(event: Event) {
        const nextDraft = (event.currentTarget as HTMLTextAreaElement).value;

        draftMentionAnchors = updateMentionAnchors(
            draftBeforeInput,
            nextDraft,
            draftMentionAnchors,
        );
        draftBeforeInput = nextDraft;
        draft = nextDraft;
        resizeComposer();
        updateMentionContext();
    }

    function updateMentionMenuPosition() {
        if (!composer || typeof window === 'undefined') {
            return;
        }

        const rectangle = composer.getBoundingClientRect();
        const width = Math.min(352, window.innerWidth - 16);

        mentionMenuPosition = {
            left: Math.max(
                8,
                Math.min(rectangle.left, window.innerWidth - width - 8),
            ),
            bottom: Math.max(8, window.innerHeight - rectangle.top + 8),
        };
    }

    function updateMentionContext() {
        if (!composer) {
            return;
        }

        const query = findMentionQuery(draft, composer.selectionStart);

        if (!query) {
            closeMentionMenu();

            return;
        }

        if (query.query !== mentionQuery) {
            mentionCandidateIndex = 0;
        }

        mentionQuery = query.query;
        mentionRange = { start: query.start, end: query.end };
        mentionMenuOpen = true;
        updateMentionMenuPosition();
    }

    function closeMentionMenu() {
        mentionMenuOpen = false;
        mentionQuery = '';
        mentionRange = null;
        mentionCandidateIndex = 0;
    }

    function openMentionCandidates() {
        if (!composer) {
            return;
        }

        const start = composer.selectionStart;
        replaceComposerSelection('@', 1, 1);
        mentionQuery = '';
        mentionRange = { start, end: start + 1 };
        mentionCandidateIndex = 0;
        mentionMenuOpen = true;
        updateMentionMenuPosition();
    }

    function selectMentionCandidate(candidate: MentionCandidate) {
        if (!mentionRange || composing) {
            return;
        }

        const result = replaceMentionRange(
            draft,
            mentionRange,
            candidate,
            draftMentionAnchors,
        );
        draft = result.value;
        draftBeforeInput = result.value;
        draftMentionAnchors = result.anchors;
        closeMentionMenu();

        requestAnimationFrame(() => {
            composer?.focus();
            composer?.setSelectionRange(result.cursor, result.cursor);
            resizeComposer();
        });
    }

    function onCompositionStart() {
        composing = true;
    }

    function onCompositionEnd() {
        composing = false;
        updateMentionContext();
    }

    function resizeComposer() {
        if (!composer) {
            return;
        }

        composer.style.height = 'auto';
        composer.style.height = `${Math.min(composer.scrollHeight, 192)}px`;
        composer.style.overflowY =
            composer.scrollHeight > 192 ? 'auto' : 'hidden';
    }

    function resetComposerHeight() {
        requestAnimationFrame(resizeComposer);
    }

    function expandComposer() {
        composerExpanded = true;
    }

    function preserveComposerFocus(node: HTMLElement) {
        function handleMouseDown(event: MouseEvent) {
            if (
                event.button !== 0 ||
                !(event.target instanceof Element) ||
                !event.target.closest('button')
            ) {
                return;
            }

            event.preventDefault();
        }

        node.addEventListener('mousedown', handleMouseDown);

        return {
            destroy() {
                node.removeEventListener('mousedown', handleMouseDown);
            },
        };
    }

    function collapseComposerIfIdle() {
        requestAnimationFrame(() => {
            if (
                !emojiPickerOpen &&
                !mentionMenuOpen &&
                pendingFiles.length === 0 &&
                pendingUploadCount === 0 &&
                !composerShell?.contains(document.activeElement)
            ) {
                composerExpanded = false;
            }
        });
    }

    function replaceComposerSelection(
        replacement: string,
        selectionStart: number,
        selectionEnd: number,
    ) {
        if (!composer) {
            return;
        }

        const start = composer.selectionStart;
        const end = composer.selectionEnd;
        const nextDraft = `${draft.slice(0, start)}${replacement}${draft.slice(end)}`;
        draftMentionAnchors = updateMentionAnchors(
            draft,
            nextDraft,
            draftMentionAnchors,
        );
        draftBeforeInput = nextDraft;
        draft = nextDraft;

        requestAnimationFrame(() => {
            composer?.focus();
            composer?.setSelectionRange(
                start + selectionStart,
                start + selectionEnd,
            );
            resizeComposer();
        });
    }

    function wrapComposerSelection(
        prefix: string,
        suffix = prefix,
        placeholder = 'テキスト',
    ) {
        if (!composer) {
            return;
        }

        const selected =
            draft.slice(composer.selectionStart, composer.selectionEnd) ||
            placeholder;
        const replacement = `${prefix}${selected}${suffix}`;

        replaceComposerSelection(
            replacement,
            prefix.length,
            prefix.length + selected.length,
        );
    }

    function prefixComposerLines(prefix: string | ((index: number) => string)) {
        if (!composer) {
            return;
        }

        const selected =
            draft.slice(composer.selectionStart, composer.selectionEnd) ||
            'リスト項目';
        const replacement = selected
            .split('\n')
            .map(
                (line, index) =>
                    `${typeof prefix === 'function' ? prefix(index) : prefix}${line}`,
            )
            .join('\n');

        replaceComposerSelection(replacement, 0, replacement.length);
    }

    function insertComposerLink() {
        if (!composer) {
            return;
        }

        const label =
            draft.slice(composer.selectionStart, composer.selectionEnd) ||
            'リンクテキスト';
        const replacement = `[${label}](https://)`;
        const urlStart = label.length + 3;

        replaceComposerSelection(
            replacement,
            urlStart,
            urlStart + 'https://'.length,
        );
    }

    function insertComposerText(value: string) {
        replaceComposerSelection(value, value.length, value.length);
    }

    async function openThread(message: MessageResource) {
        threadParent = message;
        threadReplies = [];
        threadError = '';
        loadingThread = true;

        try {
            const data = await apiJson<{ messages: MessageResource[] }>(
                `/servers/${serverId}/channels/${channelId}/messages?parent_id=${message.id}`,
            );

            if (threadParent?.id !== message.id) {
                return;
            }

            const loadedIds = data.messages.map((reply) => reply.id);
            threadReplies = [
                ...data.messages,
                ...threadReplies.filter(
                    (reply) => !loadedIds.includes(reply.id),
                ),
            ];
            data.messages.forEach((reply) => {
                if (!seenReplyIds.includes(reply.id)) {
                    seenReplyIds.push(reply.id);
                }
            });
            scrollToBottom();
        } catch (error) {
            if (threadParent?.id === message.id) {
                threadError =
                    error instanceof HttpError
                        ? error.messageText()
                        : 'スレッドの読み込みに失敗しました';
            }
        } finally {
            if (threadParent?.id === message.id) {
                loadingThread = false;
            }
        }
    }

    function closeThread() {
        threadParent = null;
        threadReplies = [];
        threadError = '';
        loadingThread = false;
    }

    async function editMessage(message: MessageResource, body: string) {
        const data = await apiJson<{ message: MessageResource }>(
            `/servers/${serverId}/channels/${channelId}/messages/${message.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({ body }),
            },
        );

        applyMessageUpdate(data.message);
    }

    async function setMessageReaction(
        message: MessageResource,
        emoji: string,
        reacted: boolean,
    ) {
        const data = await apiJson<{ message: MessageResource }>(
            `/servers/${serverId}/channels/${channelId}/messages/${message.id}/reactions`,
            {
                method: reacted ? 'PUT' : 'DELETE',
                body: JSON.stringify({ emoji }),
            },
        );

        applyMessageUpdate(data.message);
    }

    async function deleteMessage(message: MessageResource) {
        await apiFetch(
            `/servers/${serverId}/channels/${channelId}/messages/${message.id}`,
            { method: 'DELETE' },
        );

        if (message.parent_id) {
            messages = messages.map((item) =>
                item.id === message.parent_id
                    ? {
                          ...item,
                          reply_count: Math.max((item.reply_count ?? 1) - 1, 0),
                      }
                    : item,
            );
            threadReplies = threadReplies.filter(
                (item) => item.id !== message.id,
            );

            if (threadParent?.id === message.parent_id) {
                threadParent = {
                    ...threadParent,
                    reply_count: Math.max(
                        (threadParent.reply_count ?? 1) - 1,
                        0,
                    ),
                };
            }

            return;
        }

        messages = messages.filter((item) => item.id !== message.id);

        if (threadParent?.id === message.id) {
            closeThread();
        }
    }

    async function editActiveThread(body: string) {
        if (threadParent) {
            await editMessage(threadParent, body);
        }
    }

    async function deleteActiveThread() {
        if (threadParent) {
            await deleteMessage(threadParent);
        }
    }

    function canEditMessage(message: MessageResource): boolean {
        return currentUserId === message.user_id;
    }

    function canDeleteMessage(message: MessageResource): boolean {
        return (
            currentUserId === message.user_id ||
            isProjectAdministrator(server, members, currentUserId)
        );
    }

    function onAddServer() {
        showServerDialog = true;
    }

    function onEditChannel(channelToEdit: ChannelResource) {
        editingChannel = channelToEdit;
        showChannelDialog = false;
    }

    function onBrowse() {
        router.visit('/servers');
    }
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={authServers}
        activeServerId={serverId}
        {onAddServer}
        {onBrowse}
    />

    <ChannelList
        {server}
        channels={server.channels ?? []}
        {members}
        activeChannelId={channelId}
        threads={threadMessages}
        activeThreadId={threadParent?.id ?? null}
        onAddChannel={() => (showChannelDialog = true)}
        {onEditChannel}
        onManageMembers={() => (showMemberDialog = true)}
        onOpenThread={openThread}
    />

    <main class="flex min-w-0 flex-1 flex-col">
        <!-- Channel header -->
        <header
            class="flex h-12 shrink-0 items-center gap-2 border-b border-black/10 px-4 shadow-sm dark:border-black/20"
        >
            <Hash class="h-5 w-5 text-[#80848e]" />
            <h1 class="text-[15px] font-bold text-[#dbdee1]">{channel.name}</h1>
            {#if channel.starts_on || channel.ends_on}
                <span
                    class="ml-2 rounded bg-[#f0b232]/20 px-2 py-0.5 text-xs text-[#f0b232]"
                >
                    {channel.starts_on ?? '?'} 〜 {channel.ends_on ?? '未定'}
                </span>
            {/if}
            <div class="ml-auto flex items-center gap-1">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium transition hover:bg-white/10"
                    onclick={() => (showTodos = !showTodos)}
                    title="タスク"
                >
                    <ListTodo class="h-4 w-4" />
                    タスク
                </button>
                <Link
                    href={`/servers/${serverId}/channels/${channelId}/gantt`}
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium transition hover:bg-white/10"
                    title="ガントチャート"
                >
                    <CalendarRange class="h-4 w-4" />
                    ガント
                </Link>
                <Link
                    href={`/servers/${serverId}/channels/${channelId}/files`}
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium transition hover:bg-white/10"
                    title="ファイル"
                >
                    <FileText class="h-4 w-4" />
                    ファイル
                </Link>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <section class="flex min-w-0 flex-1 flex-col" aria-label="チャット">
                <!-- Messages -->
                <div
                    role="region"
                    aria-label="メッセージとファイルドロップ領域"
                    ondragover={(e) => {
                        e.preventDefault();
                        dragActive = true;
                    }}
                    ondragleave={() => (dragActive = false)}
                    ondrop={onDrop}
                    class={`flex min-w-0 flex-1 flex-col overflow-y-auto px-4 ${
                        dragActive
                            ? 'bg-[#5865f2]/5 ring-2 ring-inset ring-[#5865f2]'
                            : ''
                    }`}
                >
                    {#if threadParent}
                        <div
                            class="flex items-center gap-2 border-b border-black/10 py-2 dark:border-black/20"
                        >
                            <MessageSquare class="h-4 w-4 text-[#80848e]" />
                            <span class="text-sm font-semibold">スレッド</span>
                            <span class="truncate text-sm text-[#80848e]">
                                {threadParent.user?.name}: {safeMentionText(
                                    threadParent.body,
                                    threadParent.mentions ?? [],
                                ).slice(0, 40)}
                            </span>
                            <button
                                type="button"
                                class="ml-auto rounded p-1 hover:bg-white/10"
                                onclick={closeThread}
                                title="スレッドを閉じる"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    {/if}

                    <div class="flex-1 py-2">
                        {#if threadParent}
                            <MessageItem
                                message={threadParent}
                                canEdit={canEditMessage(threadParent)}
                                canDelete={canDeleteMessage(threadParent)}
                                {currentUserId}
                                highlighted={highlightedMessageId ===
                                    threadParent.id}
                                onEdit={editActiveThread}
                                onDelete={deleteActiveThread}
                                onSetReaction={(emoji, reacted) =>
                                    setMessageReaction(
                                        threadParent!,
                                        emoji,
                                        reacted,
                                    )}
                            />
                            <div class="my-2 border-t border-white/5"></div>

                            {#if threadError}
                                <p
                                    class="px-2 py-3 text-sm text-red-400"
                                    role="alert"
                                >
                                    {threadError}
                                </p>
                            {:else if loadingThread}
                                <div
                                    class="flex items-center justify-center gap-2 py-8 text-sm text-[#80848e]"
                                >
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    スレッドを読み込み中
                                </div>
                            {:else}
                                {#each threadReplies as reply (reply.id)}
                                    <MessageItem
                                        message={reply}
                                        canEdit={canEditMessage(reply)}
                                        canDelete={canDeleteMessage(reply)}
                                        {currentUserId}
                                        highlighted={highlightedMessageId ===
                                            reply.id}
                                        onEdit={(body) =>
                                            editMessage(reply, body)}
                                        onDelete={() => deleteMessage(reply)}
                                        onSetReaction={(emoji, reacted) =>
                                            setMessageReaction(
                                                reply,
                                                emoji,
                                                reacted,
                                            )}
                                    />
                                {/each}
                                {#if threadReplies.length === 0}
                                    <div
                                        class="py-8 text-center text-sm text-[#80848e]"
                                    >
                                        返信はまだありません
                                    </div>
                                {/if}
                            {/if}
                        {:else}
                            {#each messages as message (message.id)}
                                <MessageItem
                                    {message}
                                    canEdit={canEditMessage(message)}
                                    canDelete={canDeleteMessage(message)}
                                    {currentUserId}
                                    highlighted={highlightedMessageId ===
                                        message.id}
                                    onOpenThread={() => openThread(message)}
                                    onEdit={(body) =>
                                        editMessage(message, body)}
                                    onDelete={() => deleteMessage(message)}
                                    onSetReaction={(emoji, reacted) =>
                                        setMessageReaction(
                                            message,
                                            emoji,
                                            reacted,
                                        )}
                                />
                            {/each}
                            {#if messages.length === 0}
                                <div
                                    class="flex h-full items-center justify-center text-sm text-[#80848e]"
                                >
                                    まだメッセージがありません
                                </div>
                            {/if}
                        {/if}
                    </div>
                    <div bind:this={messagesEnd}></div>
                </div>

                <!-- Composer -->
                <div class="shrink-0 px-4 pb-4">
                    {#if sendError}
                        <p
                            class="mb-2 text-xs text-red-400"
                            role="alert"
                            aria-live="assertive"
                        >
                            {sendError}
                        </p>
                    {/if}
                    {#if uploadError}
                        <p
                            class="mb-2 text-xs text-red-400"
                            role="alert"
                            aria-live="assertive"
                        >
                            {uploadError}
                        </p>
                    {/if}
                    <div
                        bind:this={composerShell}
                        use:preserveComposerFocus
                        role="group"
                        aria-label="メッセージ入力とファイルドロップ領域"
                        class="overflow-hidden rounded-xl border border-[#686a70] bg-[#383a40] shadow-sm transition focus-within:border-[#8b8d93] focus-within:ring-1 focus-within:ring-white/10"
                        ondragover={(e) => {
                            e.preventDefault();
                            dragActive = true;
                        }}
                        ondragleave={() => (dragActive = false)}
                        ondrop={onDrop}
                        onfocusout={collapseComposerIfIdle}
                    >
                        <input
                            bind:this={fileInput}
                            type="file"
                            multiple
                            class="hidden"
                            onchange={onFileInputChange}
                        />
                        {#if composerExpanded && showFormatting}
                            <div
                                class="flex min-h-10 items-center gap-1 overflow-x-auto px-2 pt-1 text-[#b5bac1]"
                                aria-label="書式設定"
                            >
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => wrapComposerSelection('**')}
                                    title="太字"
                                >
                                    <Bold class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => wrapComposerSelection('_')}
                                    title="斜体"
                                >
                                    <Italic class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => wrapComposerSelection('__')}
                                    title="下線"
                                >
                                    <Underline class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => wrapComposerSelection('~~')}
                                    title="取り消し線"
                                >
                                    <Strikethrough class="h-4 w-4" />
                                </button>
                                <span
                                    class="mx-0.5 h-5 w-px shrink-0 bg-white/10"
                                ></span>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={insertComposerLink}
                                    title="リンク"
                                >
                                    <Link2 class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() =>
                                        prefixComposerLines(
                                            (index) => `${index + 1}. `,
                                        )}
                                    title="番号付きリスト"
                                >
                                    <ListOrdered class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => prefixComposerLines('- ')}
                                    title="箇条書き"
                                >
                                    <ListIcon class="h-4 w-4" />
                                </button>
                                <span
                                    class="mx-0.5 h-5 w-px shrink-0 bg-white/10"
                                ></span>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => prefixComposerLines('> ')}
                                    title="引用"
                                >
                                    <TextQuote class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() => wrapComposerSelection('`')}
                                    title="インラインコード"
                                >
                                    <Code class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition hover:bg-white/10 hover:text-white"
                                    onclick={() =>
                                        wrapComposerSelection(
                                            '```\n',
                                            '\n```',
                                            'コード',
                                        )}
                                    title="コードブロック"
                                >
                                    <SquareCode class="h-4 w-4" />
                                </button>
                            </div>
                        {/if}
                        {#if pendingFiles.length > 0 || pendingUploadCount > 0}
                            <div
                                class="flex flex-wrap gap-2 px-3 pt-3"
                                aria-label="送信予定の添付ファイル"
                            >
                                {#each pendingFiles as file (file.path)}
                                    {#if isImageFile(file)}
                                        <figure
                                            class="group relative size-32 overflow-hidden rounded-lg border border-border bg-muted"
                                        >
                                            <img
                                                src={pendingFileStreamUrl(file)}
                                                alt={file.original_name}
                                                class="size-full object-cover"
                                            />
                                            <Button
                                                variant="secondary"
                                                size="icon"
                                                class="absolute right-1 top-1 size-7 opacity-90 shadow-sm transition-opacity group-hover:opacity-100"
                                                aria-label={`${file.original_name}の添付を削除`}
                                                title="添付を削除"
                                                onclick={() =>
                                                    removePendingFile(file)}
                                            >
                                                <X />
                                            </Button>
                                            <figcaption
                                                class="absolute inset-x-0 bottom-0 truncate bg-background/85 px-2 py-1 text-xs text-foreground"
                                            >
                                                {file.original_name}
                                            </figcaption>
                                        </figure>
                                    {:else}
                                        <div
                                            class="flex h-10 max-w-64 items-center gap-2 rounded-lg border border-border bg-muted px-2 text-sm text-foreground"
                                        >
                                            <Paperclip
                                                class="size-4 shrink-0"
                                            />
                                            <span
                                                class="min-w-0 flex-1 truncate"
                                            >
                                                {file.original_name}
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7 shrink-0"
                                                aria-label={`${file.original_name}の添付を削除`}
                                                title="添付を削除"
                                                onclick={() =>
                                                    removePendingFile(file)}
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    {/if}
                                {/each}
                                {#if pendingUploadCount > 0}
                                    <div
                                        class="flex h-10 items-center gap-2 rounded-lg border border-border bg-muted px-3 text-sm text-muted-foreground"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <Loader2 class="size-4 animate-spin" />
                                        {pendingUploadCount}件をアップロード中…
                                    </div>
                                {/if}
                            </div>
                        {/if}
                        <div
                            class={composerExpanded
                                ? ''
                                : 'flex h-12 items-center gap-1.5 px-2'}
                        >
                            {#if !composerExpanded}
                                <button
                                    type="button"
                                    class="rounded-full p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
                                    onclick={() => fileInput?.click()}
                                    title="ファイルを添付"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            {/if}
                            <textarea
                                bind:this={composer}
                                bind:value={draft}
                                rows={composerExpanded ? 3 : 1}
                                class={composerExpanded
                                    ? 'block max-h-48 min-h-20 w-full resize-none bg-transparent px-3 py-2 text-[15px] leading-6 text-[#dbdee1] outline-none placeholder:text-[#a5a7ad]'
                                    : 'h-9 min-w-0 flex-1 resize-none bg-transparent px-1 py-1.5 text-[15px] leading-6 text-[#dbdee1] outline-none placeholder:text-[#80848e]'}
                                placeholder={threadParent
                                    ? `「${threadParent.user?.name}」への返信`
                                    : 'メッセージを入力'}
                                onfocus={expandComposer}
                                oninput={handleComposerInput}
                                oncompositionstart={onCompositionStart}
                                oncompositionend={onCompositionEnd}
                                onkeydown={onComposerKeydown}
                                onpaste={onComposerPaste}
                            ></textarea>
                            {#if !composerExpanded}
                                <div class="hidden sm:block">
                                    <EmojiPicker
                                        bind:open={emojiPickerOpen}
                                        onselect={insertComposerText}
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-[#80848e] transition enabled:bg-[#5865f2] enabled:text-white enabled:shadow-sm enabled:hover:bg-[#4752c4] disabled:cursor-not-allowed disabled:bg-[#dfe1e5] disabled:text-[#6a6f78] dark:disabled:bg-[#404249] dark:disabled:text-[#b5bac1]"
                                    onclick={sendMessage}
                                    disabled={sending ||
                                        composing ||
                                        pendingUploadCount > 0 ||
                                        (!draft.trim() &&
                                            pendingFiles.length === 0)}
                                    title="送信"
                                >
                                    {#if sending}
                                        <Loader2 class="h-5 w-5 animate-spin" />
                                    {:else}
                                        <Send class="h-5 w-5" />
                                    {/if}
                                </button>
                            {/if}
                        </div>
                        {#if composerExpanded}
                            <div class="flex items-center gap-1 px-2 py-1.5">
                                <button
                                    type="button"
                                    class="rounded-full bg-white/5 p-2 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
                                    onclick={() => fileInput?.click()}
                                    title="ファイルを添付"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class={`rounded px-2 py-1 text-base font-medium underline decoration-2 underline-offset-4 transition hover:bg-white/10 hover:text-white ${
                                        showFormatting
                                            ? 'text-[#dbdee1]'
                                            : 'text-[#80848e]'
                                    }`}
                                    onclick={() =>
                                        (showFormatting = !showFormatting)}
                                    aria-pressed={showFormatting}
                                    aria-label="書式設定を切り替え"
                                    title="書式設定を切り替え"
                                >
                                    Aa
                                </button>
                                <EmojiPicker
                                    bind:open={emojiPickerOpen}
                                    align="start"
                                    alignOffset={-88}
                                    onselect={insertComposerText}
                                />
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
                                    onclick={openMentionCandidates}
                                    title="メンションを挿入"
                                >
                                    <AtSign class="h-4 w-4" />
                                </button>
                                <span
                                    class="mx-0.5 h-5 w-px shrink-0 bg-white/10"
                                ></span>
                                <span
                                    class="ml-auto hidden items-center gap-1.5 whitespace-nowrap text-[11px] text-[#6a6f78] sm:inline-flex dark:text-[#949ba4]"
                                    aria-label={`${sendShortcutModifier} + Enterで送信`}
                                >
                                    <kbd
                                        class="rounded border border-[#b5bac1] bg-[#dfe1e5] px-1.5 py-0.5 font-sans leading-none text-[#4e5058] dark:border-[#686a70] dark:bg-[#2b2d31] dark:text-[#dbdee1]"
                                        >{sendShortcutModifier}</kbd
                                    >
                                    <span>+</span>
                                    <kbd
                                        class="rounded border border-[#b5bac1] bg-[#dfe1e5] px-1.5 py-0.5 font-sans leading-none text-[#4e5058] dark:border-[#686a70] dark:bg-[#2b2d31] dark:text-[#dbdee1]"
                                        >Enter</kbd
                                    >
                                    <span>で送信</span>
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-[#80848e] transition enabled:bg-[#5865f2] enabled:text-white enabled:shadow-sm enabled:hover:bg-[#4752c4] disabled:cursor-not-allowed disabled:bg-[#dfe1e5] disabled:text-[#6a6f78] dark:disabled:bg-[#404249] dark:disabled:text-[#b5bac1]"
                                    onclick={sendMessage}
                                    disabled={sending ||
                                        composing ||
                                        pendingUploadCount > 0 ||
                                        (!draft.trim() &&
                                            pendingFiles.length === 0)}
                                    title="送信"
                                >
                                    {#if sending}
                                        <Loader2 class="h-5 w-5 animate-spin" />
                                    {:else}
                                        <Send class="h-5 w-5" />
                                    {/if}
                                </button>
                            </div>
                        {/if}
                    </div>
                </div>
            </section>

            <!-- Todo panel -->
            {#if showTodos}
                <TodoPanel
                    {todos}
                    {members}
                    {serverId}
                    {channelId}
                    channelStartsOn={channel.starts_on}
                />
            {/if}
        </div>
    </main>

    {#if mentionMenuOpen}
        <div
            class="pointer-events-auto fixed z-[60] max-h-[min(20rem,calc(100vh-1rem))] overflow-y-auto rounded-xl border border-white/10 bg-[#2b2d31] p-1 text-[#dbdee1] shadow-2xl"
            style={mentionMenuStyle}
            role="listbox"
            tabindex="-1"
            aria-label="メンション候補"
            onpointerdown={(event) => event.preventDefault()}
        >
            <div
                class="px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#80848e]"
            >
                メンション候補{mentionQuery ? `「${mentionQuery}」` : ''}
            </div>
            {#if mentionCandidates.length > 0}
                {#each mentionCandidates as candidate, index (`${candidate.kind}-${candidate.id ?? 'everyone'}`)}
                    <button
                        type="button"
                        role="option"
                        aria-selected={index === mentionCandidateIndex}
                        class={`flex w-full min-w-0 items-center gap-2 rounded-lg px-2 py-2 text-left transition hover:bg-white/10 ${index === mentionCandidateIndex ? 'bg-white/10' : ''}`}
                        onclick={() => selectMentionCandidate(candidate)}
                    >
                        <span
                            class={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${candidate.kind === 'everyone' ? 'bg-[#f0b232]/20 text-[#f6c85f]' : 'bg-[#5865f2] text-white'}`}
                        >
                            {candidate.kind === 'everyone'
                                ? '@'
                                : candidate.name.slice(0, 1).toUpperCase()}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">
                                @{candidate.kind === 'everyone'
                                    ? 'everyone'
                                    : candidate.name}
                            </span>
                            <span class="block truncate text-xs text-[#80848e]"
                                >{candidate.email}</span
                            >
                        </span>
                    </button>
                {/each}
            {:else}
                <p class="px-2 py-3 text-sm text-[#80848e]">
                    一致するメンバーがいません
                </p>
            {/if}
        </div>
    {/if}

    {#if dragActive}
        <div
            class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center bg-[#5865f2]/20 backdrop-blur-sm"
        >
            <div
                class="flex items-center gap-3 rounded-2xl border-2 border-dashed border-[#5865f2] bg-[#313338]/90 px-8 py-6 text-lg font-semibold"
            >
                <Paperclip class="h-6 w-6" />
                ドロップしてアップロード
            </div>
        </div>
    {/if}
</div>

{#if showChannelDialog || editingChannel}
    <ChannelDialog
        {server}
        channel={editingChannel}
        onUpdated={(updated) => {
            server = {
                ...server,
                channels: (server.channels ?? []).map((item) =>
                    item.id === updated.id ? updated : item,
                ),
            };

            if (updated.id === channel.id) {
                channel = { ...channel, ...updated };
            }
        }}
        onClose={() => {
            showChannelDialog = false;
            editingChannel = null;
        }}
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

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}

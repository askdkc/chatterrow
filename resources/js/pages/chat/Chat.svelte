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
    import { filesFromDrop } from '@/lib/dropped-files';
    import { getEcho } from '@/lib/echo';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
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
    }: {
        server: ServerResource;
        channel: ChannelResource;
        initialMessages: MessageResource[];
        members: UserResource[];
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
    const seenReplyIds: number[] = [];

    const channelId = $derived(channel.id);
    const serverId = $derived(server.id);

    async function loadTodos() {
        const data = await apiJson<{ todos: TodoResource[] }>(
            `/servers/${serverId}/channels/${channelId}/todos`,
        );
        todos = data.todos;
    }

    onMount(() => {
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
            echo.leaveChannel(
                `private-server-${serverId}-channel-${channelId}`,
            );
            window.removeEventListener('keydown', onKeydown);
        };
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

    function scrollToBottom() {
        requestAnimationFrame(() => {
            messagesEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' });
        });
    }

    async function sendMessage() {
        const body = draft.trim();

        if (sending || (!body && pendingFiles.length === 0)) {
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

        uploadError = '';

        try {
            const files = Array.from(fileList);

            for (let index = 0; index < files.length; index += 10) {
                const form = new FormData();

                for (const file of files.slice(index, index + 10)) {
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
        if (e.key !== 'Enter' || e.isComposing || (!e.metaKey && !e.ctrlKey)) {
            return;
        }

        e.preventDefault();
        sendMessage();
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

    function collapseComposerIfIdle() {
        requestAnimationFrame(() => {
            if (
                !emojiPickerOpen &&
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
        draft = `${draft.slice(0, start)}${replacement}${draft.slice(end)}`;

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

        messages = messages.map((item) =>
            item.id === message.id ? { ...item, ...data.message } : item,
        );
        threadReplies = threadReplies.map((item) =>
            item.id === message.id ? { ...item, ...data.message } : item,
        );

        if (threadParent?.id === message.id) {
            threadParent = { ...threadParent, ...data.message };
        }
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
            currentUserId === server.created_by
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
                                {threadParent.user?.name}: {threadParent.body.slice(
                                    0,
                                    40,
                                )}
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
                                onEdit={editActiveThread}
                                onDelete={deleteActiveThread}
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
                                        onEdit={(body) =>
                                            editMessage(reply, body)}
                                        onDelete={() => deleteMessage(reply)}
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
                                    onOpenThread={() => openThread(message)}
                                    onEdit={(body) =>
                                        editMessage(message, body)}
                                    onDelete={() => deleteMessage(message)}
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
                    {#if pendingFiles.length > 0}
                        <div class="mb-2 flex flex-wrap gap-2">
                            {#each pendingFiles as file (file.path)}
                                <div
                                    class="flex items-center gap-2 rounded-lg bg-[#383a40] px-3 py-1.5 text-sm"
                                >
                                    <Paperclip class="h-3.5 w-3.5" />
                                    <span class="max-w-48 truncate"
                                        >{file.original_name}</span
                                    >
                                    <button
                                        type="button"
                                        class="text-[#80848e] hover:text-white"
                                        onclick={() =>
                                            (pendingFiles = pendingFiles.filter(
                                                (f) => f.path !== file.path,
                                            ))}
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                    <div
                        bind:this={composerShell}
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
                            onchange={(e) =>
                                onFilesPicked(e.currentTarget.files)}
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
                                oninput={resizeComposer}
                                onkeydown={onComposerKeydown}
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
                                    class="rounded p-1.5 text-[#80848e] transition enabled:text-[#dbdee1] enabled:hover:bg-white/10 enabled:hover:text-white disabled:opacity-40"
                                    onclick={sendMessage}
                                    disabled={sending ||
                                        (!draft.trim() &&
                                            pendingFiles.length === 0)}
                                    title="送信"
                                >
                                    {#if sending}
                                        <Loader2 class="h-4 w-4 animate-spin" />
                                    {:else}
                                        <Send class="h-4 w-4" />
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
                                    onclick={() => insertComposerText('@')}
                                    title="メンションを挿入"
                                >
                                    <AtSign class="h-4 w-4" />
                                </button>
                                <span
                                    class="mx-0.5 h-5 w-px shrink-0 bg-white/10"
                                ></span>
                                <button
                                    type="button"
                                    class="ml-auto rounded-md p-2 text-[#80848e] transition enabled:text-[#dbdee1] enabled:hover:bg-white/10 enabled:hover:text-white disabled:opacity-40"
                                    onclick={sendMessage}
                                    disabled={sending ||
                                        (!draft.trim() &&
                                            pendingFiles.length === 0)}
                                    title="送信"
                                >
                                    {#if sending}
                                        <Loader2 class="h-4 w-4 animate-spin" />
                                    {:else}
                                        <Send class="h-4 w-4" />
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
        onUpdated={(updated) => (server = { ...server, ...updated })}
        onClose={() => (showMemberDialog = false)}
    />
{/if}

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}

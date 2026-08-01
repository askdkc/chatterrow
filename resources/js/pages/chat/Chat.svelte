<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import {
        Hash,
        Send,
        Paperclip,
        X,
        MessageSquare,
        ListTodo,
        CalendarRange,
        FileText,
        Loader2,
    } from 'lucide-svelte';
    import { onMount } from 'svelte';
    import ChannelDialog from '@/components/discord/ChannelDialog.svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import MessageItem from '@/components/discord/MessageItem.svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import TodoPanel from '@/components/discord/TodoPanel.svelte';
    import { getEcho } from '@/lib/echo';
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
        authServers,
    }: {
        server: ServerResource;
        channel: ChannelResource;
        initialMessages: MessageResource[];
        members: UserResource[];
        authServers: ServerResource[];
    } = $props();

    let messages: MessageResource[] = $state(initialMessages);
    let draft = $state('');
    let threadParent: MessageResource | null = $state(null);
    let sending = $state(false);
    let dragActive = $state(false);
    let pendingFiles: StoredFileResource[] = $state([]);
    let showChannelDialog = $state(false);
    let showMemberDialog = $state(false);
    let showServerDialog = $state(false);
    let todos = $state<TodoResource[]>([]);
    let showTodos = $state(true);
    let messagesEnd: HTMLDivElement;
    let fileInput: HTMLInputElement;

    const channelId = channel.id;
    const serverId = server.id;

    async function loadTodos() {
        const res = await fetch(`/servers/${serverId}/channels/${channelId}/todos`);

        if (res.ok) {
            const data = await res.json();
            todos = data.todos;
        }
    }

    onMount(() => {
        loadTodos();
        scrollToBottom();
        const echo = getEcho();
        const broadcastChannel = echo.private(`server.${serverId}.channel.${channelId}`);

        broadcastChannel.listen('.MessageCreated', (e: { message: MessageResource }) => {
            appendMessage(e.message);
        });
        broadcastChannel.listen('.ReminderCreated', (e: { message: MessageResource }) => {
            appendMessage(e.message);
        });
        broadcastChannel.listen('.TodoUpdated', (e: { todo: TodoResource }) => {
            upsertTodo(e.todo);
        });

        const onKeydown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
threadParent = null;
}
        };
        window.addEventListener('keydown', onKeydown);

        return () => {
            broadcastChannel.stopListening('.MessageCreated');
            broadcastChannel.stopListening('.ReminderCreated');
            broadcastChannel.stopListening('.TodoUpdated');
            echo.leaveChannel(`private-server-${serverId}-channel-${channelId}`);
            window.removeEventListener('keydown', onKeydown);
        };
    });

    function appendMessage(message: MessageResource) {
        if (message.channel_id !== channelId) {
return;
}

        if (message.parent_id) {
            messages = messages.map((m) =>
                m.id === message.parent_id ? { ...m, reply_count: (m.reply_count ?? 0) + 1 } : m,
            );

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

        if (!body && pendingFiles.length === 0) {
return;
}

        sending = true;

        try {
            const res = await fetch(`/servers/${serverId}/channels/${channelId}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
            });

            if (res.ok) {
                const data = await res.json();
                appendMessage(data.message);
                draft = '';
                pendingFiles = [];
                threadParent = null;
            }
        } finally {
            sending = false;
        }
    }

    function csrfToken(): string {
        return (
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ??
            ''
        );
    }

    async function onFilesPicked(fileList: FileList | null) {
        if (!fileList || fileList.length === 0) {
return;
}

        const form = new FormData();

        for (const file of Array.from(fileList)) {
            form.append('files[]', file);
        }

        const res = await fetch(`/servers/${serverId}/files`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            body: form,
        });

        if (res.ok) {
            const data = await res.json();
            pendingFiles = [...pendingFiles, ...data.files];
        }
    }

    function onDrop(e: DragEvent) {
        e.preventDefault();
        dragActive = false;
        onFilesPicked(e.dataTransfer?.files ?? null);
    }

    function openThread(message: MessageResource) {
        threadParent = message;
    }

    function onAddServer() {
        showServerDialog = true;
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
        onAddChannel={() => (showChannelDialog = true)}
        onManageMembers={() => (showMemberDialog = true)}
    />

    <main class="flex min-w-0 flex-1 flex-col">
        <!-- Channel header -->
        <header
            class="flex h-12 shrink-0 items-center gap-2 border-b border-black/10 px-4 shadow-sm dark:border-black/20"
        >
            <Hash class="h-5 w-5 text-[#80848e]" />
            <h1 class="text-[15px] font-bold text-[#dbdee1]">{channel.name}</h1>
            {#if channel.starts_on || channel.ends_on}
                <span class="ml-2 rounded bg-[#f0b232]/20 px-2 py-0.5 text-xs text-[#f0b232]">
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
                <a
                    href={`/servers/${serverId}/channels/${channelId}/gantt`}
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium transition hover:bg-white/10"
                    title="ガントチャート"
                >
                    <CalendarRange class="h-4 w-4" />
                    ガント
                </a>
                <a
                    href={`/servers/${serverId}/channels/${channelId}/files`}
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium transition hover:bg-white/10"
                    title="ファイル"
                >
                    <FileText class="h-4 w-4" />
                    ファイル
                </a>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Messages -->
            <div
                ondragover={(e) => {
                    e.preventDefault();
                    dragActive = true;
                }}
                ondragleave={() => (dragActive = false)}
                ondrop={onDrop}
                class={`flex min-w-0 flex-1 flex-col overflow-y-auto px-4 ${
                    dragActive ? 'bg-[#5865f2]/5 ring-2 ring-inset ring-[#5865f2]' : ''
                }`}
            >
                {#if threadParent}
                    <div class="flex items-center gap-2 border-b border-black/10 py-2 dark:border-black/20">
                        <MessageSquare class="h-4 w-4 text-[#80848e]" />
                        <span class="text-sm font-semibold">スレッド</span>
                        <span class="truncate text-sm text-[#80848e]">
                            {threadParent.user?.name}: {threadParent.body.slice(0, 40)}
                        </span>
                        <button
                            type="button"
                            class="ml-auto rounded p-1 hover:bg-white/10"
                            onclick={() => (threadParent = null)}
                            title="スレッドを閉じる"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                {/if}

                <div class="flex-1">
                    {#each messages as message (message.id)}
                        <MessageItem
                            {message}
                            onOpenThread={() => openThread(message)}
                        />
                    {/each}
                    {#if messages.length === 0}
                        <div class="flex h-full items-center justify-center text-sm text-[#80848e]">
                            まだメッセージがありません
                        </div>
                    {/if}
                </div>
                <div bind:this={messagesEnd}></div>
            </div>

            <!-- Todo panel -->
            {#if showTodos}
                <TodoPanel
                    {todos}
                    {members}
                    serverId={serverId}
                    channelId={channelId}
                />
            {/if}
        </div>

        <!-- Composer -->
        <div class="shrink-0 px-4 pb-6">
            {#if pendingFiles.length > 0}
                <div class="mb-2 flex flex-wrap gap-2">
                    {#each pendingFiles as file (file.path)}
                        <div
                            class="flex items-center gap-2 rounded-lg bg-[#383a40] px-3 py-1.5 text-sm"
                        >
                            <Paperclip class="h-3.5 w-3.5" />
                            <span class="max-w-48 truncate">{file.original_name}</span>
                            <button
                                type="button"
                                class="text-[#80848e] hover:text-white"
                                onclick={() =>
                                    (pendingFiles = pendingFiles.filter(
                                        (f) => f.path !== file.path,
                                    ))
                                }
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    {/each}
                </div>
            {/if}
            <div
                class="flex items-end gap-3 rounded-lg bg-[#383a40] px-4 py-3"
                ondragover={(e) => {
                    e.preventDefault();
                    dragActive = true;
                }}
                ondragleave={() => (dragActive = false)}
                ondrop={onDrop}
            >
                <input
                    bind:this={fileInput}
                    type="file"
                    multiple
                    class="hidden"
                    onchange={(e) => onFilesPicked(e.currentTarget.files)}
                />
                <button
                    type="button"
                    class="shrink-0 rounded p-1 text-[#b5bac1] transition hover:text-[#dbdee1]"
                    onclick={() => fileInput?.click()}
                    title="ファイルを添付"
                >
                    <Paperclip class="h-5 w-5" />
                </button>
                <textarea
                    bind:value={draft}
                    rows={1}
                    class="max-h-40 min-h-5 flex-1 resize-none bg-transparent text-[15px] text-[#dbdee1] outline-none placeholder:text-[#6d6f78]"
                    placeholder={threadParent ? `「${threadParent.user?.name}」への返信` : 'メッセージを入力'}
                    onkeydown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            sendMessage();
                        }
                    }}
                ></textarea>
                <button
                    type="button"
                    class="shrink-0 rounded p-1 text-[#b5bac1] transition hover:text-[#dbdee1] disabled:opacity-50"
                    onclick={sendMessage}
                    disabled={sending || (!draft.trim() && pendingFiles.length === 0)}
                    title="送信"
                >
                    {#if sending}
                        <Loader2 class="h-5 w-5 animate-spin" />
                    {:else}
                        <Send class="h-5 w-5" />
                    {/if}
                </button>
            </div>
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

{#if showChannelDialog}
    <ChannelDialog
        {server}
        onClose={() => (showChannelDialog = false)}
    />
{/if}

{#if showMemberDialog}
    <MemberDialog
        {server}
        {members}
        onClose={() => (showMemberDialog = false)}
    />
{/if}

{#if showServerDialog}
    <ServerDialog
        onClose={() => (showServerDialog = false)}
    />
{/if}

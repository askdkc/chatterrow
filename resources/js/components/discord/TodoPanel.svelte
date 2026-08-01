<script lang="ts">
    import {
        ListTodo,
        Plus,
        CheckCircle2,
        Circle,
        CalendarDays,
        Clock3,
        Flag,
        Trash2,
        User,
    } from 'lucide-svelte';
    import TodoDialog from '@/components/discord/TodoDialog.svelte';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import type { TodoResource, UserResource } from '@/types';

    let {
        todos,
        members,
        serverId,
        channelId,
    }: {
        todos: TodoResource[];
        members: UserResource[];
        serverId: number;
        channelId: number;
    } = $props();

    let showCreateDialog = $state(false);
    let error = $state('');

    function addTodo(todo: TodoResource) {
        error = '';
        todos = [...todos, todo];
    }

    async function toggleTodo(todo: TodoResource) {
        try {
            const data = await apiJson<{ todo: TodoResource }>(
                `/servers/${serverId}/channels/${channelId}/todos/${todo.id}/toggle`,
                { method: 'PATCH' },
            );
            todos = todos.map((t) => (t.id === todo.id ? data.todo : t));
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '更新に失敗しました';
        }
    }

    async function removeTodo(todo: TodoResource) {
        try {
            await apiFetch(
                `/servers/${serverId}/channels/${channelId}/todos/${todo.id}`,
                {
                    method: 'DELETE',
                },
            );
            todos = todos.filter((t) => t.id !== todo.id);
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '削除に失敗しました';
        }
    }

    function formatDateTime(iso: string | null): string {
        if (!iso) {
            return '';
        }

        return new Date(iso).toLocaleString('ja-JP', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function priorityLabel(priority: TodoResource['priority']): string {
        return {
            low: '低',
            normal: '通常',
            high: '高',
            urgent: '緊急',
        }[priority];
    }

    function assigneeName(id: number | null): string {
        return members.find((m) => m.id === id)?.name ?? '未割当';
    }
</script>

<aside
    class="flex w-72 shrink-0 flex-col border-l border-black/10 bg-[#2b2d31] dark:border-black/20"
>
    <div
        class="flex h-12 items-center gap-2 border-b border-black/10 px-4 dark:border-black/20"
    >
        <ListTodo class="h-4 w-4 text-[#80848e]" />
        <span class="text-sm font-bold">タスク</span>
        <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-xs">
            {todos.filter((t) => !t.completed_at).length} 未完了
        </span>
    </div>

    {#if error}
        <p
            class="border-b border-black/10 px-4 py-2 text-xs text-red-400"
            role="alert"
            aria-live="assertive"
        >
            {error}
        </p>
    {/if}

    <div class="flex-1 overflow-y-auto p-3">
        {#if todos.length === 0}
            <p class="py-6 text-center text-sm text-[#80848e]">
                タスクがありません
            </p>
        {/if}

        {#each todos as todo (todo.id)}
            <div
                class="mb-2 rounded-lg bg-[#383a40] p-3 transition hover:bg-[#404249]"
                class:opacity-60={Boolean(todo.completed_at)}
            >
                <div class="flex items-start gap-2">
                    <button
                        type="button"
                        onclick={() => toggleTodo(todo)}
                        class="mt-0.5 shrink-0"
                        title="完了切替"
                    >
                        {#if todo.completed_at}
                            <CheckCircle2 class="h-5 w-5 text-[#23a559]" />
                        {:else}
                            <Circle
                                class="h-5 w-5 text-[#80848e] hover:text-[#dbdee1]"
                            />
                        {/if}
                    </button>
                    <div class="min-w-0 flex-1">
                        <p
                            class="break-words text-sm font-medium text-[#dbdee1]"
                            class:line-through={Boolean(todo.completed_at)}
                        >
                            {todo.title}
                        </p>
                        {#if todo.details}
                            <p
                                class="mt-0.5 break-words text-xs text-[#80848e]"
                            >
                                {todo.details}
                            </p>
                        {/if}
                        <div
                            class="mt-1.5 flex items-center gap-2 text-xs text-[#80848e]"
                        >
                            {#if todo.starts_at}
                                <span class="flex items-center gap-1">
                                    <Clock3 class="h-3 w-3" />
                                    {formatDateTime(todo.starts_at)}
                                </span>
                            {/if}
                            {#if todo.due_at || todo.due_on}
                                <span class="flex items-center gap-1">
                                    <CalendarDays class="h-3 w-3" />
                                    {todo.due_at
                                        ? formatDateTime(todo.due_at)
                                        : todo.due_on}
                                </span>
                            {/if}
                            <span class="flex items-center gap-1">
                                <Flag class="h-3 w-3" />
                                {priorityLabel(todo.priority)}
                            </span>
                            <span class="flex items-center gap-1">
                                <User class="h-3 w-3" />
                                {assigneeName(todo.assignee_id)}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded p-1 text-[#80848e] opacity-0 transition hover:bg-white/10 hover:text-red-400 group-hover:opacity-100"
                        onclick={() => removeTodo(todo)}
                        title="削除"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        {/each}
    </div>

    <div class="border-t border-black/10 p-3 dark:border-black/20">
        <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-md bg-[#5865f2] px-3 py-2.5 text-sm font-medium text-white transition hover:bg-[#4752c4]"
            onclick={() => (showCreateDialog = true)}
        >
            <Plus class="h-4 w-4" />
            タスクを追加
        </button>
    </div>
</aside>

{#if showCreateDialog}
    <TodoDialog
        {serverId}
        {channelId}
        onCreated={addTodo}
        onClose={() => (showCreateDialog = false)}
    />
{/if}

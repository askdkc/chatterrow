<script lang="ts">
    import {
        ListTodo,
        Plus,
        CheckCircle2,
        Circle,
        CalendarDays,
        Clock3,
        ChevronDown,
        ChevronLeft,
        ChevronRight,
        ChevronsDown,
        ChevronsUp,
        Flag,
        Trash2,
        User,
    } from 'lucide-svelte';
    import TodoDialog from '@/components/discord/TodoDialog.svelte';
    import { formatDateTime } from '@/lib/dates';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import { priorityLabel } from '@/lib/todos';
    import type { TodoResource, UserResource } from '@/types';

    let {
        todos,
        members,
        serverId,
        channelId,
        channelStartsOn = null,
    }: {
        todos: TodoResource[];
        members: UserResource[];
        serverId: number;
        channelId: number;
        channelStartsOn?: string | null;
    } = $props();

    let showCreateDialog = $state(false);
    let editingTodo = $state<TodoResource | null>(null);
    let collapsed = $state(false);
    let expandedTodoIds = $state<number[]>([]);
    let error = $state('');
    const allTodosExpanded = $derived(
        todos.length > 0 &&
            todos.every((todo) => expandedTodoIds.includes(todo.id)),
    );

    function addTodo(todo: TodoResource) {
        error = '';
        todos = [...todos, todo];
    }

    function updateTodo(todo: TodoResource) {
        error = '';
        todos = todos.map((item) => (item.id === todo.id ? todo : item));
    }

    function openTodo(todo: TodoResource) {
        editingTodo = todo;
    }

    function isTodoCollapsed(todoId: number): boolean {
        return !expandedTodoIds.includes(todoId);
    }

    function toggleTodoDetails(todoId: number) {
        expandedTodoIds = isTodoCollapsed(todoId)
            ? [...expandedTodoIds, todoId]
            : expandedTodoIds.filter((id) => id !== todoId);
    }

    function toggleAllTodoDetails() {
        expandedTodoIds = allTodosExpanded ? [] : todos.map((todo) => todo.id);
    }

    function handleTodoKeydown(event: KeyboardEvent, todo: TodoResource) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openTodo(todo);
        }
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
            expandedTodoIds = expandedTodoIds.filter((id) => id !== todo.id);
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '削除に失敗しました';
        }
    }

    function assigneeName(id: number | null): string {
        return members.find((m) => m.id === id)?.name ?? '未割当';
    }
</script>

<aside
    class={`flex shrink-0 flex-col border-l border-black/10 bg-[#2b2d31] transition-[width] duration-200 dark:border-black/20 ${collapsed ? 'w-12' : 'w-72'}`}
>
    <div
        class={`flex h-12 items-center border-b border-black/10 dark:border-black/20 ${collapsed ? 'justify-center px-1' : 'gap-2 px-4'}`}
    >
        <ListTodo class="h-4 w-4 text-[#80848e]" />
        {#if !collapsed}
            <span class="text-sm font-bold">タスク</span>
            <button
                type="button"
                class="ml-auto flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1] disabled:cursor-default disabled:opacity-40"
                aria-label={allTodosExpanded
                    ? 'タスクをすべて折りたたむ'
                    : 'タスクをすべて展開'}
                title={allTodosExpanded
                    ? 'タスクをすべて折りたたむ'
                    : 'タスクをすべて展開'}
                disabled={todos.length === 0}
                onclick={toggleAllTodoDetails}
            >
                {#if allTodosExpanded}
                    <ChevronsUp class="h-3.5 w-3.5" />
                    全て閉じる
                {:else}
                    <ChevronsDown class="h-3.5 w-3.5" />
                    全て展開
                {/if}
            </button>
            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">
                {todos.filter((t) => !t.completed_at).length} 未完了
            </span>
        {/if}
        <button
            type="button"
            class={`rounded p-1 text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1] ${collapsed ? '' : 'ml-1'}`}
            aria-label={collapsed ? 'タスクを展開' : 'タスクを折りたたむ'}
            title={collapsed ? 'タスクを展開' : 'タスクを折りたたむ'}
            onclick={() => (collapsed = !collapsed)}
        >
            {#if collapsed}
                <ChevronLeft class="h-4 w-4" />
            {:else}
                <ChevronRight class="h-4 w-4" />
            {/if}
        </button>
    </div>

    {#if !collapsed}
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
                    class="group mb-2 rounded-lg bg-[#383a40] p-3 transition hover:bg-[#404249]"
                    class:opacity-60={Boolean(todo.completed_at)}
                >
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            onclick={(event) => {
                                event.stopPropagation();
                                toggleTodo(todo);
                            }}
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
                        <button
                            type="button"
                            class="min-w-0 flex-1 text-left"
                            aria-label={`${todo.title}を編集`}
                            onclick={() => openTodo(todo)}
                        >
                            <p
                                class="break-words text-sm font-medium text-[#dbdee1]"
                                class:line-through={Boolean(todo.completed_at)}
                            >
                                {todo.title}
                            </p>
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1]"
                            aria-label={isTodoCollapsed(todo.id)
                                ? `${todo.title}の詳細を展開`
                                : `${todo.title}の詳細を折りたたむ`}
                            aria-expanded={!isTodoCollapsed(todo.id)}
                            aria-controls={`todo-details-${todo.id}`}
                            title={isTodoCollapsed(todo.id)
                                ? '詳細を展開'
                                : '詳細を折りたたむ'}
                            onclick={() => toggleTodoDetails(todo.id)}
                        >
                            {#if isTodoCollapsed(todo.id)}
                                <ChevronRight class="h-4 w-4" />
                            {:else}
                                <ChevronDown class="h-4 w-4" />
                            {/if}
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-[#80848e] opacity-0 transition hover:bg-white/10 hover:text-red-400 focus:opacity-100 group-hover:opacity-100"
                            onclick={() => removeTodo(todo)}
                            title="削除"
                            aria-label={`${todo.title}を削除`}
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    {#if !isTodoCollapsed(todo.id)}
                        <div
                            id={`todo-details-${todo.id}`}
                            class="ml-7 cursor-pointer"
                            role="button"
                            tabindex="0"
                            aria-label={`${todo.title}の詳細を編集`}
                            onclick={() => openTodo(todo)}
                            onkeydown={(event) =>
                                handleTodoKeydown(event, todo)}
                        >
                            {#if todo.details}
                                <p
                                    class="mt-0.5 break-words text-xs text-[#80848e]"
                                >
                                    {todo.details}
                                </p>
                            {/if}
                            <div class="mt-3 grid grid-cols-1 gap-2 text-xs">
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <Clock3 class="h-3 w-3" />
                                        開始日
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {todo.starts_at
                                            ? formatDateTime(todo.starts_at, {
                                                  year: false,
                                                  month: 'numeric',
                                              })
                                            : '未設定'}
                                    </span>
                                </div>
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <CalendarDays class="h-3 w-3" />
                                        終了日
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {todo.due_at
                                            ? formatDateTime(todo.due_at, {
                                                  year: false,
                                                  month: 'numeric',
                                              })
                                            : '未設定'}
                                    </span>
                                </div>
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <Flag class="h-3 w-3" />
                                        プライオリティ
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {priorityLabel(todo.priority)}
                                    </span>
                                </div>
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <User class="h-3 w-3" />
                                        担当者
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {assigneeName(todo.assignee_id)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    {/if}
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
    {/if}
</aside>

{#if showCreateDialog || editingTodo}
    <TodoDialog
        {serverId}
        {channelId}
        {channelStartsOn}
        todo={editingTodo}
        onCreated={addTodo}
        onUpdated={updateTodo}
        onClose={() => {
            showCreateDialog = false;
            editingTodo = null;
        }}
    />
{/if}

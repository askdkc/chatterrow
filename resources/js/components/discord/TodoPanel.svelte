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
    import { t } from '@/lib/i18n';
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
    let hoveredTodoIds = $state<number[]>([]);
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
        return (
            !expandedTodoIds.includes(todoId) &&
            !hoveredTodoIds.includes(todoId)
        );
    }

    function hoverTodo(todoId: number) {
        if (!hoveredTodoIds.includes(todoId)) {
            hoveredTodoIds = [...hoveredTodoIds, todoId];
        }
    }

    function unhoverTodo(todoId: number) {
        hoveredTodoIds = hoveredTodoIds.filter((id) => id !== todoId);
    }

    function toggleTodoDetails(todoId: number) {
        expandedTodoIds = expandedTodoIds.includes(todoId)
            ? expandedTodoIds.filter((id) => id !== todoId)
            : [...expandedTodoIds, todoId];
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
                e instanceof HttpError
                    ? e.messageText()
                    : t('Failed to update task.');
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
            hoveredTodoIds = hoveredTodoIds.filter((id) => id !== todo.id);
        } catch (e) {
            error =
                e instanceof HttpError
                    ? e.messageText()
                    : t('Failed to delete task.');
        }
    }

    function assigneeName(id: number | null): string {
        return members.find((m) => m.id === id)?.name ?? t('Unassigned');
    }

    function priorityLabel(priority: TodoResource['priority']): string {
        switch (priority) {
            case 'low':
                return t('Low');
            case 'normal':
                return t('Normal');
            case 'high':
                return t('High');
            case 'urgent':
                return t('Urgent');
            default:
                return t('Normal');
        }
    }
</script>

<aside
    class={`flex shrink-0 flex-col border-l border-black/10 bg-[#2b2d31] transition-[width] duration-200 dark:border-black/20 ${collapsed ? 'w-12' : 'w-72'}`}
>
    <div
        class={`flex h-12 items-center border-b border-black/10 dark:border-black/20 ${collapsed ? 'justify-center px-1' : 'gap-1 px-4'}`}
    >
        <ListTodo class="h-4 w-4 shrink-0 text-[#80848e]" />
        {#if !collapsed}
            <span class="shrink-0 whitespace-nowrap text-sm font-bold"
                >{t('Tasks')}</span
            >
            <button
                type="button"
                class="ml-auto flex shrink-0 items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium whitespace-nowrap text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1] disabled:cursor-default disabled:opacity-40"
                aria-label={allTodosExpanded
                    ? t('Collapse all tasks')
                    : t('Expand all tasks')}
                title={allTodosExpanded
                    ? t('Collapse all tasks')
                    : t('Expand all tasks')}
                disabled={todos.length === 0}
                onclick={toggleAllTodoDetails}
            >
                {#if allTodosExpanded}
                    <ChevronsUp class="h-3.5 w-3.5" />
                    {t('Collapse all')}
                {:else}
                    <ChevronsDown class="h-3.5 w-3.5" />
                    {t('Expand all')}
                {/if}
            </button>
            <span
                class="shrink-0 whitespace-nowrap rounded-full bg-white/10 px-2 py-0.5 text-xs"
            >
                {t('Incomplete: :count', {
                    count: String(todos.filter((t) => !t.completed_at).length),
                })}
            </span>
        {/if}
        <button
            type="button"
            class={`shrink-0 rounded p-1 text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1] ${collapsed ? '' : 'ml-1'}`}
            aria-label={collapsed
                ? t('Expand task panel')
                : t('Collapse task panel')}
            title={collapsed
                ? t('Expand task panel')
                : t('Collapse task panel')}
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
                    {t('No tasks')}
                </p>
            {/if}

            {#each todos as todo (todo.id)}
                <div
                    class="group mb-2 rounded-lg bg-[#383a40] p-3 transition hover:bg-[#404249]"
                    class:opacity-60={Boolean(todo.completed_at)}
                    role="group"
                    onmouseenter={() => hoverTodo(todo.id)}
                    onmouseleave={() => unhoverTodo(todo.id)}
                >
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            onclick={(event) => {
                                event.stopPropagation();
                                toggleTodo(todo);
                            }}
                            class="mt-0.5 shrink-0"
                            title={t('Toggle completion')}
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
                            aria-label={t('Edit :name', { name: todo.title })}
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
                                ? t('Expand details for :name', {
                                      name: todo.title,
                                  })
                                : t('Collapse details for :name', {
                                      name: todo.title,
                                  })}
                            aria-expanded={!isTodoCollapsed(todo.id)}
                            aria-controls={`todo-details-${todo.id}`}
                            title={isTodoCollapsed(todo.id)
                                ? t('Expand details')
                                : t('Collapse details')}
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
                            title={t('Delete')}
                            aria-label={t('Delete :name', { name: todo.title })}
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
                            aria-label={t('Edit details for :name', {
                                name: todo.title,
                            })}
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
                                        {t('Start date')}
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {todo.starts_at
                                            ? formatDateTime(todo.starts_at, {
                                                  year: false,
                                                  month: 'numeric',
                                              })
                                            : t('Not set')}
                                    </span>
                                </div>
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <CalendarDays class="h-3 w-3" />
                                        {t('End date')}
                                    </span>
                                    <span
                                        class="mt-0.5 block text-sm font-semibold text-[#4e5058] dark:text-[#dbdee1]"
                                    >
                                        {todo.due_at
                                            ? formatDateTime(todo.due_at, {
                                                  year: false,
                                                  month: 'numeric',
                                              })
                                            : t('Not set')}
                                    </span>
                                </div>
                                <div
                                    class="min-w-0 rounded-md bg-white/50 px-2 py-1.5 dark:bg-black/10"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[#6a6f78] dark:text-[#949ba4]"
                                    >
                                        <Flag class="h-3 w-3" />
                                        {t('Priority')}
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
                                        {t('Assignee')}
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
                {t('Add task')}
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

<script lang="ts">
    import { Loader2, Plus, X } from 'lucide-svelte';
    import { apiJson, HttpError } from '@/lib/http';
    import type { TodoResource } from '@/types';

    let {
        serverId,
        channelId,
        onCreated,
        onClose,
    }: {
        serverId: number;
        channelId: number;
        onCreated: (todo: TodoResource) => void;
        onClose: () => void;
    } = $props();

    let title = $state('');
    let startsOn = $state('');
    let startsTime = $state('');
    let dueOn = $state('');
    let dueTime = $state('');
    let priority = $state<TodoResource['priority']>('normal');
    let details = $state('');
    let saving = $state(false);
    let error = $state('');

    function dateTime(date: string, time: string): string | null {
        return date ? `${date}T${time || '00:00'}` : null;
    }

    async function createTodo() {
        const trimmedTitle = title.trim();

        if (!trimmedTitle || saving) {
            return;
        }

        saving = true;
        error = '';

        try {
            const data = await apiJson<{ todo: TodoResource }>(
                `/servers/${serverId}/channels/${channelId}/todos`,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        title: trimmedTitle,
                        starts_at: dateTime(startsOn, startsTime),
                        due_at: dateTime(dueOn, dueTime),
                        priority,
                        details: details.trim() || null,
                    }),
                },
            );

            onCreated(data.todo);
            onClose();
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '追加に失敗しました';
        } finally {
            saving = false;
        }
    }

    function handleDialogKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            onClose();
        } else if (
            event.key === 'Enter' &&
            !event.isComposing &&
            (event.metaKey || event.ctrlKey)
        ) {
            event.preventDefault();
            createTodo();
        }
    }
</script>

<svelte:window onkeydown={handleDialogKeydown} />

<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <button
        type="button"
        class="absolute inset-0 bg-black/60"
        aria-label="背景をクリックして閉じる"
        onclick={onClose}
    ></button>
    <div
        class="relative z-10 w-full max-w-lg rounded-xl bg-[#313338] p-6 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="todo-dialog-title"
    >
        <div class="mb-5 flex items-center justify-between">
            <h2 id="todo-dialog-title" class="text-lg font-bold text-[#dbdee1]">
                タスクを作成
            </h2>
            <button
                type="button"
                class="rounded p-1 hover:bg-white/10"
                aria-label="閉じる"
                onclick={onClose}
            >
                <X class="h-5 w-5 text-[#80848e]" />
            </button>
        </div>

        <div class="space-y-4">
            <div>
                <label
                    for="todo-title"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    タスク名
                </label>
                <input
                    id="todo-title"
                    bind:value={title}
                    type="text"
                    placeholder="タスク名を入力"
                    class="w-full rounded-md bg-[#383a40] px-3 py-2.5 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <fieldset>
                    <legend class="mb-1 text-xs font-semibold text-[#b5bac1]">
                        開始日時
                    </legend>
                    <div
                        class="grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-2"
                    >
                        <label for="todo-starts-on" class="sr-only"
                            >開始日</label
                        >
                        <input
                            id="todo-starts-on"
                            bind:value={startsOn}
                            type="date"
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                        />
                        <label for="todo-starts-time" class="sr-only"
                            >開始時刻</label
                        >
                        <input
                            id="todo-starts-time"
                            bind:value={startsTime}
                            type="time"
                            disabled={!startsOn}
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2] disabled:opacity-50"
                        />
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-1 text-xs font-semibold text-[#b5bac1]">
                        終了日時
                    </legend>
                    <div
                        class="grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-2"
                    >
                        <label for="todo-due-on" class="sr-only">終了日</label>
                        <input
                            id="todo-due-on"
                            bind:value={dueOn}
                            type="date"
                            min={startsOn || undefined}
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                        />
                        <label for="todo-due-time" class="sr-only"
                            >終了時刻</label
                        >
                        <input
                            id="todo-due-time"
                            bind:value={dueTime}
                            type="time"
                            min={dueOn === startsOn
                                ? startsTime || undefined
                                : undefined}
                            disabled={!dueOn}
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2] disabled:opacity-50"
                        />
                    </div>
                </fieldset>
            </div>

            <div>
                <label
                    for="todo-priority"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    プライオリティ
                </label>
                <select
                    id="todo-priority"
                    bind:value={priority}
                    class="w-full rounded-md bg-[#383a40] px-3 py-2.5 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                >
                    <option value="low">低</option>
                    <option value="normal">通常</option>
                    <option value="high">高</option>
                    <option value="urgent">緊急</option>
                </select>
            </div>

            <div>
                <label
                    for="todo-details"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    メモ
                </label>
                <textarea
                    id="todo-details"
                    bind:value={details}
                    rows={5}
                    placeholder="メモを入力"
                    class="max-h-60 min-h-28 w-full resize-y rounded-md bg-[#383a40] px-3 py-2.5 text-[15px] leading-6 text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                ></textarea>
            </div>

            {#if error}
                <p
                    class="text-sm text-red-400"
                    role="alert"
                    aria-live="assertive"
                >
                    {error}
                </p>
            {/if}
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button
                type="button"
                class="rounded-md px-4 py-2 text-sm font-medium text-[#b5bac1] transition hover:bg-white/10"
                onclick={onClose}
            >
                キャンセル
            </button>
            <button
                type="button"
                class="flex items-center gap-2 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4] disabled:opacity-50"
                onclick={createTodo}
                disabled={saving || !title.trim()}
            >
                {#if saving}
                    <Loader2 class="h-4 w-4 animate-spin" />
                {:else}
                    <Plus class="h-4 w-4" />
                {/if}
                作成
            </button>
        </div>
    </div>
</div>

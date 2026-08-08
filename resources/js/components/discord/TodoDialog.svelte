<script lang="ts">
    import { Loader2, Plus, X } from 'lucide-svelte';
    import TimePicker from '@/components/ui/TimePicker.svelte';
    import {
        dateInputValue,
        dateValue,
        localDateTimeIso,
        timeInputValue,
        timeValue,
    } from '@/lib/dates';
    import { apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import type { TodoResource } from '@/types';

    let {
        serverId,
        channelId,
        channelStartsOn = null,
        todo = null,
        onCreated,
        onUpdated,
        onClose,
    }: {
        serverId: number;
        channelId: number;
        channelStartsOn?: string | null;
        todo?: TodoResource | null;
        onCreated?: (todo: TodoResource) => void;
        onUpdated?: (todo: TodoResource) => void;
        onClose: () => void;
    } = $props();

    const isEditing = $derived(todo !== null);

    function nextHalfHour(): Date {
        const now = new Date();
        const slot = 30 * 60 * 1000;
        const next = now.getTime() - (now.getTime() % slot) + slot;

        return new Date(next);
    }

    const nextStart = nextHalfHour();
    const initialStartDate = $derived(
        channelStartsOn && channelStartsOn > dateValue(nextStart)
            ? channelStartsOn
            : dateValue(nextStart),
    );
    const initialStart = $derived(
        new Date(`${initialStartDate}T${timeValue(nextStart)}`),
    );
    const initialDue = $derived(
        new Date(initialStart.getTime() + 30 * 60 * 1000),
    );

    let title = $derived(todo?.title ?? '');
    let startsOn = $derived(
        todo ? dateInputValue(todo.starts_at) : dateValue(initialStart),
    );
    let startsTime = $derived(
        todo ? timeInputValue(todo.starts_at) : timeValue(initialStart),
    );
    let dueOn = $derived(
        todo ? dateInputValue(todo.due_at) : dateValue(initialDue),
    );
    let dueTime = $derived(
        todo ? timeInputValue(todo.due_at) : timeValue(initialDue),
    );
    let dueManuallyEdited = $state(false);
    $effect(() => {
        if (todo) {
            dueManuallyEdited = true;
        }
    });
    let priority = $derived<TodoResource['priority']>(
        todo?.priority ?? 'normal',
    );
    let details = $derived(todo?.details ?? '');
    let saving = $state(false);
    let error = $state('');

    function syncDueFromStart() {
        if (dueManuallyEdited || !startsOn || !startsTime) {
            return;
        }

        const start = new Date(`${startsOn}T${startsTime}`);

        if (Number.isNaN(start.getTime())) {
            return;
        }

        const due = new Date(start.getTime() + 30 * 60 * 1000);

        const pad = (value: number) => String(value).padStart(2, '0');
        dueOn = `${due.getFullYear()}-${pad(due.getMonth() + 1)}-${pad(due.getDate())}`;
        dueTime = `${pad(due.getHours())}:${pad(due.getMinutes())}`;
    }

    async function saveTodo() {
        const trimmedTitle = title.trim();

        if (!trimmedTitle || saving) {
            return;
        }

        saving = true;
        error = '';

        try {
            const startsAt = localDateTimeIso(startsOn, startsTime);
            const startChanged = todo
                ? dateInputValue(todo.starts_at) !== startsOn ||
                  timeInputValue(todo.starts_at) !== startsTime
                : true;
            const submittedStartsAt =
                todo && !startChanged ? todo.starts_at : startsAt;
            const dueAt = localDateTimeIso(dueOn, dueTime);
            const deadlineChanged = todo
                ? dateInputValue(todo.due_at) !== dueOn ||
                  timeInputValue(todo.due_at) !== dueTime
                : true;
            const submittedDueAt =
                todo && !deadlineChanged ? todo.due_at : dueAt;
            const browserTimezone =
                Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

            const data = await apiJson<{ todo: TodoResource }>(
                todo
                    ? `/servers/${serverId}/channels/${channelId}/todos/${todo.id}`
                    : `/servers/${serverId}/channels/${channelId}/todos`,
                {
                    method: todo ? 'PATCH' : 'POST',
                    body: JSON.stringify({
                        title: trimmedTitle,
                        starts_at: submittedStartsAt,
                        due_at: submittedDueAt,
                        due_timezone:
                            todo && !deadlineChanged
                                ? todo.due_timezone
                                : browserTimezone,
                        priority,
                        details: details.trim() || null,
                    }),
                },
            );

            if (todo) {
                onUpdated?.(data.todo);
            } else {
                onCreated?.(data.todo);
            }

            onClose();
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : isEditing
                      ? t('Failed to save task.')
                      : t('Failed to add task.');
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
            saveTodo();
        }
    }
</script>

<svelte:window onkeydown={handleDialogKeydown} />

<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <button
        type="button"
        class="absolute inset-0 bg-black/60"
        aria-label={t('Click the background to close')}
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
                {isEditing ? t('Edit task') : t('Create task')}
            </h2>
            <button
                type="button"
                class="rounded p-1 hover:bg-white/10"
                aria-label={t('Close')}
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
                    {t('Task name')}
                </label>
                <input
                    id="todo-title"
                    bind:value={title}
                    type="text"
                    placeholder={t('Enter task name')}
                    class="w-full rounded-md bg-[#383a40] px-3 py-2.5 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <fieldset>
                    <legend class="mb-1 text-xs font-semibold text-[#b5bac1]">
                        {t('Start date and time')}
                    </legend>
                    <div
                        class="grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-2"
                    >
                        <label for="todo-starts-on" class="sr-only"
                            >{t('Start date')}</label
                        >
                        <input
                            id="todo-starts-on"
                            bind:value={startsOn}
                            type="date"
                            oninput={syncDueFromStart}
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                        />
                        <label for="todo-starts-time" class="sr-only"
                            >{t('Start time')}</label
                        >
                        <TimePicker
                            id="todo-starts-time"
                            bind:value={startsTime}
                            onValueChange={() => syncDueFromStart()}
                        />
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="mb-1 text-xs font-semibold text-[#b5bac1]">
                        {t('End date and time')}
                    </legend>
                    <div
                        class="grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-2"
                    >
                        <label for="todo-due-on" class="sr-only"
                            >{t('End date')}</label
                        >
                        <input
                            id="todo-due-on"
                            bind:value={dueOn}
                            type="date"
                            min={startsOn || undefined}
                            oninput={() => (dueManuallyEdited = true)}
                            class="min-w-0 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                        />
                        <label for="todo-due-time" class="sr-only"
                            >{t('End time')}</label
                        >
                        <TimePicker
                            id="todo-due-time"
                            bind:value={dueTime}
                            onValueChange={() => (dueManuallyEdited = true)}
                        />
                    </div>
                </fieldset>
            </div>

            <div>
                <label
                    for="todo-priority"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    {t('Priority')}
                </label>
                <select
                    id="todo-priority"
                    bind:value={priority}
                    class="w-full rounded-md bg-[#383a40] px-3 py-2.5 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                >
                    <option value="low">{t('Low')}</option>
                    <option value="normal">{t('Normal')}</option>
                    <option value="high">{t('High')}</option>
                    <option value="urgent">{t('Urgent')}</option>
                </select>
            </div>

            <div>
                <label
                    for="todo-details"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    {t('Notes')}
                </label>
                <textarea
                    id="todo-details"
                    bind:value={details}
                    rows={5}
                    placeholder={t('Enter notes')}
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
                {t('Cancel')}
            </button>
            <button
                type="button"
                class="flex items-center gap-2 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4] disabled:opacity-50"
                onclick={saveTodo}
                disabled={saving || !title.trim()}
            >
                {#if saving}
                    <Loader2 class="h-4 w-4 animate-spin" />
                {:else if !isEditing}
                    <Plus class="h-4 w-4" />
                {/if}
                {isEditing ? t('Save') : t('Create')}
            </button>
        </div>
    </div>
</div>

<script lang="ts">
    import { X, Loader2 } from 'lucide-svelte';
    import { apiJson, HttpError } from '@/lib/http';
    import type { ServerResource } from '@/types';

    let {
        server = null,
        onUpdated,
        onClose,
    }: {
        server?: ServerResource | null;
        onUpdated?: (server: ServerResource) => void;
        onClose: () => void;
    } = $props();

    const isEditing = $derived(server !== null);
    let name = $derived(server?.name ?? '');
    let description = $derived(server?.description ?? '');
    let startsOn = $derived(server?.starts_on ?? '');
    let endsOn = $derived(server?.ends_on ?? '');
    let saving = $state(false);
    let error = $state('');

    async function save() {
        if (saving || !name.trim()) {
            return;
        }

        saving = true;
        error = '';

        try {
            const data = await apiJson<{ server: ServerResource }>(
                server ? `/servers/${server.id}` : '/servers',
                {
                    method: server ? 'PATCH' : 'POST',
                    body: JSON.stringify({
                        name: name.trim(),
                        description: description.trim() || null,
                        starts_on: startsOn || null,
                        ends_on: endsOn || null,
                    }),
                },
            );

            if (server) {
                onUpdated?.(data.server);
                onClose();
            } else {
                window.location.href = `/servers/${data.server.id}`;
            }
        } catch (e) {
            error =
                e instanceof HttpError
                    ? e.messageText()
                    : isEditing
                      ? '保存に失敗しました'
                      : '作成に失敗しました';
        } finally {
            saving = false;
        }
    }

    function handleDialogKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            onClose();
        } else if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
            event.preventDefault();
            save();
        }
    }
</script>

<svelte:window onkeydown={handleDialogKeydown} />

<div class="fixed inset-0 z-50 flex items-center justify-center">
    <button
        type="button"
        class="absolute inset-0 bg-black/60"
        aria-label="背景をクリックして閉じる"
        onclick={onClose}
    ></button>
    <div
        class="relative z-10 w-full max-w-md rounded-xl bg-[#313338] p-6 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="server-dialog-title"
    >
        <div class="mb-4 flex items-center justify-between">
            <h2
                id="server-dialog-title"
                class="text-lg font-bold text-[#dbdee1]"
            >
                {isEditing ? 'プロジェクト設定' : 'プロジェクトを作成'}
            </h2>
            <button
                type="button"
                class="rounded p-1 hover:bg-white/10"
                onclick={onClose}
                aria-label="閉じる"
            >
                <X class="h-5 w-5 text-[#80848e]" />
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label
                    for="server-name"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                    >プロジェクト名</label
                >
                <input
                    id="server-name"
                    bind:value={name}
                    type="text"
                    placeholder="例: プロジェクトA"
                    class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                />
            </div>
            <div>
                <label
                    for="server-description"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                    >内容（任意）</label
                >
                <textarea
                    id="server-description"
                    bind:value={description}
                    rows="2"
                    class="w-full resize-none rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                ></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        for="server-starts-on"
                        class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                        >開始日</label
                    >
                    <input
                        id="server-starts-on"
                        bind:value={startsOn}
                        type="date"
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
                <div>
                    <label
                        for="server-ends-on"
                        class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                        >終了日</label
                    >
                    <input
                        id="server-ends-on"
                        bind:value={endsOn}
                        type="date"
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
            </div>
            <p class="text-xs text-[#80848e]">
                開始日・終了日はカレンダーとガントチャートに反映されます。
            </p>

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

        <div class="mt-5 flex justify-end gap-2">
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
                onclick={save}
                disabled={saving || !name.trim()}
            >
                {#if saving}
                    <Loader2 class="h-4 w-4 animate-spin" />
                {/if}
                {isEditing ? '保存' : '作成'}
            </button>
        </div>
    </div>
</div>

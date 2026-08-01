<script lang="ts">
    import { X, Loader2 } from 'lucide-svelte';
    import type { ServerResource } from '@/types';

    let {
        server,
        onClose,
    }: {
        server: ServerResource;
        onClose: () => void;
    } = $props();

    let name = $state('');
    let description = $state('');
    let startsOn = $state('');
    let endsOn = $state('');
    let saving = $state(false);
    let error = $state('');

    function csrfToken(): string {
        return (
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ??
            ''
        );
    }

    async function create() {
        if (!name.trim()) return;
        saving = true;
        error = '';
        try {
            const res = await fetch(`/servers/${server.id}/channels`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({
                    name: name.trim(),
                    description: description.trim() || null,
                    starts_on: startsOn || null,
                    ends_on: endsOn || null,
                }),
            });
            if (res.ok) {
                const data = await res.json();
                window.location.href = `/servers/${server.id}/channels/${data.channel.id}`;
            } else {
                const data = await res.json().catch(() => ({}));
                error = data.message ?? '作成に失敗しました';
            }
        } finally {
            saving = false;
        }
    }
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onclick={onClose}>
    <div
        class="w-full max-w-md rounded-xl bg-[#313338] p-6 shadow-2xl"
        onclick={(e) => e.stopPropagation()}
    >
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-[#dbdee1]">チャンネルを作成</h2>
            <button type="button" class="rounded p-1 hover:bg-white/10" onclick={onClose}>
                <X class="h-5 w-5 text-[#80848e]" />
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-xs font-semibold text-[#b5bac1]">チャンネル名</label>
                <input
                    bind:value={name}
                    type="text"
                    placeholder="例: プロジェクト進行"
                    class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                    onkeydown={(e) => {
                        if (e.key === 'Enter') create();
                    }}
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[#b5bac1]">説明（任意）</label>
                <textarea
                    bind:value={description}
                    rows="2"
                    class="w-full resize-none rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                ></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#b5bac1]">開始日</label>
                    <input
                        bind:value={startsOn}
                        type="date"
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#b5bac1]">終了期限</label>
                    <input
                        bind:value={endsOn}
                        type="date"
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
            </div>
            <p class="text-xs text-[#80848e]">チャンネルはタスクとして機能します。開始日と終了期限を設定できます。</p>

            {#if error}
                <p class="text-sm text-red-400">{error}</p>
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
                onclick={create}
                disabled={saving || !name.trim()}
            >
                {#if saving}
                    <Loader2 class="h-4 w-4 animate-spin" />
                {/if}
                作成
            </button>
        </div>
    </div>
</div>

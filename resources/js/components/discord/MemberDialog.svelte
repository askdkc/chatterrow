<script lang="ts">
    import { X, UserPlus, Loader2 } from 'lucide-svelte';
    import type { ServerResource, UserResource } from '@/types';

    let {
        server,
        members,
        onClose,
    }: {
        server: ServerResource;
        members: UserResource[];
        onClose: () => void;
    } = $props();

    let email = $state('');
    let searching = $state(false);
    let error = $state('');
    let success = $state('');

    function csrfToken(): string {
        return (
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ??
            ''
        );
    }

    async function invite() {
        if (!email.trim()) {
return;
}

        searching = true;
        error = '';
        success = '';

        try {
            const res = await fetch(`/servers/${server.id}/members`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ email: email.trim() }),
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                success = `${data.user?.name ?? email} を追加しました`;
                email = '';
                members = [...members, data.user].filter(
                    (m, i, arr) => arr.findIndex((x) => x.id === m.id) === i,
                );
            } else {
                error = data.message ?? '追加に失敗しました';
            }
        } finally {
            searching = false;
        }
    }

    async function remove(member: UserResource) {
        const res = await fetch(`/servers/${server.id}/members/${member.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });

        if (res.ok) {
            members = members.filter((m) => m.id !== member.id);
        }
    }
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onclick={onClose}>
    <div
        class="w-full max-w-md rounded-xl bg-[#313338] p-6 shadow-2xl"
        onclick={(e) => e.stopPropagation()}
    >
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-[#dbdee1]">サーバー設定</h2>
            <button type="button" class="rounded p-1 hover:bg-white/10" onclick={onClose}>
                <X class="h-5 w-5 text-[#80848e]" />
            </button>
        </div>

        <div class="mb-4">
            <p class="text-sm font-semibold text-[#dbdee1]">{server.name}</p>
            {#if server.description}
                <p class="mt-1 text-sm text-[#80848e]">{server.description}</p>
            {/if}
            {#if server.starts_on || server.ends_on}
                <p class="mt-1 text-xs text-[#b5bac1]">
                    期間: {server.starts_on ?? '未設定'} 〜 {server.ends_on ?? '未設定'}
                </p>
            {/if}
        </div>

        <div class="mb-3 flex gap-2">
            <input
                bind:value={email}
                type="email"
                placeholder="メールアドレスでメンバーを追加"
                class="min-w-0 flex-1 rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                onkeydown={(e) => {
                    if (e.key === 'Enter') {
invite();
}
                }}
            />
            <button
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-md bg-[#23a559] px-3 py-2 text-sm font-medium text-white transition hover:bg-[#1e8b4a] disabled:opacity-50"
                onclick={invite}
                disabled={searching || !email.trim()}
            >
                {#if searching}
                    <Loader2 class="h-4 w-4 animate-spin" />
                {:else}
                    <UserPlus class="h-4 w-4" />
                {/if}
                追加
            </button>
        </div>

        {#if error}
            <p class="mb-2 text-sm text-red-400">{error}</p>
        {/if}
        {#if success}
            <p class="mb-2 text-sm text-green-400">{success}</p>
        {/if}

        <div class="max-h-64 space-y-1 overflow-y-auto">
            {#each members as member (member.id)}
                <div class="flex items-center gap-3 rounded-md px-2 py-1.5 transition hover:bg-white/5">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#5865f2] text-xs font-bold text-white"
                    >
                        {member.name.slice(0, 1).toUpperCase()}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-[#dbdee1]">{member.name}</p>
                        <p class="truncate text-xs text-[#80848e]">{member.email}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 text-[#80848e] transition hover:bg-white/10 hover:text-red-400"
                        onclick={() => remove(member)}
                        title="削除"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            {/each}
        </div>

        <div class="mt-5 flex justify-end">
            <button
                type="button"
                class="rounded-md px-4 py-2 text-sm font-medium text-[#b5bac1] transition hover:bg-white/10"
                onclick={onClose}
            >
                閉じる
            </button>
        </div>
    </div>
</div>

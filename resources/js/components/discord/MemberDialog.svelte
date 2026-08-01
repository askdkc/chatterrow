<script lang="ts">
    import { X, UserPlus, Loader2 } from 'lucide-svelte';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import type { ServerResource, UserResource } from '@/types';

    let {
        server,
        members,
        onUpdated,
        onClose,
    }: {
        server: ServerResource;
        members: UserResource[];
        onUpdated?: (server: ServerResource) => void;
        onClose: () => void;
    } = $props();

    let email = $state('');
    let projectName = $derived(server.name);
    let projectDescription = $derived(server.description ?? '');
    let projectStartsOn = $derived(server.starts_on ?? '');
    let projectEndsOn = $derived(server.ends_on ?? '');
    let savingProject = $state(false);
    let searching = $state(false);
    let error = $state('');
    let success = $state('');

    async function saveProject() {
        if (savingProject || !projectName.trim()) {
            return;
        }

        savingProject = true;
        error = '';
        success = '';

        try {
            const data = await apiJson<{ server: ServerResource }>(
                `/servers/${server.id}`,
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        name: projectName.trim(),
                        description: projectDescription.trim() || null,
                        starts_on: projectStartsOn || null,
                        ends_on: projectEndsOn || null,
                    }),
                },
            );

            onUpdated?.(data.server);
            success = 'プロジェクト情報を保存しました';
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'プロジェクト情報の保存に失敗しました';
        } finally {
            savingProject = false;
        }
    }

    async function invite() {
        if (!email.trim()) {
            return;
        }

        searching = true;
        error = '';
        success = '';

        try {
            const data = await apiJson<{ user: UserResource }>(
                `/servers/${server.id}/members`,
                {
                    method: 'POST',
                    body: JSON.stringify({ email: email.trim() }),
                },
            );

            success = `${data.user?.name ?? email} を追加しました`;
            email = '';
            members = [...members, data.user].filter(
                (m, i, arr) => arr.findIndex((x) => x.id === m.id) === i,
            );
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '追加に失敗しました';
        } finally {
            searching = false;
        }
    }

    async function remove(member: UserResource) {
        try {
            await apiFetch(`/servers/${server.id}/members/${member.id}`, {
                method: 'DELETE',
            });
            members = members.filter((m) => m.id !== member.id);
        } catch (e) {
            error =
                e instanceof HttpError ? e.messageText() : '削除に失敗しました';
        }
    }

    function handleDialogKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            onClose();
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
        class="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-y-auto rounded-xl bg-[#313338] p-6 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="member-dialog-title"
    >
        <div class="mb-4 flex items-center justify-between">
            <h2
                id="member-dialog-title"
                class="text-lg font-bold text-[#dbdee1]"
            >
                プロジェクト設定
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
                    for="project-name"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    プロジェクト名
                </label>
                <input
                    id="project-name"
                    bind:value={projectName}
                    type="text"
                    class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label
                        for="project-starts-on"
                        class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                    >
                        開始日
                    </label>
                    <input
                        id="project-starts-on"
                        bind:value={projectStartsOn}
                        type="date"
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
                <div>
                    <label
                        for="project-ends-on"
                        class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                    >
                        終了日
                    </label>
                    <input
                        id="project-ends-on"
                        bind:value={projectEndsOn}
                        type="date"
                        min={projectStartsOn || undefined}
                        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none focus:ring-1 focus:ring-[#5865f2]"
                    />
                </div>
            </div>
            <div>
                <label
                    for="project-description"
                    class="mb-1 block text-xs font-semibold text-[#b5bac1]"
                >
                    内容
                </label>
                <textarea
                    id="project-description"
                    bind:value={projectDescription}
                    rows={4}
                    placeholder="プロジェクトの内容を入力"
                    class="max-h-48 min-h-24 w-full resize-y rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2]"
                ></textarea>
            </div>
            <div class="flex justify-end">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4] disabled:opacity-50"
                    onclick={saveProject}
                    disabled={savingProject || !projectName.trim()}
                >
                    {#if savingProject}
                        <Loader2 class="h-4 w-4 animate-spin" />
                    {/if}
                    保存
                </button>
            </div>
        </div>

        <div
            class="mb-3 mt-5 border-t border-black/10 pt-4 dark:border-black/20"
        >
            <p class="mb-2 text-xs font-semibold text-[#b5bac1]">メンバー</p>
            <div class="flex gap-2">
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
        </div>

        {#if error}
            <p
                class="mb-2 text-sm text-red-400"
                role="alert"
                aria-live="assertive"
            >
                {error}
            </p>
        {/if}
        {#if success}
            <p class="mb-2 text-sm text-green-400" role="status">{success}</p>
        {/if}

        <div class="max-h-64 space-y-1 overflow-y-auto">
            {#each members as member (member.id)}
                <div
                    class="flex items-center gap-3 rounded-md px-2 py-1.5 transition hover:bg-white/5"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#5865f2] text-xs font-bold text-white"
                    >
                        {member.name.slice(0, 1).toUpperCase()}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-[#dbdee1]">
                            {member.name}
                        </p>
                        <p class="truncate text-xs text-[#80848e]">
                            {member.email}
                        </p>
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

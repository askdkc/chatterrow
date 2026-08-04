<script lang="ts">
    import { CheckCheck, Loader2, Trash2, X } from 'lucide-svelte';
    import { formatDateTime } from '@/lib/dates';
    import { mentionNotificationsState } from '@/lib/mention-notifications.svelte';
    import { safeMentionText } from '@/lib/mentions';
    import type { NotificationResource } from '@/types';

    let { onClose }: { onClose?: () => void } = $props();

    const notifications = mentionNotificationsState();
    const items = $derived(notifications.state.items);
    const unreadTotal = $derived(notifications.state.unreadTotal);
    let deletingId = $state<number | null>(null);
    let deletingAll = $state(false);

    function itemLabel(item: NotificationResource): string {
        return `${item.author?.name ?? '不明'} / ${item.server_name} / #${item.channel_name}`;
    }

    async function openNotification(item: NotificationResource) {
        await notifications.navigateToNotification(item);
        onClose?.();
    }

    async function readAll() {
        try {
            await notifications.markAllRead();
        } catch {
            // Keep the list open when a read-all request fails.
        }
    }

    async function deleteNotification(item: NotificationResource) {
        if (deletingAll || deletingId !== null) {
            return;
        }

        deletingId = item.id;

        try {
            await notifications.remove(item);
        } catch {
            // Keep the notification visible when deletion fails.
        } finally {
            deletingId = null;
        }
    }

    async function deleteAll() {
        if (
            deletingAll ||
            items.length === 0 ||
            (typeof window !== 'undefined' &&
                !window.confirm('すべての通知を削除しますか？'))
        ) {
            return;
        }

        deletingAll = true;

        try {
            await notifications.removeAll();
        } catch {
            // Keep the notification list visible when deletion fails.
        } finally {
            deletingAll = false;
        }
    }
</script>

<section
    class="flex max-h-[min(34rem,calc(100vh-1rem))] min-h-0 flex-col overflow-hidden rounded-2xl border border-black/10 bg-[#2b2d31] text-[#dbdee1] shadow-2xl dark:border-white/10"
    aria-label="通知一覧"
>
    <header
        class="flex shrink-0 items-center gap-2 border-b border-black/10 px-4 py-3 dark:border-white/10"
    >
        <div class="min-w-0 flex-1">
            <h2 class="truncate text-sm font-bold">メンション通知</h2>
            <p class="text-xs text-[#80848e]">未読 {unreadTotal} 件</p>
        </div>
        <button
            type="button"
            class="rounded p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
            onclick={readAll}
            disabled={unreadTotal === 0}
            title="すべて既読"
            aria-label="すべて既読"
        >
            <CheckCheck class="h-4 w-4" />
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-[#6a6f78] transition hover:bg-black/5 hover:text-[#2e3338] disabled:cursor-default disabled:opacity-40 dark:text-[#b5bac1] dark:hover:bg-white/10 dark:hover:text-white"
            onclick={deleteAll}
            disabled={items.length === 0 || deletingAll}
            title="通知をすべて削除"
            aria-label="通知をすべて削除"
        >
            {#if deletingAll}
                <Loader2 class="h-4 w-4 animate-spin" />
            {:else}
                <Trash2 class="h-4 w-4" />
            {/if}
            <span>すべて削除</span>
        </button>
        {#if onClose}
            <button
                type="button"
                class="rounded p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white"
                onclick={onClose}
                title="通知を閉じる"
                aria-label="通知を閉じる"
            >
                <X class="h-4 w-4" />
            </button>
        {/if}
    </header>

    <div class="min-h-0 overflow-y-auto overscroll-contain p-1">
        {#if notifications.state.loading && items.length === 0}
            <div
                class="flex items-center justify-center gap-2 px-4 py-10 text-sm text-[#80848e]"
            >
                <Loader2 class="h-4 w-4 animate-spin" />
                通知を読み込み中
            </div>
        {:else if items.length === 0}
            <p class="px-4 py-10 text-center text-sm text-[#80848e]">
                通知はありません
            </p>
        {:else}
            {#each items as item (item.id)}
                <div
                    class={`group flex w-full items-start rounded-xl border border-transparent transition hover:bg-black/5 dark:hover:bg-white/10 ${item.read_at === null ? 'border-[#5865f2]/40 bg-[#dbeafe] dark:border-[#5865f2]/60 dark:bg-[#5865f2]/30' : ''}`}
                >
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 gap-3 px-3 py-3 text-left"
                        aria-label={itemLabel(item)}
                        onclick={() => openNotification(item)}
                    >
                        <span
                            class="relative mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#5865f2] text-xs font-bold text-white"
                        >
                            {item.author?.name?.slice(0, 1).toUpperCase() ??
                                '?'}
                            {#if item.read_at === null}
                                <span
                                    class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#dbeafe] bg-[#f0b232] dark:border-[#2b2d31]"
                                    aria-label="未読"
                                ></span>
                            {/if}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline gap-1 text-xs">
                                <span
                                    class={`truncate font-semibold ${item.read_at === null ? 'text-[#1e3a8a] dark:text-white' : 'text-[#dbdee1]'}`}
                                >
                                    {item.author?.name ?? '不明'}
                                </span>
                                <span
                                    class={`shrink-0 ${item.read_at === null ? 'text-[#365899] dark:text-[#c9cdfb]' : 'text-[#80848e]'}`}
                                    >がメンション</span
                                >
                            </span>
                            <span
                                class={`mt-0.5 block truncate text-xs ${item.read_at === null ? 'text-[#365899] dark:text-[#c9cdfb]' : 'text-[#80848e]'}`}
                            >
                                {item.server_name} / #{item.channel_name}
                            </span>
                            <span
                                class={`mt-1 block line-clamp-2 break-words text-sm ${item.read_at === null ? 'text-[#365899] dark:text-[#d9dcff]' : 'text-[#b5bac1]'}`}
                            >
                                {safeMentionText(item.excerpt)}
                            </span>
                            <time
                                class={`mt-1 block text-[10px] ${item.read_at === null ? 'text-[#4b5f87] dark:text-[#c9cdfb]' : 'text-[#80848e]'}`}
                                datetime={item.created_at}
                                >{formatDateTime(item.created_at, {
                                    year: false,
                                })}</time
                            >
                        </span>
                    </button>
                    <button
                        type="button"
                        class="mr-2 mt-2 shrink-0 rounded p-1.5 text-[#6a6f78] transition hover:bg-black/10 hover:text-[#2e3338] disabled:cursor-default disabled:opacity-40 dark:text-[#b5bac1] dark:hover:bg-white/10 dark:hover:text-white"
                        onclick={() => deleteNotification(item)}
                        disabled={deletingAll || deletingId !== null}
                        title="通知を削除"
                        aria-label={`${itemLabel(item)}を削除`}
                    >
                        {#if deletingId === item.id}
                            <Loader2 class="h-4 w-4 animate-spin" />
                        {:else}
                            <Trash2 class="h-4 w-4" />
                        {/if}
                    </button>
                </div>
            {/each}
        {/if}
    </div>
</section>

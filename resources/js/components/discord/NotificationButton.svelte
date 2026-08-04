<script lang="ts">
    import { Bell } from 'lucide-svelte';
    import { onDestroy } from 'svelte';
    import NotificationList from '@/components/discord/NotificationList.svelte';
    import * as Popover from '@/components/ui/popover';
    import { mentionNotificationsState } from '@/lib/mention-notifications.svelte';
    import { cn } from '@/lib/utils';

    let {
        collapsed = true,
        open = $bindable(false),
    }: {
        collapsed?: boolean;
        open?: boolean;
    } = $props();

    const notifications = mentionNotificationsState();
    const unreadTotal = $derived(notifications.state.unreadTotal);
    const hoverCloseDelay = 160;
    let hoverOpened = $state(false);
    let hoverClosePending = $state(false);
    let hoverCloseTimer: ReturnType<typeof setTimeout> | null = null;

    function cancelHoverClose() {
        if (hoverCloseTimer !== null) {
            clearTimeout(hoverCloseTimer);
            hoverCloseTimer = null;
        }

        hoverClosePending = false;
    }

    function openFromHover() {
        cancelHoverClose();

        if (!open) {
            hoverOpened = true;
            open = true;
            void notifications.load().catch(() => undefined);
        }
    }

    function closeAfterHover() {
        if (!hoverOpened) {
            return;
        }

        cancelHoverClose();
        hoverClosePending = true;
        hoverCloseTimer = setTimeout(() => {
            hoverCloseTimer = null;
            hoverClosePending = false;
            hoverOpened = false;
            open = false;
        }, hoverCloseDelay);
    }

    function closePopover() {
        cancelHoverClose();
        hoverOpened = false;
        open = false;
    }

    function handleOpenChange(nextOpen: boolean) {
        if (nextOpen) {
            void notifications.load().catch(() => undefined);
        } else {
            cancelHoverClose();
            hoverOpened = false;
        }
    }

    onDestroy(cancelHoverClose);
</script>

<Popover.Root bind:open onOpenChange={handleOpenChange}>
    <Popover.Trigger
        type="button"
        class={cn(
            'group relative mx-2 flex h-10 items-center rounded-xl text-[#dbdee1] transition hover:bg-white/10',
            collapsed ? 'w-12 justify-center' : 'justify-start gap-2 px-3',
        )}
        aria-label="通知"
        title="通知"
        data-hover-opened={hoverOpened}
        data-hover-close-pending={hoverClosePending}
        onmouseenter={openFromHover}
        onmouseleave={closeAfterHover}
    >
        <Bell class="h-5 w-5 shrink-0" />
        {#if !collapsed}
            <span class="truncate text-sm font-medium">通知</span>
        {/if}
        {#if unreadTotal > 0}
            <span
                class={cn(
                    'absolute flex min-w-4 items-center justify-center rounded-full bg-[#f0b232] px-1 text-[10px] font-bold leading-4 text-[#1e1f22]',
                    collapsed ? '-top-1 -right-1' : 'top-1 right-2',
                )}
                aria-label={`未読通知 ${unreadTotal}件`}
                >{unreadTotal > 99 ? '99+' : unreadTotal}</span
            >
        {/if}
    </Popover.Trigger>

    <Popover.Content
        side="right"
        align="start"
        sideOffset={12}
        collisionPadding={8}
        class="w-[min(24rem,calc(100vw-1rem))] gap-0 bg-transparent p-0 shadow-none ring-0"
    >
        <div
            data-notification-hover-surface
            role="group"
            aria-label="通知ポップオーバー"
            onmouseenter={cancelHoverClose}
            onmouseleave={closeAfterHover}
        >
            <NotificationList onClose={closePopover} />
        </div>
    </Popover.Content>
</Popover.Root>

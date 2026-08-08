import { router } from '@inertiajs/svelte';
import { SvelteSet } from 'svelte/reactivity';
import { getEcho } from '@/lib/echo';
import { apiJson } from '@/lib/http';
import { t } from '@/lib/i18n';
import type { NotificationIndexResource, NotificationResource } from '@/types';

export type MentionNotificationState = {
    items: NotificationResource[];
    total: number;
    unreadTotal: number;
    serverCounts: Record<string, number>;
    channelCounts: Record<string, number>;
    nextCursor: string | null;
    loading: boolean;
    loaded: boolean;
    error: string | null;
};

export type MentionNotificationStore = {
    state: MentionNotificationState;
    initialize: (userId: number | null | undefined) => void;
    load: (cursor?: string | null, force?: boolean) => Promise<void>;
    markRead: (notification: NotificationResource) => Promise<void>;
    markAllRead: () => Promise<void>;
    remove: (notification: NotificationResource) => Promise<void>;
    removeAll: () => Promise<void>;
    navigateToNotification: (
        notification: NotificationResource,
    ) => Promise<void>;
    getServerUnreadCount: (serverId: number) => number;
    getChannelUnreadCount: (channelId: number) => number;
};

const state = $state<MentionNotificationState>({
    items: [],
    total: 0,
    unreadTotal: 0,
    serverCounts: {},
    channelCounts: {},
    nextCursor: null,
    loading: false,
    loaded: false,
    error: null,
});

let activeUserId: number | null = null;
let loadPromise: Promise<void> | null = null;
let userChannel: ReturnType<ReturnType<typeof getEcho>['private']> | null =
    null;
const seenNotificationIds = new SvelteSet<number>();

function countMap(value: unknown): Record<string, number> {
    if (!value || typeof value !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(value as Record<string, unknown>).flatMap(
            ([key, count]) => {
                const numeric = Number(count);

                return Number.isFinite(numeric) && numeric > 0
                    ? [[key, numeric]]
                    : [];
            },
        ),
    );
}

function unreadCountsFromItems(items: readonly NotificationResource[]): {
    servers: Record<string, number>;
    channels: Record<string, number>;
} {
    const servers: Record<string, number> = {};
    const channels: Record<string, number> = {};

    for (const item of items) {
        if (item.read_at !== null) {
            continue;
        }

        const serverKey = String(item.server_id);
        const channelKey = String(item.channel_id);
        servers[serverKey] = (servers[serverKey] ?? 0) + 1;
        channels[channelKey] = (channels[channelKey] ?? 0) + 1;
    }

    return { servers, channels };
}

function uniqueNotifications(
    incoming: readonly NotificationResource[],
    existing: readonly NotificationResource[] = [],
): NotificationResource[] {
    const result: NotificationResource[] = [];
    const ids = new SvelteSet<number>();

    for (const item of [...incoming, ...existing]) {
        if (ids.has(item.id)) {
            continue;
        }

        ids.add(item.id);
        seenNotificationIds.add(item.id);
        result.push(item);
    }

    return result;
}

function applyIndexResponse(
    response: NotificationIndexResource,
    append: boolean,
): void {
    const incoming = response.items ?? response.notifications ?? [];
    const items = append
        ? uniqueNotifications(state.items, incoming)
        : uniqueNotifications(incoming, state.items);
    const fallbackCounts = unreadCountsFromItems(items);
    const counts = response.counts;

    state.items = items;
    state.total = Number(response.total ?? counts?.total ?? items.length);
    state.unreadTotal = Number(
        response.unread ??
            counts?.unread ??
            items.filter((item) => !item.read_at).length,
    );
    state.serverCounts = countMap(
        response.server_counts ?? counts?.servers ?? fallbackCounts.servers,
    );
    state.channelCounts = countMap(
        response.channel_counts ?? counts?.channels ?? fallbackCounts.channels,
    );
    state.nextCursor = response.next_cursor ?? null;
    state.loaded = true;
    state.error = null;
}

function incrementCount(counts: Record<string, number>, key: number): void {
    const name = String(key);
    counts[name] = (counts[name] ?? 0) + 1;
}

function decrementCount(counts: Record<string, number>, key: number): void {
    const name = String(key);
    const next = Math.max((counts[name] ?? 1) - 1, 0);

    if (next === 0) {
        delete counts[name];
    } else {
        counts[name] = next;
    }
}

function addRealtimeNotification(notification: NotificationResource): void {
    if (seenNotificationIds.has(notification.id)) {
        return;
    }

    seenNotificationIds.add(notification.id);
    state.items = uniqueNotifications([notification], state.items);
    state.total += 1;

    if (notification.read_at === null) {
        state.unreadTotal += 1;
        incrementCount(state.serverCounts, notification.server_id);
        incrementCount(state.channelCounts, notification.channel_id);
    }
}

function stopUserChannel(): void {
    if (!userChannel || activeUserId === null) {
        return;
    }

    userChannel.stopListening('.MentionNotificationCreated');

    try {
        getEcho().leaveChannel(`private-users-${activeUserId}`);
    } catch {
        // Echo may already have been torn down during a page transition.
    }

    userChannel = null;
}

function subscribeToUser(userId: number): void {
    const channel = getEcho().private(`users.${userId}`);

    userChannel = channel;
    channel.listen(
        '.MentionNotificationCreated',
        (event: { notification: NotificationResource }) => {
            if (event.notification) {
                addRealtimeNotification(event.notification);
            }
        },
    );
}

async function loadNotifications(
    cursor: string | null = null,
    force = false,
): Promise<void> {
    if (activeUserId === null || (state.loaded && !cursor && !force)) {
        return;
    }

    if (loadPromise && !cursor) {
        return loadPromise;
    }

    state.loading = true;
    state.error = null;
    const query = cursor ? `?cursor=${encodeURIComponent(cursor)}` : '';
    const request = apiJson<NotificationIndexResource>(`/notifications${query}`)
        .then((response) => applyIndexResponse(response, Boolean(cursor)))
        .catch((error: unknown) => {
            state.error =
                error instanceof Error
                    ? error.message
                    : t('Failed to load notifications');

            throw error;
        })
        .finally(() => {
            state.loading = false;
            loadPromise = null;
        });

    if (!cursor) {
        loadPromise = request;
    }

    return request;
}

function initialize(userId: number | null | undefined): void {
    if (
        typeof window === 'undefined' ||
        userId === null ||
        userId === undefined
    ) {
        return;
    }

    if (activeUserId === userId) {
        return;
    }

    stopUserChannel();
    activeUserId = userId;
    seenNotificationIds.clear();
    state.items = [];
    state.total = 0;
    state.unreadTotal = 0;
    state.serverCounts = {};
    state.channelCounts = {};
    state.nextCursor = null;
    state.loaded = false;
    state.error = null;

    subscribeToUser(userId);
    void loadNotifications().catch(() => undefined);
}

async function markRead(notification: NotificationResource): Promise<void> {
    await apiJson<{ notification?: NotificationResource }>(
        `/notifications/${notification.id}/read`,
        { method: 'PATCH' },
    );

    const item = state.items.find(
        (candidate) => candidate.id === notification.id,
    );

    if (!item || item.read_at !== null) {
        return;
    }

    item.read_at = new Date().toISOString();
    state.unreadTotal = Math.max(state.unreadTotal - 1, 0);
    decrementCount(state.serverCounts, item.server_id);
    decrementCount(state.channelCounts, item.channel_id);
}

async function markAllRead(): Promise<void> {
    await apiJson('/notifications/read-all', { method: 'PATCH' });

    const readAt = new Date().toISOString();
    state.items = state.items.map((item) => ({
        ...item,
        read_at: item.read_at ?? readAt,
    }));
    state.unreadTotal = 0;
    state.serverCounts = {};
    state.channelCounts = {};
}

async function remove(notification: NotificationResource): Promise<void> {
    await apiJson(`/notifications/${notification.id}`, { method: 'DELETE' });

    const item = state.items.find(
        (candidate) => candidate.id === notification.id,
    );

    if (!item) {
        return;
    }

    state.items = state.items.filter(
        (candidate) => candidate.id !== notification.id,
    );
    state.total = Math.max(state.total - 1, 0);

    if (item.read_at === null) {
        state.unreadTotal = Math.max(state.unreadTotal - 1, 0);
        decrementCount(state.serverCounts, item.server_id);
        decrementCount(state.channelCounts, item.channel_id);
    }
}

async function removeAll(): Promise<void> {
    await apiJson('/notifications', { method: 'DELETE' });

    state.items = [];
    state.total = 0;
    state.unreadTotal = 0;
    state.serverCounts = {};
    state.channelCounts = {};
    state.nextCursor = null;
}

export function notificationTargetUrl(
    notification: NotificationResource,
): string {
    return `/servers/${notification.server_id}/channels/${notification.channel_id}?message=${notification.message_id}`;
}

async function navigateToNotification(
    notification: NotificationResource,
): Promise<void> {
    try {
        await markRead(notification);
    } catch {
        // Navigation remains useful if the read request races a session change.
    }

    router.visit(notificationTargetUrl(notification));
}

function getServerUnreadCount(serverId: number): number {
    return state.serverCounts[String(serverId)] ?? 0;
}

function getChannelUnreadCount(channelId: number): number {
    return state.channelCounts[String(channelId)] ?? 0;
}

const store: MentionNotificationStore = {
    state,
    initialize,
    load: loadNotifications,
    markRead,
    markAllRead,
    remove,
    removeAll,
    navigateToNotification,
    getServerUnreadCount,
    getChannelUnreadCount,
};

export function mentionNotificationsState(): MentionNotificationStore {
    return store;
}

export { addRealtimeNotification };

import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { ConnectionStatus } from 'laravel-echo';
import { apiErrorMessage } from '@/lib/errors';
import { getEcho, isRealtimeConfigured, startEcho, stopEcho } from '@/realtime/echo';
import { notificationsService } from '@/services/notifications';
import type { UserNotification } from '@/types/api';

/** How often the unread badge is refreshed while the tab is visible. */
const POLL_INTERVAL_MS = 30_000;

/**
 * In-app notification state: the recent list shown in the bell dropdown, the
 * unread badge count, and a background poll that resyncs on visibility and
 * reconnect so a dropped connection recovers without a manual refresh.
 *
 * When Reverb is configured a private WebSocket channel delivers a
 * `notification.created` signal; the signal is never trusted as state, it only
 * triggers the same REST resync used by polling, so the API stays authoritative.
 */
export const useNotificationsStore = defineStore('notifications', () => {
    const recent = ref<UserNotification[]>([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const errorMessage = ref<string | null>(null);

    let timer: ReturnType<typeof setInterval> | null = null;
    let inFlight = false;
    let listening = false;
    let channelName: string | null = null;
    let unsubscribeConnection: (() => void) | null = null;

    async function load(silent: boolean): Promise<void> {
        if (inFlight) {
            return;
        }

        inFlight = true;

        if (!silent) {
            loading.value = true;
            errorMessage.value = null;
        }

        try {
            const page = await notificationsService.list({ per_page: 10 });
            recent.value = page.items;
            unreadCount.value = page.unreadCount;
        } catch (error) {
            // A silent resync keeps the last known state and lets polling retry.
            if (!silent) {
                errorMessage.value = apiErrorMessage(error);
            }
        } finally {
            inFlight = false;
            loading.value = false;
        }
    }

    async function refresh(): Promise<void> {
        await load(false);
    }

    async function refreshUnreadCount(): Promise<void> {
        try {
            unreadCount.value = await notificationsService.unreadCount();
        } catch {
            // Keep the last known count; the next tick retries.
        }
    }

    async function markRead(id: string): Promise<void> {
        const target = recent.value.find((item) => item.id === id);

        if (target && !target.read) {
            target.read = true;
            target.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }

        try {
            await notificationsService.markRead(id);
        } catch {
            await refresh();
        }
    }

    async function markAllRead(): Promise<void> {
        const readAt = new Date().toISOString();

        recent.value = recent.value.map((item) => ({
            ...item,
            read: true,
            read_at: item.read_at ?? readAt,
        }));
        unreadCount.value = 0;

        try {
            await notificationsService.markAllRead();
        } catch {
            await refresh();
        }
    }

    function handleVisibility(): void {
        if (document.visibilityState === 'visible') {
            void refreshUnreadCount();
        }
    }

    function handleOnline(): void {
        void refreshUnreadCount();
    }

    /** A realtime event is only a hint that state changed; re-read it via REST. */
    function handleRealtimeSignal(): void {
        void load(true);
    }

    /**
     * Subscribe the signed-in user to their private notification channel. Only
     * the connection is long-lived; the subscription follows the user id, so a
     * different user is unsubscribed before the new one is subscribed.
     */
    function startRealtime(userId: string): void {
        if (!isRealtimeConfigured()) {
            return;
        }

        const instance = startEcho();

        if (instance === null) {
            return;
        }

        if (unsubscribeConnection === null) {
            unsubscribeConnection = instance.connector.onConnectionChange(
                (status: ConnectionStatus) => {
                    if (status === 'connected') {
                        handleRealtimeSignal();
                    }
                },
            );
        }

        const name = `App.Models.User.${userId}`;

        if (channelName === name) {
            return;
        }

        if (channelName !== null) {
            instance.leaveChannel(channelName);
        }

        instance.private(name).listen('.notification.created', handleRealtimeSignal);
        channelName = name;
    }

    /** Leave the private channel and drop the WebSocket connection. */
    function stopRealtime(): void {
        const instance = getEcho();

        if (instance !== null && channelName !== null) {
            instance.leaveChannel(channelName);
        }

        channelName = null;
        unsubscribeConnection?.();
        unsubscribeConnection = null;
        stopEcho();
    }

    function startPolling(): void {
        if (timer !== null) {
            return;
        }

        void refreshUnreadCount();
        timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refreshUnreadCount();
            }
        }, POLL_INTERVAL_MS);

        if (!listening) {
            document.addEventListener('visibilitychange', handleVisibility);
            window.addEventListener('online', handleOnline);
            listening = true;
        }
    }

    function stopPolling(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        if (listening) {
            document.removeEventListener('visibilitychange', handleVisibility);
            window.removeEventListener('online', handleOnline);
            listening = false;
        }
    }

    function reset(): void {
        recent.value = [];
        unreadCount.value = 0;
        errorMessage.value = null;
    }

    return {
        recent,
        unreadCount,
        loading,
        errorMessage,
        refresh,
        refreshUnreadCount,
        markRead,
        markAllRead,
        startRealtime,
        stopRealtime,
        startPolling,
        stopPolling,
        reset,
    };
});

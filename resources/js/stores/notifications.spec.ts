import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useNotificationsStore } from '@/stores/notifications';

const { list, unreadCount, markRead, markAllRead } = vi.hoisted(() => ({
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
}));

vi.mock('@/services/notifications', () => ({
    notificationsService: { list, unreadCount, markRead, markAllRead },
}));

const realtime = vi.hoisted(() => {
    let statusHandler: ((status: string) => void) | null = null;

    const unsubscribeConnection = vi.fn();
    const listen = vi.fn();
    const leaveChannel = vi.fn();
    const onConnectionChange = vi.fn((callback: (status: string) => void) => {
        statusHandler = callback;
        return unsubscribeConnection;
    });
    const echo = {
        private: vi.fn(() => ({ listen })),
        leaveChannel,
        connector: { onConnectionChange },
    };

    return {
        echo,
        listen,
        leaveChannel,
        onConnectionChange,
        unsubscribeConnection,
        isRealtimeConfigured: vi.fn(() => false),
        startEcho: vi.fn(() => echo),
        getEcho: vi.fn(() => echo),
        stopEcho: vi.fn(),
        statusHandler: () => statusHandler,
        resetStatus: () => {
            statusHandler = null;
        },
    };
});

vi.mock('@/realtime/echo', () => ({
    isRealtimeConfigured: realtime.isRealtimeConfigured,
    startEcho: realtime.startEcho,
    getEcho: realtime.getEcho,
    stopEcho: realtime.stopEcho,
}));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    realtime.resetStatus();
});

describe('notifications store', () => {
    it('loads recent notifications and the unread count', async () => {
        list.mockResolvedValue({
            items: [{ id: 'a', read: false, read_at: null, created_at: null }],
            unreadCount: 1,
            nextCursor: null,
        });

        const store = useNotificationsStore();
        await store.refresh();

        expect(store.recent).toHaveLength(1);
        expect(store.unreadCount).toBe(1);
    });

    it('optimistically marks a notification read and decrements the count', async () => {
        list.mockResolvedValue({
            items: [{ id: 'a', read: false, read_at: null, created_at: null }],
            unreadCount: 1,
            nextCursor: null,
        });
        markRead.mockResolvedValue({});

        const store = useNotificationsStore();
        await store.refresh();
        await store.markRead('a');

        expect(store.recent.at(0)?.read).toBe(true);
        expect(store.unreadCount).toBe(0);
        expect(markRead).toHaveBeenCalledWith('a');
    });

    it('resyncs from the server when marking read fails', async () => {
        list.mockResolvedValue({ items: [], unreadCount: 0, nextCursor: null });
        markRead.mockRejectedValue(new Error('offline'));

        const store = useNotificationsStore();
        await store.markRead('a');

        expect(list).toHaveBeenCalled();
    });

    it('marks every notification read and zeroes the count', async () => {
        list.mockResolvedValue({
            items: [
                { id: 'a', read: false, read_at: null, created_at: null },
                { id: 'b', read: false, read_at: null, created_at: null },
            ],
            unreadCount: 2,
            nextCursor: null,
        });
        markAllRead.mockResolvedValue(2);

        const store = useNotificationsStore();
        await store.refresh();
        await store.markAllRead();

        expect(store.unreadCount).toBe(0);
        expect(store.recent.every((item) => item.read)).toBe(true);
    });
});

describe('notifications realtime', () => {
    const emptyPage = { items: [], unreadCount: 0, nextCursor: null };

    beforeEach(() => {
        realtime.isRealtimeConfigured.mockReturnValue(false);
    });

    it('stays on polling when realtime is not configured', () => {
        const store = useNotificationsStore();
        store.startRealtime('user-1');

        expect(realtime.startEcho).not.toHaveBeenCalled();
        expect(realtime.echo.private).not.toHaveBeenCalled();
    });

    it('subscribes to the user private channel and resyncs on a signal', async () => {
        realtime.isRealtimeConfigured.mockReturnValue(true);
        list.mockResolvedValue(emptyPage);

        const store = useNotificationsStore();
        store.startRealtime('user-1');

        expect(realtime.echo.private).toHaveBeenCalledWith('App.Models.User.user-1');
        expect(realtime.listen).toHaveBeenCalledWith('.notification.created', expect.any(Function));

        const handler = realtime.listen.mock.calls[0]?.[1] as (() => void) | undefined;
        handler?.();

        await vi.waitFor(() => expect(list).toHaveBeenCalled());
    });

    it('does not resubscribe to the same channel', () => {
        realtime.isRealtimeConfigured.mockReturnValue(true);

        const store = useNotificationsStore();
        store.startRealtime('user-1');
        store.startRealtime('user-1');

        expect(realtime.echo.private).toHaveBeenCalledTimes(1);
        expect(realtime.leaveChannel).not.toHaveBeenCalled();
    });

    it('leaves the previous channel when the signed-in user changes', () => {
        realtime.isRealtimeConfigured.mockReturnValue(true);

        const store = useNotificationsStore();
        store.startRealtime('user-1');
        store.startRealtime('user-2');

        expect(realtime.leaveChannel).toHaveBeenCalledWith('App.Models.User.user-1');
        expect(realtime.echo.private).toHaveBeenLastCalledWith('App.Models.User.user-2');
    });

    it('resyncs after the socket reconnects', async () => {
        realtime.isRealtimeConfigured.mockReturnValue(true);
        list.mockResolvedValue(emptyPage);

        const store = useNotificationsStore();
        store.startRealtime('user-1');

        realtime.statusHandler()?.('disconnected');
        expect(list).not.toHaveBeenCalled();

        realtime.statusHandler()?.('connected');
        await vi.waitFor(() => expect(list).toHaveBeenCalled());
    });

    it('leaves the channel and disconnects when stopped', () => {
        realtime.isRealtimeConfigured.mockReturnValue(true);

        const store = useNotificationsStore();
        store.startRealtime('user-1');
        store.stopRealtime();

        expect(realtime.leaveChannel).toHaveBeenCalledWith('App.Models.User.user-1');
        expect(realtime.unsubscribeConnection).toHaveBeenCalled();
        expect(realtime.stopEcho).toHaveBeenCalled();
    });
});

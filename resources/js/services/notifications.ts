import { api } from './api/client';
import type { ApiCollection, ApiItem, UserNotification } from '@/types/api';

export interface NotificationQuery {
    unread?: boolean;
    per_page?: number;
    cursor?: string;
}

export interface NotificationPage {
    items: UserNotification[];
    unreadCount: number;
    nextCursor: string | null;
}

export const notificationsService = {
    list: (query: NotificationQuery = {}) =>
        api
            .get<ApiCollection<UserNotification>>('/notifications', {
                query: {
                    unread: query.unread ? 1 : undefined,
                    per_page: query.per_page,
                    cursor: query.cursor,
                },
            })
            .then(
                (r): NotificationPage => ({
                    items: r.data,
                    unreadCount: Number(r.meta.unread_count ?? 0),
                    nextCursor: r.meta.pagination?.next_cursor ?? null,
                }),
            ),

    unreadCount: () =>
        api
            .get<ApiItem<{ unread_count: number }>>('/notifications/unread-count')
            .then((r) => r.data.unread_count),

    markRead: (notificationId: string) =>
        api
            .post<ApiItem<UserNotification>>(`/notifications/${notificationId}/read`)
            .then((r) => r.data),

    markAllRead: () =>
        api
            .post<ApiItem<{ updated: number }>>('/notifications/read-all')
            .then((r) => r.data.updated),
};

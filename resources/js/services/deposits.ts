import { api } from './api/client';
import type { ApiCollection, ApiItem, Deposit } from '@/types/api';

export interface ManualDepositPayload {
    wallet_id: string;
    amount: number;
    metadata?: Record<string, unknown> | null;
}

export interface ProviderDepositPayload {
    wallet_id: string;
    amount: number;
    provider: string;
    metadata?: Record<string, unknown> | null;
}

export const depositsService = {
    list: () => api.get<ApiCollection<Deposit>>('/deposits').then((r) => r.data),

    createManual: (payload: ManualDepositPayload, idempotencyKey: string) =>
        api
            .post<ApiItem<Deposit>>('/deposits', { body: payload, idempotencyKey })
            .then((r) => r.data),

    createProvider: (payload: ProviderDepositPayload, idempotencyKey: string) =>
        api
            .post<ApiItem<Deposit>>('/deposits/provider', { body: payload, idempotencyKey })
            .then((r) => r.data),

    show: (depositId: string) =>
        api.get<ApiItem<Deposit>>(`/deposits/${depositId}`).then((r) => r.data),

    approve: (depositId: string) =>
        api.post<ApiItem<Deposit>>(`/deposits/${depositId}/approve`).then((r) => r.data),

    reject: (depositId: string, reason?: string) =>
        api
            .post<ApiItem<Deposit>>(`/deposits/${depositId}/reject`, { body: { reason } })
            .then((r) => r.data),

    confirm: (depositId: string) =>
        api.post<ApiItem<Deposit>>(`/deposits/${depositId}/confirm`).then((r) => r.data),
};

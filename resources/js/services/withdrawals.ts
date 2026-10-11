import { api } from './api/client';
import type { ApiCollection, ApiItem, Withdrawal } from '@/types/api';

export interface WithdrawalPayload {
    wallet_id: string;
    amount: number;
    fee?: number;
    provider: string;
    destination?: Record<string, unknown> | null;
}

export const withdrawalsService = {
    list: () => api.get<ApiCollection<Withdrawal>>('/withdrawals').then((r) => r.data),

    create: (payload: WithdrawalPayload, idempotencyKey: string) =>
        api
            .post<ApiItem<Withdrawal>>('/withdrawals', { body: payload, idempotencyKey })
            .then((r) => r.data),

    show: (withdrawalId: string) =>
        api.get<ApiItem<Withdrawal>>(`/withdrawals/${withdrawalId}`).then((r) => r.data),

    initiate: (withdrawalId: string) =>
        api.post<ApiItem<Withdrawal>>(`/withdrawals/${withdrawalId}/initiate`).then((r) => r.data),

    cancel: (withdrawalId: string) =>
        api.post<ApiItem<Withdrawal>>(`/withdrawals/${withdrawalId}/cancel`).then((r) => r.data),
};

import { api } from './api/client';
import type { ApiItem, Transfer } from '@/types/api';

export interface TransferPayload {
    sender_wallet_id: string;
    recipient_wallet_id: string;
    currency: string;
    amount: number;
    description?: string | null;
}

export const transfersService = {
    create: (payload: TransferPayload, idempotencyKey: string) =>
        api
            .post<ApiItem<Transfer>>('/transfers', { body: payload, idempotencyKey })
            .then((r) => r.data),

    show: (transferId: string) =>
        api.get<ApiItem<Transfer>>(`/transfers/${transferId}`).then((r) => r.data),
};

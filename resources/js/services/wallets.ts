import { api } from './api/client';
import type {
    ApiCollection,
    ApiItem,
    Wallet,
    WalletBalance,
    WalletStatement,
    WalletTransaction,
} from '@/types/api';

export interface WalletHistoryQuery {
    from?: string;
    to?: string;
    type?: string;
    per_page?: number;
    [key: string]: string | number | boolean | undefined | null;
}

export const walletsService = {
    list: () => api.get<ApiCollection<Wallet>>('/wallets').then((r) => r.data),

    create: (payload: { currency: string; name?: string | null }) =>
        api.post<ApiItem<Wallet>>('/wallets', { body: payload }).then((r) => r.data),

    balance: (walletId: string) =>
        api.get<ApiItem<WalletBalance>>(`/wallets/${walletId}/balance`).then((r) => r.data),

    transactions: (walletId: string, query: WalletHistoryQuery = {}) =>
        api
            .get<ApiCollection<WalletTransaction>>(`/wallets/${walletId}/transactions`, { query })
            .then((r) => r.data),

    statement: (walletId: string, query: { from?: string; to?: string } = {}) =>
        api
            .get<ApiItem<WalletStatement>>(`/wallets/${walletId}/statement`, { query })
            .then((r) => r.data),
};

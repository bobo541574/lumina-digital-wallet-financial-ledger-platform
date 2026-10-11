/**
 * Generate an idempotency key for a money-moving request. The backend requires
 * one for transfers, deposits and withdrawals; the same key must be reused when
 * retrying a request so the server can deduplicate it.
 */
export function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

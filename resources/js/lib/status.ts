export type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const TONE_BY_STATUS: Record<string, BadgeTone> = {
    // Shared success
    succeeded: 'success',
    approved: 'info',

    // In-flight
    pending: 'warning',
    reserved: 'warning',
    processing: 'info',
    reconciling: 'info',

    // Terminal failure / attention
    failed: 'danger',
    quarantined: 'danger',
    mismatch: 'danger',

    // Terminal neutral
    cancelled: 'neutral',
    closed: 'neutral',

    // Wallet
    active: 'success',
    frozen: 'warning',
};

/** Map a backend status string to a Badge tone for consistent colouring. */
export function statusTone(status: string): BadgeTone {
    return TONE_BY_STATUS[status] ?? 'neutral';
}

/** Turn a snake_case status into a human label, e.g. `reconciling` -> `Reconciling`. */
export function humanizeStatus(status: string): string {
    return status
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

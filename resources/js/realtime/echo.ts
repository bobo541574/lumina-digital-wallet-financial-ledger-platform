import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getAuthToken } from '@/services/api/client';

/**
 * The Pusher-protocol WebSocket client used for Laravel Reverb. Realtime is
 * opt-in: when no Reverb app key is configured the app stays on REST polling,
 * so a missing WebSocket host can never break the notification center.
 */
let echo: Echo<'reverb'> | null = null;

function appKey(): string {
    return String(import.meta.env.VITE_REVERB_APP_KEY ?? '');
}

/** Whether a Reverb app key is configured, i.e. WebSocket push is available. */
export function isRealtimeConfigured(): boolean {
    return appKey() !== '';
}

/** The active Echo instance, or null when realtime has not been started. */
export function getEcho(): Echo<'reverb'> | null {
    return echo;
}

/**
 * Create the shared Echo connection. The bearer token is read from the single
 * auth source of truth, and channel authorization hits `/broadcasting/auth`
 * with that token (Sanctum), not a session cookie.
 */
export function startEcho(): Echo<'reverb'> | null {
    if (echo !== null) {
        return echo;
    }

    if (!isRealtimeConfigured()) {
        return null;
    }

    const scheme = String(import.meta.env.VITE_REVERB_SCHEME || 'http');
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    echo = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: appKey(),
        wsHost: String(import.meta.env.VITE_REVERB_HOST || window.location.hostname),
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        bearerToken: getAuthToken(),
        Pusher,
    });

    return echo;
}

/** Tear the connection down and clear the singleton. */
export function stopEcho(): void {
    echo?.disconnect();
    echo = null;
}

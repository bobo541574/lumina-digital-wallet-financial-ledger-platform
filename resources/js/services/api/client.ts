import type { ApiErrorEnvelope } from '@/types/api';

export interface RequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    query?: Record<string, string | number | boolean | undefined | null>;
    headers?: Record<string, string>;
    idempotencyKey?: string;
    signal?: AbortSignal;
}

/**
 * Error thrown for any non-2xx API response. Carries the stable machine-readable
 * `code` from the backend error envelope so callers branch on codes, not strings.
 */
export class ApiError extends Error {
    constructor(
        public readonly code: string,
        message: string,
        public readonly status: number,
        public readonly details: Record<string, unknown> = {},
        public readonly requestId: string | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

const API_BASE = '/api/v1';

let authToken: string | null = null;

/** The token used for authenticated requests. Set by the auth store. */
export function setAuthToken(token: string | null): void {
    authToken = token;
}

/** The token used for authenticated requests, so other clients (e.g. Echo) can reuse it. */
export function getAuthToken(): string | null {
    return authToken;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
    const url = new URL(`${API_BASE}${path}`, window.location.origin);

    if (query) {
        for (const [key, value] of Object.entries(query)) {
            if (value !== undefined && value !== null) {
                url.searchParams.set(key, String(value));
            }
        }
    }

    return url.pathname + url.search;
}

/**
 * Typed JSON request against the versioned API. On failure it always throws an
 * `ApiError`, never returns a partial result.
 */
export async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...options.headers,
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (authToken) {
        headers.Authorization = `Bearer ${authToken}`;
    }

    if (options.idempotencyKey) {
        headers['Idempotency-Key'] = options.idempotencyKey;
    }

    const response = await fetch(buildUrl(path, options.query), {
        method: options.method ?? 'GET',
        headers,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
        signal: options.signal,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = (await response.json().catch(() => null)) as T | ApiErrorEnvelope | null;

    if (!response.ok) {
        const envelope = payload as ApiErrorEnvelope | null;
        const error = envelope?.error;

        throw new ApiError(
            error?.code ?? 'NETWORK_ERROR',
            error?.message ?? 'The request failed.',
            response.status,
            error?.details ?? {},
            error?.request_id ?? null,
        );
    }

    return payload as T;
}

/**
 * Fetch a file (e.g. a CSV statement export) with the auth header attached and
 * return it as a Blob, since browser navigation cannot carry a bearer token.
 */
export async function download(
    path: string,
    query?: RequestOptions['query'],
): Promise<{ blob: Blob; filename: string | null }> {
    const headers: Record<string, string> = { Accept: 'text/csv, application/octet-stream' };

    if (authToken) {
        headers.Authorization = `Bearer ${authToken}`;
    }

    const response = await fetch(buildUrl(path, query), { headers });

    if (!response.ok) {
        let code = 'DOWNLOAD_FAILED';
        let message = 'The download failed.';

        try {
            const envelope = (await response.json()) as ApiErrorEnvelope;
            code = envelope.error?.code ?? code;
            message = envelope.error?.message ?? message;
        } catch {
            // Non-JSON error body; fall back to defaults.
        }

        throw new ApiError(code, message, response.status);
    }

    const disposition = response.headers.get('Content-Disposition');
    const match = disposition?.match(/filename="?([^"]+)"?/);

    return { blob: await response.blob(), filename: match?.[1] ?? null };
}

export const api = {
    get: <T>(path: string, options?: RequestOptions) =>
        request<T>(path, { ...options, method: 'GET' }),
    post: <T>(path: string, options?: RequestOptions) =>
        request<T>(path, { ...options, method: 'POST' }),
    put: <T>(path: string, options?: RequestOptions) =>
        request<T>(path, { ...options, method: 'PUT' }),
    patch: <T>(path: string, options?: RequestOptions) =>
        request<T>(path, { ...options, method: 'PATCH' }),
    delete: <T>(path: string, options?: RequestOptions) =>
        request<T>(path, { ...options, method: 'DELETE' }),
};

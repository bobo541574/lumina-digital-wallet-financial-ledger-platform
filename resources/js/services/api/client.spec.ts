import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, api, request, setAuthToken } from '@/services/api/client';

function jsonResponse(body: unknown, init: { status?: number } = {}): Response {
    const status = init.status ?? 200;

    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
        headers: new Headers(),
    } as unknown as Response;
}

describe('api client', () => {
    beforeEach(() => {
        setAuthToken(null);
    });

    it('returns the parsed body on success', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(
            jsonResponse({ data: { id: '1' }, meta: { request_id: 'r1' } }),
        );

        const result = await request<{ data: { id: string } }>('/wallets');

        expect(result.data.id).toBe('1');
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/v1/wallets',
            expect.objectContaining({ method: 'GET' }),
        );
    });

    it('attaches the bearer token when authenticated', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(jsonResponse({ data: [] }));
        setAuthToken('secret-token');

        await api.get('/wallets');

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect((init.headers as Record<string, string>).Authorization).toBe('Bearer secret-token');
    });

    it('adds an Idempotency-Key header when provided', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(jsonResponse({ data: {} }));

        await api.post('/transfers', { body: { amount: 100 }, idempotencyKey: 'key-123' });

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect((init.headers as Record<string, string>)['Idempotency-Key']).toBe('key-123');
    });

    it('throws an ApiError carrying the error envelope fields', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(
            jsonResponse(
                {
                    error: {
                        code: 'INSUFFICIENT_FUNDS',
                        message: 'Not enough funds.',
                        details: { available: 100 },
                        request_id: 'req-9',
                    },
                },
                { status: 422 },
            ),
        );

        await expect(api.post('/transfers', { body: {} })).rejects.toMatchObject({
            code: 'INSUFFICIENT_FUNDS',
            status: 422,
            requestId: 'req-9',
        });
    });

    it('returns undefined for a 204 No Content response', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue({
            ok: true,
            status: 204,
            headers: new Headers(),
        } as unknown as Response);

        await expect(api.delete('/wallets/1')).resolves.toBeUndefined();
    });

    it('serializes query parameters', async () => {
        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(jsonResponse({ data: [] }));

        await api.get('/wallets/1/transactions', { query: { type: 'transfer', per_page: 10 } });

        const [url] = fetchMock.mock.calls[0] as [string];
        expect(url).toBe('/api/v1/wallets/1/transactions?type=transfer&per_page=10');
    });

    it('exposes ApiError as an instanceof Error subclass', () => {
        const error = new ApiError('X', 'message', 400);
        expect(error).toBeInstanceOf(Error);
        expect(error.name).toBe('ApiError');
    });

    it('uses VITE_API_BASE_URL when configured', async () => {
        vi.resetModules();
        vi.stubEnv('VITE_API_BASE_URL', 'https://api.example.com/v1/');

        const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
        fetchMock.mockResolvedValue(jsonResponse({ data: [] }));

        const mod = await import('@/services/api/client');
        await mod.request('/wallets');

        expect(fetchMock).toHaveBeenCalledWith(
            'https://api.example.com/v1/wallets',
            expect.objectContaining({ method: 'GET' }),
        );

        vi.unstubAllEnvs();
        vi.resetModules();
    });
});

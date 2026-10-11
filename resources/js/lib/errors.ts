import { ApiError } from '@/services/api/client';

/** Extract a user-facing message from any thrown value. */
export function apiErrorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Something went wrong. Please try again.';
}

/** The stable machine-readable error code, when the failure came from the API. */
export function apiErrorCode(error: unknown): string | null {
    return error instanceof ApiError ? error.code : null;
}

/** The request id from the API error envelope, useful for support references. */
export function apiErrorRequestId(error: unknown): string | null {
    return error instanceof ApiError ? error.requestId : null;
}

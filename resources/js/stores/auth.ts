import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ApiError, api, setAuthToken } from '@/services/api/client';
import type { ApiItem, AuthTokenResponse, AuthUser } from '@/types/api';

const TOKEN_STORAGE_KEY = 'lumina.token';

export interface LoginCredentials {
    email: string;
    password: string;
    device_name?: string;
    two_factor_code?: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    device_name?: string;
}

function readStoredToken(): string | null {
    try {
        return window.localStorage.getItem(TOKEN_STORAGE_KEY);
    } catch {
        return null;
    }
}

function persistToken(token: string | null): void {
    try {
        if (token) {
            window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
        } else {
            window.localStorage.removeItem(TOKEN_STORAGE_KEY);
        }
    } catch {
        // Storage may be unavailable (private mode); the in-memory token still works.
    }
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null);
    const token = ref<string | null>(null);
    const loading = ref(false);
    const initialized = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const roles = computed(() => user.value?.roles ?? []);
    const permissions = computed(() => user.value?.permissions ?? []);
    const isAdmin = computed(() => roles.value.includes('admin'));

    function hasPermission(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    function hasRole(role: string): boolean {
        return roles.value.includes(role);
    }

    function applySession(payload: AuthTokenResponse): void {
        user.value = payload.user;
        token.value = payload.token;
        setAuthToken(payload.token);
        persistToken(payload.token);
    }

    function clearSession(): void {
        user.value = null;
        token.value = null;
        setAuthToken(null);
        persistToken(null);
    }

    async function login(credentials: LoginCredentials): Promise<void> {
        loading.value = true;

        try {
            const response = await api.post<ApiItem<AuthTokenResponse>>('/auth/login', {
                body: credentials,
            });

            applySession(response.data);
        } finally {
            loading.value = false;
        }
    }

    async function register(payload: RegisterPayload): Promise<void> {
        loading.value = true;

        try {
            const response = await api.post<ApiItem<AuthTokenResponse>>('/auth/register', {
                body: payload,
            });

            applySession(response.data);
        } finally {
            loading.value = false;
        }
    }

    async function fetchMe(): Promise<void> {
        const response = await api.get<ApiItem<AuthUser>>('/auth/me');
        user.value = response.data;
    }

    async function logout(): Promise<void> {
        try {
            if (token.value) {
                await api.post('/auth/logout');
            }
        } catch (error) {
            if (!(error instanceof ApiError)) {
                throw error;
            }
            // A failed logout still clears the local session.
        } finally {
            clearSession();
        }
    }

    /** Restore a persisted session on app boot, validating the token with the API. */
    async function bootstrap(): Promise<void> {
        const stored = readStoredToken();

        if (stored) {
            token.value = stored;
            setAuthToken(stored);

            try {
                await fetchMe();
            } catch (error) {
                // Only an explicit rejection invalidates the token. Transient
                // failures (offline, or a request aborted by navigation) keep the
                // stored token so the next boot can retry.
                if (error instanceof ApiError && error.status === 401) {
                    clearSession();
                }
            }
        }

        initialized.value = true;
    }

    return {
        user,
        token,
        loading,
        initialized,
        isAuthenticated,
        isAdmin,
        roles,
        permissions,
        hasPermission,
        hasRole,
        login,
        register,
        fetchMe,
        logout,
        bootstrap,
        clearSession,
    };
});

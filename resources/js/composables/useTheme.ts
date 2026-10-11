import { readonly, ref, type DeepReadonly, type Ref } from 'vue';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'lumina.theme';

/**
 * Theme is module-level state so every component that calls `useTheme()` shares
 * one source of truth. The `<html class="dark">` class drives the Tailwind
 * tokens defined in `resources/css/app.css`.
 */
const theme: Ref<Theme> = ref('light');

function readStoredTheme(): Theme | null {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : null;
    } catch {
        return null;
    }
}

function prefersDark(): boolean {
    return typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)').matches
        : false;
}

function applyTheme(value: Theme): void {
    theme.value = value;

    const root = document.documentElement;
    root.classList.toggle('dark', value === 'dark');
    root.style.colorScheme = value;

    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Storage may be unavailable (private mode); the in-memory value still applies.
    }
}

/** Resolve and apply the initial theme. Call once before the app mounts. */
export function initTheme(): void {
    applyTheme(readStoredTheme() ?? (prefersDark() ? 'dark' : 'light'));
}

export function useTheme(): {
    theme: DeepReadonly<Ref<Theme>>;
    isDark: () => boolean;
    setTheme: (value: Theme) => void;
    toggle: () => void;
} {
    return {
        theme: readonly(theme),
        isDark: () => theme.value === 'dark',
        setTheme: applyTheme,
        toggle: () => applyTheme(theme.value === 'dark' ? 'light' : 'dark'),
    };
}

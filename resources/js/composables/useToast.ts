import { readonly, ref, type DeepReadonly, type Ref } from 'vue';

export type ToastVariant = 'info' | 'success' | 'warning' | 'error';

export interface Toast {
    id: number;
    title: string;
    description?: string;
    variant: ToastVariant;
}

const toasts: Ref<Toast[]> = ref([]);
let sequence = 0;
const timers = new Map<number, ReturnType<typeof setTimeout>>();

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);

    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }
}

function push(toast: Omit<Toast, 'id'>, duration = 5000): number {
    const id = ++sequence;
    toasts.value = [...toasts.value, { ...toast, id }];

    if (duration > 0 && typeof setTimeout === 'function') {
        timers.set(
            id,
            setTimeout(() => dismiss(id), duration),
        );
    }

    return id;
}

/**
 * Global toast queue. `useToast()` returns a shared store so any component can
 * raise feedback and the single `<ToastContainer>` renders it. Call
 * `push({ title, variant: 'success' })` for full control or the shorthands.
 */
export function useToast(): {
    toasts: DeepReadonly<Ref<Toast[]>>;
    push: (toast: Omit<Toast, 'id'>, duration?: number) => number;
    dismiss: (id: number) => void;
    success: (title: string, description?: string) => number;
    error: (title: string, description?: string) => number;
    info: (title: string, description?: string) => number;
    warning: (title: string, description?: string) => number;
    clear: () => void;
} {
    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        success: (title, description) => push({ title, description, variant: 'success' }),
        error: (title, description) => push({ title, description, variant: 'error' }),
        info: (title, description) => push({ title, description, variant: 'info' }),
        warning: (title, description) => push({ title, description, variant: 'warning' }),
        clear: () => {
            toasts.value = [];
            timers.forEach((timer) => clearTimeout(timer));
            timers.clear();
        },
    };
}

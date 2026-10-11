import { ref, type Ref } from 'vue';
import { apiErrorMessage } from '@/lib/errors';
import { useToast } from '@/composables/useToast';

/**
 * Run a one-shot async mutation with shared pending state, error capture and
 * toast feedback. Pages use this for admin actions (approve, reverse, ...) so
 * the loading/disable/error handling stays consistent.
 */
export function useAsyncAction(): {
    pending: Ref<boolean>;
    errorMessage: Ref<string | null>;
    run: <T>(
        action: () => Promise<T>,
        options?: { success?: string; onSuccess?: (result: T) => void | Promise<void> },
    ) => Promise<T | null>;
} {
    const pending = ref(false);
    const errorMessage = ref<string | null>(null);
    const toast = useToast();

    async function run<T>(
        action: () => Promise<T>,
        options: { success?: string; onSuccess?: (result: T) => void | Promise<void> } = {},
    ): Promise<T | null> {
        pending.value = true;
        errorMessage.value = null;

        try {
            const result = await action();

            if (options.success) {
                toast.success(options.success);
            }

            await options.onSuccess?.(result);

            return result;
        } catch (error) {
            const message = apiErrorMessage(error);
            errorMessage.value = message;
            toast.error(message);

            return null;
        } finally {
            pending.value = false;
        }
    }

    return { pending, errorMessage, run };
}

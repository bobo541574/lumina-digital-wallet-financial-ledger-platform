import { onMounted, ref, type Ref } from 'vue';
import { apiErrorMessage } from '@/lib/errors';

/**
 * Load a list of items once on mount and expose loading/error state. Keeps the
 * admin list pages to a few lines. Pass a fetcher that resolves to an array of
 * items (the service layer already unwraps the API envelope).
 */
export function useAdminList<T>(fetcher: () => Promise<T[]>): {
    items: Ref<T[]>;
    loading: Ref<boolean>;
    errorMessage: Ref<string | null>;
    reload: () => Promise<void>;
} {
    const items = ref<T[]>([]) as Ref<T[]>;
    const loading = ref(true);
    const errorMessage = ref<string | null>(null);

    async function reload(): Promise<void> {
        loading.value = true;
        errorMessage.value = null;

        try {
            items.value = await fetcher();
        } catch (error) {
            errorMessage.value = apiErrorMessage(error);
        } finally {
            loading.value = false;
        }
    }

    onMounted(reload);

    return { items, loading, errorMessage, reload };
}

<script setup lang="ts">
import { useToast, type ToastVariant } from '@/composables/useToast';

const { toasts, dismiss } = useToast();

const styles: Record<ToastVariant, string> = {
    info: 'border-border bg-card',
    success: 'border-success/40 bg-success/10',
    warning: 'border-warning/40 bg-warning/10',
    error: 'border-destructive/40 bg-destructive/10',
};
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end"
        aria-live="polite"
        aria-atomic="false"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :role="toast.variant === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto w-full max-w-sm rounded-lg border px-4 py-3 shadow-lg"
            :class="styles[toast.variant]"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium">{{ toast.title }}</p>
                    <p v-if="toast.description" class="mt-0.5 text-sm text-muted-foreground">
                        {{ toast.description }}
                    </p>
                </div>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground"
                    :aria-label="`Dismiss ${toast.title}`"
                    @click="dismiss(toast.id)"
                >
                    ×
                </button>
            </div>
        </div>
    </div>
</template>

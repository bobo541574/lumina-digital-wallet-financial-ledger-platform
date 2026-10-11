<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import Button from '@/components/ui/Button.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'primary' | 'destructive' | 'outline';
        loading?: boolean;
        requireReason?: boolean;
        reasonLabel?: string;
    }>(),
    {
        description: undefined,
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'primary',
        loading: false,
        requireReason: false,
        reasonLabel: 'Reason',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm', reason: string): void;
    (e: 'cancel'): void;
}>();

const reasonId = useId();
const reason = ref('');
const panel = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

function close(): void {
    if (props.loading) {
        return;
    }

    emit('update:open', false);
    emit('cancel');
}

function confirm(): void {
    emit('confirm', reason.value.trim());
}

function trapFocus(event: KeyboardEvent): void {
    const container = panel.value;

    if (!container) {
        return;
    }

    const focusable = container.querySelectorAll<HTMLElement>(
        'button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), ' +
            'select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
        return;
    }

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (event.key === 'Tab') {
        trapFocus(event);
    }
}

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            previouslyFocused?.focus();
            return;
        }

        reason.value = '';
        previouslyFocused = document.activeElement as HTMLElement | null;
        await nextTick();
        panel.value?.querySelector<HTMLElement>('textarea, button:not([disabled])')?.focus();
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown="onKeydown"
        >
            <div class="fixed inset-0 bg-black/50" aria-hidden="true" @click="close" />
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-xl"
            >
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <p v-if="description" class="mt-2 text-sm text-muted-foreground">
                    {{ description }}
                </p>

                <div v-if="$slots.default" class="mt-4">
                    <slot />
                </div>

                <div v-if="requireReason" class="mt-4">
                    <label :for="reasonId" class="mb-1.5 block text-sm font-medium">
                        {{ reasonLabel }}
                    </label>
                    <textarea
                        :id="reasonId"
                        v-model="reason"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" :disabled="loading" @click="close">
                        {{ cancelLabel }}
                    </Button>
                    <Button
                        :variant="variant"
                        :loading="loading"
                        :disabled="requireReason && reason.trim() === ''"
                        @click="confirm"
                    >
                        {{ confirmLabel }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type AlertVariant = 'info' | 'success' | 'warning' | 'error';

const props = withDefaults(defineProps<{ variant?: AlertVariant; title?: string }>(), {
    variant: 'info',
    title: undefined,
});

const variants: Record<AlertVariant, string> = {
    info: 'border-border bg-muted text-foreground',
    success: 'border-success/40 bg-success/10 text-foreground',
    warning: 'border-warning/40 bg-warning/10 text-foreground',
    error: 'border-destructive/40 bg-destructive/10 text-foreground',
};

const isError = computed(() => props.variant === 'error' || props.variant === 'warning');
</script>

<template>
    <div
        :role="isError ? 'alert' : 'status'"
        :class="cn('rounded-lg border px-4 py-3 text-sm', variants[variant])"
    >
        <p v-if="title" class="font-medium">{{ title }}</p>
        <div :class="title ? 'mt-1' : ''">
            <slot />
        </div>
    </div>
</template>

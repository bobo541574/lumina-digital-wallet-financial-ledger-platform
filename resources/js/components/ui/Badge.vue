<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const props = withDefaults(defineProps<{ tone?: BadgeTone }>(), { tone: 'neutral' });

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    success: 'bg-success/15 text-success',
    warning: 'bg-warning/15 text-warning',
    danger: 'bg-destructive/15 text-destructive',
    info: 'bg-primary/15 text-primary',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        tones[props.tone],
    ),
);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>

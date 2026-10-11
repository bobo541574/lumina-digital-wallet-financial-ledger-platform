<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div :class="cn('rounded-xl border border-border bg-card text-card-foreground shadow-sm')">
        <div v-if="title || description || $slots.header" class="border-b border-border px-6 py-4">
            <slot name="header">
                <h3 v-if="title" class="text-base font-semibold">{{ title }}</h3>
                <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </slot>
        </div>
        <div class="px-6 py-4">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-border px-6 py-4">
            <slot name="footer" />
        </div>
    </div>
</template>

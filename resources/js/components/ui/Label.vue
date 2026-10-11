<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    for?: string;
    required?: boolean;
}>();

const labelClass = computed(() => cn('block text-sm font-medium text-foreground'));
</script>

<template>
    <label :for="props.for" :class="labelClass">
        <slot />
        <span v-if="required" class="text-destructive" aria-hidden="true">*</span>
    </label>
</template>

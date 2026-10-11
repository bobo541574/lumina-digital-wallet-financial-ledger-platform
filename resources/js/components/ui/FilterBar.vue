<script setup lang="ts">
withDefaults(
    defineProps<{ hasActiveFilters?: boolean; clearLabel?: string }>(),
    { hasActiveFilters: false, clearLabel: 'Clear filters' },
);

defineEmits<{ (e: 'clear'): void }>();
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <slot />
        <button
            v-if="hasActiveFilters"
            type="button"
            class="pb-2 text-sm text-muted-foreground hover:text-foreground"
            @click="$emit('clear')"
        >
            {{ clearLabel }}
        </button>
    </div>
</template>

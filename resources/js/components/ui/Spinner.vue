<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Loading' });
</script>

<template>
    <div
        class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground"
        role="status"
    >
        <span
            class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent"
            aria-hidden="true"
        />
        <span>{{ label }}</span>
    </div>
</template>

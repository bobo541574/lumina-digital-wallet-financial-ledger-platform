<script setup lang="ts">
const model = defineModel<string>({ default: '' });

withDefaults(defineProps<{ placeholder?: string; label?: string; id?: string }>(), {
    placeholder: 'Search',
    label: 'Search',
    id: undefined,
});
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        >
            ⌕
        </span>
        <input
            :id="id"
            v-model="model"
            type="search"
            :placeholder="placeholder"
            :aria-label="label"
            class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        />
    </div>
</template>

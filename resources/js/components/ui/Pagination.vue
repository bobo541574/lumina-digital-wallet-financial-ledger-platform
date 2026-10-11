<script setup lang="ts">
import Button from '@/components/ui/Button.vue';

withDefaults(
    defineProps<{ nextCursor?: string | null; prevCursor?: string | null; loading?: boolean }>(),
    { nextCursor: null, prevCursor: null, loading: false },
);

defineEmits<{ (e: 'next'): void; (e: 'prev'): void }>();
</script>

<template>
    <nav class="flex items-center justify-between" aria-label="Pagination">
        <Button
            variant="outline"
            size="sm"
            :disabled="!prevCursor || loading"
            @click="$emit('prev')"
        >
            Previous
        </Button>
        <Button variant="outline" size="sm" :disabled="!nextCursor || loading" @click="$emit('next')">
            Next
        </Button>
    </nav>
</template>

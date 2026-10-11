<script setup lang="ts">
import Card from '@/components/ui/Card.vue';

withDefaults(defineProps<{ title: string; description?: string }>(), { description: undefined });
</script>

<template>
    <Card :title="title" :description="description">
        <slot />
        <template v-if="$slots.actions" #footer>
            <div class="flex justify-end gap-2">
                <slot name="actions" />
            </div>
        </template>
    </Card>
</template>

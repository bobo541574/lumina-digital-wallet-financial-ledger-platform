<script setup lang="ts">
import { computed } from 'vue';
import Label from '@/components/ui/Label.vue';
import InlineError from '@/components/ui/InlineError.vue';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        required?: boolean;
        description?: string;
        error?: string | null;
    }>(),
    {
        required: false,
        description: undefined,
        error: null,
    },
);

const descriptionId = computed(() => (props.description ? `${props.id}-description` : undefined));
const errorId = computed(() => (props.error ? `${props.id}-error` : undefined));

/** Space-separated ids an input should pass to `aria-describedby`. */
const describedBy = computed(() => {
    const ids = [descriptionId.value, errorId.value].filter((id): id is string => Boolean(id));

    return ids.length > 0 ? ids.join(' ') : undefined;
});
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id" :required="required">{{ label }}</Label>
        <slot :id="id" :described-by="describedBy" :invalid="Boolean(error)" />
        <p v-if="description" :id="descriptionId" class="text-xs text-muted-foreground">
            {{ description }}
        </p>
        <InlineError v-if="error" :id="errorId" :message="error" />
    </div>
</template>

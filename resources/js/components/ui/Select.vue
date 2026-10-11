<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        options: Array<{ value: string; label: string }>;
        placeholder?: string;
        id?: string;
        disabled?: boolean;
        /** Accessible name when the select is not associated with a visible label. */
        ariaLabel?: string;
        class?: string;
    }>(),
    {
        modelValue: '',
        placeholder: 'Select an option',
        disabled: false,
        id: undefined,
        ariaLabel: undefined,
        class: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const selectedValue = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});
</script>

<template>
    <select
        :id="id"
        v-model="selectedValue"
        :disabled="disabled"
        :aria-label="ariaLabel"
        :class="
            cn(
                'flex h-10 w-full appearance-none rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
        </option>
    </select>
</template>

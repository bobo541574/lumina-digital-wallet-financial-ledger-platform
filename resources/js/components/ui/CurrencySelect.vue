<script setup lang="ts">
import { computed } from 'vue';
import Select from '@/components/ui/Select.vue';
import { SUPPORTED_CURRENCIES } from '@/lib/format/money';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        currencies?: readonly string[];
        placeholder?: string;
        id?: string;
        disabled?: boolean;
    }>(),
    {
        modelValue: '',
        currencies: undefined,
        placeholder: 'Select currency',
        id: undefined,
        disabled: false,
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const options = computed(() =>
    (props.currencies ?? SUPPORTED_CURRENCIES).map((code) => ({ value: code, label: code })),
);

const selected = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});
</script>

<template>
    <Select
        :id="id"
        v-model="selected"
        :options="options"
        :placeholder="placeholder"
        :disabled="disabled"
    />
</template>

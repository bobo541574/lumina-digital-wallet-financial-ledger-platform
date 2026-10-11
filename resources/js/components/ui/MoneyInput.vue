<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Input from '@/components/ui/Input.vue';
import { formatMinorPlain, parseAmountToMinor } from '@/lib/format/money';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        currency: string;
        id?: string;
        placeholder?: string;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    { id: undefined, placeholder: '0.00', disabled: false, invalid: false },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'update:valid', value: boolean): void;
}>();

const raw = ref(props.modelValue === null ? '' : formatMinorPlain(props.modelValue, props.currency));
const error = ref<string | null>(null);
let focused = false;

const display = computed<string>({
    get: () => raw.value,
    set: (value: string) => {
        raw.value = value;
        handleChange(value);
    },
});

function handleChange(value: string): void {
    const cleaned = value.replace(/[^\d.,]/g, '');

    if (cleaned.trim() === '') {
        error.value = null;
        emit('update:modelValue', null);
        emit('update:valid', false);
        return;
    }

    try {
        const minor = parseAmountToMinor(cleaned, props.currency);
        error.value = null;
        emit('update:modelValue', minor);
        emit('update:valid', true);
    } catch (parseError) {
        error.value = parseError instanceof Error ? parseError.message : 'Enter a valid amount.';
        emit('update:modelValue', null);
        emit('update:valid', false);
    }
}

watch(
    () => props.modelValue,
    (value) => {
        if (focused) {
            return;
        }

        raw.value = value === null ? '' : formatMinorPlain(value, props.currency);
    },
);

watch(
    () => props.currency,
    () => {
        if (props.modelValue !== null) {
            raw.value = formatMinorPlain(props.modelValue, props.currency);
        }
    },
);

function onFocus(): void {
    focused = true;
}

function onBlur(): void {
    focused = false;

    if (props.modelValue !== null) {
        raw.value = formatMinorPlain(props.modelValue, props.currency);
    }
}
</script>

<template>
    <div>
        <Input
            :id="id"
            v-model="display"
            inputmode="decimal"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-invalid="invalid || Boolean(error) || undefined"
            @focus="onFocus"
            @blur="onBlur"
        />
        <p v-if="error" role="alert" class="mt-1 text-xs text-destructive">{{ error }}</p>
    </div>
</template>

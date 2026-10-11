<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney } from '@/lib/format/money';

const props = defineProps<{
    amount: number;
    currency: string;
    /** Render negative amounts in the destructive colour. */
    coloured?: boolean;
    /** Force a leading +/− sign. */
    signed?: boolean;
}>();

const text = computed(() => {
    const formatted = formatMoney(Math.abs(props.amount), props.currency);
    const sign = props.signed ? (props.amount < 0 ? '−' : '+') : props.amount < 0 ? '−' : '';
    return `${sign}${formatted}`;
});

const classes = computed(() => {
    if (!props.coloured) {
        return '';
    }

    return props.amount < 0 ? 'text-destructive' : 'text-success';
});
</script>

<template>
    <span :class="classes">{{ text }}</span>
</template>

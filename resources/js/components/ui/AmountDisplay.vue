<script setup lang="ts">
import { computed } from 'vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import { cn } from '@/lib/utils';

type Size = 'sm' | 'md' | 'lg';
type Weight = 'normal' | 'medium' | 'semibold';

const props = withDefaults(
    defineProps<{
        amount: number;
        currency: string;
        signed?: boolean;
        coloured?: boolean;
        size?: Size;
        weight?: Weight;
    }>(),
    { signed: false, coloured: false, size: 'md', weight: 'normal' },
);

const sizes: Record<Size, string> = { sm: 'text-sm', md: 'text-base', lg: 'text-2xl' };
const weights: Record<Weight, string> = {
    normal: 'font-normal',
    medium: 'font-medium',
    semibold: 'font-semibold',
};

const classes = computed(() => cn('tabular-nums', sizes[props.size], weights[props.weight]));
</script>

<template>
    <MoneyText
        :amount="amount"
        :currency="currency"
        :signed="signed"
        :coloured="coloured"
        :class="classes"
    />
</template>

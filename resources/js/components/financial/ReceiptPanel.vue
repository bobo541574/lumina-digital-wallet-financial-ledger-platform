<script setup lang="ts">
import MoneyText from '@/components/ui/MoneyText.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import type { DescriptionItem } from '@/types/ui';

withDefaults(
    defineProps<{
        title: string;
        reference: string;
        status?: string;
        amount?: number | null;
        currency?: string;
        rows?: DescriptionItem[];
        timestamp?: string | null;
    }>(),
    {
        status: undefined,
        amount: null,
        currency: undefined,
        rows: () => [],
        timestamp: null,
    },
);
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-6" aria-label="Transaction receipt">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ reference }}</p>
            </div>
            <StatusBadge v-if="status" :status="status" />
        </header>

        <p v-if="amount !== null && currency" class="mt-4 text-3xl font-semibold">
            <MoneyText :amount="amount" :currency="currency" />
        </p>

        <dl class="mt-4 space-y-2 text-sm">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex justify-between gap-4 border-t border-border pt-2"
            >
                <dt class="text-muted-foreground">{{ row.label }}</dt>
                <dd class="text-right font-medium">{{ row.value }}</dd>
            </div>
        </dl>

        <p v-if="timestamp" class="mt-4 text-xs text-muted-foreground">
            {{ timestamp.slice(0, 19).replace('T', ' ') }}
        </p>
    </section>
</template>

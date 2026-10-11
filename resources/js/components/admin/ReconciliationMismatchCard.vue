<script setup lang="ts">
import { computed } from 'vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import type { ReconciliationItem } from '@/types/api';

const props = defineProps<{ item: ReconciliationItem }>();

const classification = computed(() => props.item.classification.replace(/_/g, ' '));
const subject = computed(() =>
    props.item.subject_id
        ? `${props.item.subject_type ?? 'subject'} ${props.item.subject_id.slice(0, 8)}`
        : (props.item.subject_type ?? '—'),
);
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium capitalize">{{ classification }}</p>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ subject }}</p>
            </div>
            <StatusBadge :status="item.status" />
        </div>

        <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Expected</dt>
                <dd><MoneyText :amount="item.expected" :currency="item.currency" /></dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Actual</dt>
                <dd><MoneyText :amount="item.actual" :currency="item.currency" /></dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Difference</dt>
                <dd>
                    <MoneyText
                        :amount="item.difference"
                        :currency="item.currency"
                        coloured
                        signed
                    />
                </dd>
            </div>
        </dl>

        <div v-if="$slots.actions" class="mt-4 flex justify-end gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

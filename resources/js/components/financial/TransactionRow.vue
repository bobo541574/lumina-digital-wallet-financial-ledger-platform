<script setup lang="ts">
import MoneyText from '@/components/ui/MoneyText.vue';
import type { WalletTransaction } from '@/types/api';

defineProps<{ transaction: WalletTransaction }>();
</script>

<template>
    <tr class="border-t border-border">
        <td class="px-4 py-3 text-muted-foreground">
            {{ transaction.occurred_at?.slice(0, 10) ?? '—' }}
        </td>
        <td class="px-4 py-3">
            {{ transaction.description ?? transaction.source_type ?? '—' }}
        </td>
        <td class="px-4 py-3 text-muted-foreground">{{ transaction.entry_type ?? '—' }}</td>
        <td class="px-4 py-3 text-right font-medium">
            <MoneyText
                :amount="transaction.signed_amount"
                :currency="transaction.currency"
                coloured
                signed
            />
        </td>
    </tr>
</template>

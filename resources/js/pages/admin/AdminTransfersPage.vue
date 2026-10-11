<script setup lang="ts">
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import type { Transfer } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ResourceTable from '@/components/layout/ResourceTable.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const { items, loading, errorMessage } = useAdminList<Transfer>(() => adminService.transfers());
</script>

<template>
    <div>
        <PageHeader title="Transfers" description="Internal transfers between wallets." />
        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <ResourceTable
            :columns="[
                { key: 'reference', label: 'Reference' },
                { key: 'sender', label: 'Sender' },
                { key: 'recipient', label: 'Recipient' },
                { key: 'status', label: 'Status' },
                { key: 'amount', label: 'Amount', align: 'right' },
            ]"
            :loading="loading"
            :empty="items.length === 0"
            :colspan="5"
        >
            <tr v-for="transfer in items" :key="transfer.id" class="border-t border-border">
                <td class="px-4 py-3 font-mono text-xs">{{ transfer.reference }}</td>
                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ transfer.sender_wallet_id }}
                </td>
                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ transfer.recipient_wallet_id }}
                </td>
                <td class="px-4 py-3"><StatusBadge :status="transfer.status" /></td>
                <td class="px-4 py-3 text-right">
                    <MoneyText :amount="transfer.amount" :currency="transfer.currency" />
                </td>
            </tr>
        </ResourceTable>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { withdrawalsService } from '@/services/withdrawals';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import type { Withdrawal } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FilterBar from '@/components/ui/FilterBar.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import PermissionGate from '@/components/ui/PermissionGate.vue';
import Select from '@/components/ui/Select.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import DataTable from '@/components/ui/DataTable.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const { items, loading, errorMessage, reload } = useAdminList<Withdrawal>(() =>
    adminService.withdrawals(),
);
const { pending, run } = useAsyncAction();

const statusFilter = ref('');
const filtered = computed(() =>
    statusFilter.value
        ? items.value.filter((w) => w.status === statusFilter.value)
        : items.value,
);
const hasActiveFilters = computed(() => statusFilter.value !== '');

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'reserved', label: 'Reserved' },
    { value: 'processing', label: 'Processing' },
    { value: 'reconciling', label: 'Reconciling' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
];

type ReviewAction = 'initiate' | 'cancel';

const review = ref<{ withdrawal: Withdrawal; action: ReviewAction } | null>(null);

function ask(withdrawal: Withdrawal, action: ReviewAction): void {
    review.value = { withdrawal, action };
}

const dialogTitle = computed(() => {
    if (!review.value) {
        return '';
    }

    return review.value.action === 'initiate'
        ? `Initiate payout ${review.value.withdrawal.reference}`
        : `Cancel withdrawal ${review.value.withdrawal.reference}`;
});

const dialogDescription = computed(() =>
    review.value?.action === 'initiate'
        ? 'Reserve the hold and instruct the payout provider. Unknown provider outcomes stay reconciling.'
        : 'Release the hold and cancel the request. Funds return to the available balance.',
);

async function confirmAction(): Promise<void> {
    const target = review.value;

    if (!target) {
        return;
    }

    const succeeded = await run(
        () =>
            target.action === 'initiate'
                ? withdrawalsService.initiate(target.withdrawal.id)
                : withdrawalsService.cancel(target.withdrawal.id),
        {
            success:
                target.action === 'initiate'
                    ? 'Payout initiated.'
                    : 'Withdrawal cancelled.',
        },
    );

    if (succeeded !== null) {
        review.value = null;
        await reload();
    }
}
</script>

<template>
    <div>
        <PageHeader
            title="Withdrawals"
            description="Review payout requests, initiate provider payouts, and release holding state."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <FilterBar class="mb-4" :has-active-filters="hasActiveFilters" @clear="statusFilter = ''">
            <div class="w-48">
                <Select
                    id="withdrawal-status"
                    v-model="statusFilter"
                    :options="statusOptions"
                    placeholder="All statuses"
                    aria-label="Filter by status"
                />
            </div>
        </FilterBar>

        <DataTable
            :columns="[
                { key: 'reference', label: 'Reference' },
                { key: 'provider', label: 'Provider' },
                { key: 'status', label: 'Status' },
                { key: 'amount', label: 'Amount', align: 'right' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="loading"
            :empty="filtered.length === 0"
            empty-text="No withdrawals match these filters."
        >
            <tr v-for="withdrawal in filtered" :key="withdrawal.id" class="border-t border-border">
                <td class="px-4 py-3 font-mono text-xs">{{ withdrawal.reference }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ withdrawal.provider }}</td>
                <td class="px-4 py-3"><StatusBadge :status="withdrawal.status" /></td>
                <td class="px-4 py-3 text-right">
                    <MoneyText :amount="withdrawal.amount" :currency="withdrawal.currency" />
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                        <PermissionGate permission="withdrawals.process">
                            <Button
                                v-if="withdrawal.status === 'reserved'"
                                size="sm"
                                @click="ask(withdrawal, 'initiate')"
                            >
                                Initiate payout
                            </Button>
                        </PermissionGate>
                        <PermissionGate permission="withdrawals.approve">
                            <Button
                                v-if="
                                    withdrawal.status === 'pending' ||
                                    withdrawal.status === 'reserved'
                                "
                                size="sm"
                                variant="ghost"
                                @click="ask(withdrawal, 'cancel')"
                            >
                                Cancel
                            </Button>
                        </PermissionGate>
                    </div>
                </td>
            </tr>
        </DataTable>

        <ConfirmDialog
            :open="review !== null"
            :title="dialogTitle"
            :description="dialogDescription"
            :variant="review?.action === 'cancel' ? 'destructive' : 'primary'"
            :require-reason="review?.action === 'cancel'"
            confirm-label="Confirm"
            :loading="pending"
            @update:open="(open) => { if (!open) review = null; }"
            @confirm="confirmAction"
        />
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { depositsService } from '@/services/deposits';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import type { Deposit } from '@/types/api';
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

const { items, loading, errorMessage, reload } = useAdminList<Deposit>(() =>
    adminService.deposits(),
);
const { pending, run } = useAsyncAction();

const statusFilter = ref('');
const filtered = computed(() =>
    statusFilter.value ? items.value.filter((d) => d.status === statusFilter.value) : items.value,
);
const hasActiveFilters = computed(() => statusFilter.value !== '');

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'failed', label: 'Failed' },
    { value: 'quarantined', label: 'Quarantined' },
    { value: 'cancelled', label: 'Cancelled' },
];

type ReviewAction = 'approve' | 'reject' | 'confirm';

const review = ref<{ deposit: Deposit; action: ReviewAction } | null>(null);

function ask(deposit: Deposit, action: ReviewAction): void {
    review.value = { deposit, action };
}

const dialogTitle = computed(() => {
    if (!review.value) {
        return '';
    }

    return {
        approve: `Approve deposit ${review.value.deposit.reference}`,
        reject: `Reject deposit ${review.value.deposit.reference}`,
        confirm: `Confirm deposit ${review.value.deposit.reference}`,
    }[review.value.action];
});

const dialogDescription = computed(() =>
    review.value
        ? `This posts the ledger entry and credits the customer wallet.`
        : '',
);

async function confirmAction(reason: string): Promise<void> {
    const target = review.value;

    if (!target) {
        return;
    }

    const succeeded = await run(
        () =>
            target.action === 'approve'
                ? depositsService.approve(target.deposit.id)
                : target.action === 'reject'
                  ? depositsService.reject(target.deposit.id, reason)
                  : depositsService.confirm(target.deposit.id),
        {
            success:
                target.action === 'approve'
                    ? 'Deposit approved.'
                    : target.action === 'reject'
                      ? 'Deposit rejected.'
                      : 'Deposit confirmed.',
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
            title="Deposits"
            description="Review and approve manual top-ups; provider deposits settle by webhook."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <FilterBar
            class="mb-4"
            :has-active-filters="hasActiveFilters"
            @clear="statusFilter = ''"
        >
            <div class="w-48">
                <Select
                    id="deposit-status"
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
                { key: 'channel', label: 'Channel' },
                { key: 'status', label: 'Status' },
                { key: 'amount', label: 'Amount', align: 'right' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="loading"
            :empty="filtered.length === 0"
            empty-text="No deposits match these filters."
        >
            <tr v-for="deposit in filtered" :key="deposit.id" class="border-t border-border">
                <td class="px-4 py-3 font-mono text-xs">{{ deposit.reference }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ deposit.channel }}</td>
                <td class="px-4 py-3"><StatusBadge :status="deposit.status" /></td>
                <td class="px-4 py-3 text-right">
                    <MoneyText :amount="deposit.amount" :currency="deposit.currency" />
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                        <PermissionGate
                            permission="deposits.approve"
                            :require-all="false"
                        >
                            <template v-if="deposit.status === 'pending'">
                                <Button size="sm" variant="outline" @click="ask(deposit, 'approve')">
                                    Approve
                                </Button>
                                <Button size="sm" variant="ghost" @click="ask(deposit, 'reject')">
                                    Reject
                                </Button>
                            </template>
                        </PermissionGate>
                        <PermissionGate permission="deposits.confirm">
                            <Button
                                v-if="deposit.status === 'approved'"
                                size="sm"
                                @click="ask(deposit, 'confirm')"
                            >
                                Confirm
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
            :variant="review?.action === 'reject' ? 'destructive' : 'primary'"
            :require-reason="review?.action === 'reject'"
            confirm-label="Confirm"
            :loading="pending"
            @update:open="(open) => { if (!open) review = null; }"
            @confirm="confirmAction"
        />
    </div>
</template>
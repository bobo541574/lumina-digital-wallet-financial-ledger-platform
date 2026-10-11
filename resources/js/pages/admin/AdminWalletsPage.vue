<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import type { Wallet } from '@/types/api';
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

const { items, loading, errorMessage, reload } = useAdminList<Wallet>(() =>
    adminService.wallets(),
);
const { pending, run } = useAsyncAction();

const statusFilter = ref('');
const filtered = computed(() =>
    statusFilter.value ? items.value.filter((w) => w.status === statusFilter.value) : items.value,
);
const hasActiveFilters = computed(() => statusFilter.value !== '');

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'frozen', label: 'Frozen' },
    { value: 'closed', label: 'Closed' },
];

type FreezeAction = 'freeze' | 'unfreeze';

const review = ref<{ wallet: Wallet; action: FreezeAction } | null>(null);

function ask(wallet: Wallet, action: FreezeAction): void {
    review.value = { wallet, action };
}

const dialogTitle = computed(() => {
    if (!review.value) {
        return '';
    }

    return review.value.action === 'freeze'
        ? `Freeze wallet ${review.value.wallet.id.slice(0, 8)}`
        : `Unfreeze wallet ${review.value.wallet.id.slice(0, 8)}`;
});

const dialogDescription = computed(() =>
    review.value?.action === 'freeze'
        ? 'Permanently freeze this wallet. The available balance is locked and no new activity is allowed.'
        : 'Release the freeze. The available balance is unlocked and normal activity resumes.',
);

async function confirmAction(): Promise<void> {
    const target = review.value;

    if (!target) {
        return;
    }

    const succeeded = await run(
        () =>
            target.action === 'freeze'
                ? adminService.freezeWallet(target.wallet.id)
                : adminService.unfreezeWallet(target.wallet.id),
        {
            success:
                target.action === 'freeze' ? 'Wallet frozen.' : 'Wallet unfrozen.',
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
            title="Wallets"
            description="Inspect balances and freeze flagged wallets to lock available funds."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <FilterBar class="mb-4" :has-active-filters="hasActiveFilters" @clear="statusFilter = ''">
            <div class="w-48">
                <Select
                    id="wallet-status"
                    v-model="statusFilter"
                    :options="statusOptions"
                    placeholder="All statuses"
                    aria-label="Filter by status"
                />
            </div>
        </FilterBar>

        <DataTable
            :columns="[
                { key: 'wallet', label: 'Wallet' },
                { key: 'user', label: 'User' },
                { key: 'status', label: 'Status' },
                { key: 'available', label: 'Available', align: 'right' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="loading"
            :empty="filtered.length === 0"
            empty-text="No wallets match these filters."
        >
            <tr v-for="wallet in filtered" :key="wallet.id" class="border-t border-border">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ wallet.name ?? wallet.currency }}</div>
                    <div class="font-mono text-xs text-muted-foreground">{{ wallet.id }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ wallet.user_id }}
                </td>
                <td class="px-4 py-3"><StatusBadge :status="wallet.status" /></td>
                <td class="px-4 py-3 text-right">
                    <MoneyText
                        :amount="wallet.balance?.available_balance ?? 0"
                        :currency="wallet.currency"
                    />
                </td>
                <td class="px-4 py-3 text-right">
                    <PermissionGate permission="wallets.manage">
                        <Button
                            v-if="wallet.status === 'active'"
                            size="sm"
                            variant="outline"
                            @click="ask(wallet, 'freeze')"
                        >
                            Freeze
                        </Button>
                        <Button
                            v-if="wallet.status === 'frozen'"
                            size="sm"
                            @click="ask(wallet, 'unfreeze')"
                        >
                            Unfreeze
                        </Button>
                    </PermissionGate>
                </td>
            </tr>
        </DataTable>

        <ConfirmDialog
            :open="review !== null"
            :title="dialogTitle"
            :description="dialogDescription"
            :variant="review?.action === 'freeze' ? 'destructive' : 'primary'"
            confirm-label="Confirm"
            :loading="pending"
            @update:open="(open) => { if (!open) review = null; }"
            @confirm="confirmAction"
        />
    </div>
</template>
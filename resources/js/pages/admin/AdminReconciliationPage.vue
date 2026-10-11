<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import { apiErrorMessage } from '@/lib/errors';
import type { ReconciliationItem, ReconciliationRun } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PermissionGate from '@/components/ui/PermissionGate.vue';
import Select from '@/components/ui/Select.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ReconciliationMismatchCard from '@/components/admin/ReconciliationMismatchCard.vue';
import DataTable from '@/components/ui/DataTable.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const { items: runs, loading, errorMessage, reload } = useAdminList<ReconciliationRun>(() =>
    adminService.reconciliationRuns(),
);
const { pending: runPending, run: startRun } = useAsyncAction();
const { pending: resolvePending, run: runResolve } = useAsyncAction();

const layerOptions = [
    { value: 'wallet', label: 'Wallet balance' },
    { value: 'provider', label: 'Provider transactions' },
    { value: 'settlement', label: 'Bank settlement' },
];

const runDialogOpen = ref(false);
const runLayer = ref('wallet');

async function confirmRun(): Promise<void> {
    const created = await startRun(
        () => adminService.runReconciliation({ layer: runLayer.value }),
        { success: 'Reconciliation run started.' },
    );

    if (created !== null) {
        runDialogOpen.value = false;
        await reload();
    }
}

const selectedRun = ref<ReconciliationRun | null>(null);
const items = ref<ReconciliationItem[]>([]);
const itemsLoading = ref(false);
const itemsError = ref<string | null>(null);

async function selectRun(selected: ReconciliationRun): Promise<void> {
    selectedRun.value = selected;
    itemsLoading.value = true;
    itemsError.value = null;

    try {
        items.value = await adminService.reconciliationItems({ run_id: selected.id });
    } catch (error) {
        itemsError.value = apiErrorMessage(error);
    } finally {
        itemsLoading.value = false;
    }
}

const openItems = computed(() => items.value.filter((item) => item.status === 'open'));

type ResolveStatus = 'resolved' | 'ignored';

const resolveTarget = ref<{ item: ReconciliationItem; status: ResolveStatus } | null>(null);

const resolveTitle = computed(() =>
    resolveTarget.value
        ? `${resolveTarget.value.status === 'resolved' ? 'Resolve' : 'Ignore'} mismatch`
        : '',
);

async function confirmResolve(note: string): Promise<void> {
    const target = resolveTarget.value;

    if (!target) {
        return;
    }

    const resolved = await runResolve(
        () =>
            adminService.resolveReconciliationItem(target.item.id, {
                status: target.status,
                note,
            }),
        { success: 'Mismatch resolved.' },
    );

    if (resolved !== null) {
        resolveTarget.value = null;
        await selectRun(selectedRun.value!);
        await reload();
    }
}
</script>

<template>
    <div>
        <PageHeader title="Reconciliation" description="Ledger, provider and settlement checks.">
            <template #actions>
                <PermissionGate permission="reconciliation.run">
                    <Button @click="runDialogOpen = true">Run reconciliation</Button>
                </PermissionGate>
            </template>
        </PageHeader>

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <DataTable
            :columns="[
                { key: 'run', label: 'Run' },
                { key: 'layer', label: 'Layer' },
                { key: 'status', label: 'Status' },
                { key: 'items', label: 'Items', align: 'right' },
                { key: 'discrepancies', label: 'Discrepancies', align: 'right' },
                { key: 'actions', label: '', align: 'right' },
            ]"
            :loading="loading"
            :empty="runs.length === 0"
            empty-text="No reconciliation runs yet."
        >
            <tr v-for="runRecord in runs" :key="runRecord.id" class="border-t border-border">
                <td class="px-4 py-3">
                    <div class="font-mono text-xs">{{ runRecord.id.slice(0, 8) }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ runRecord.started_at?.slice(0, 19) ?? '—' }}
                    </div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ runRecord.layer }}</td>
                <td class="px-4 py-3"><StatusBadge :status="runRecord.status" /></td>
                <td class="px-4 py-3 text-right">{{ runRecord.items_count }}</td>
                <td
                    class="px-4 py-3 text-right font-medium"
                    :class="runRecord.discrepancies_count > 0 ? 'text-destructive' : ''"
                >
                    {{ runRecord.discrepancies_count }}
                </td>
                <td class="px-4 py-3 text-right">
                    <Button
                        size="sm"
                        variant="outline"
                        @click="selectRun(runRecord)"
                    >
                        Review
                    </Button>
                </td>
            </tr>
        </DataTable>

        <section v-if="selectedRun" class="mt-10">
            <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Run {{ selectedRun.id.slice(0, 8) }}</h2>
                    <p class="text-sm text-muted-foreground">
                        Layer {{ selectedRun.layer }} · {{ items.length }} item(s),
                        {{ openItems.length }} still open
                    </p>
                </div>
                <Button size="sm" variant="ghost" @click="selectedRun = null">Close</Button>
            </header>

            <Alert v-if="itemsError" variant="error" class="mb-6">{{ itemsError }}</Alert>
            <Spinner v-if="itemsLoading" />

            <EmptyState
                v-else-if="items.length === 0"
                title="No items"
                description="This run produced no mismatches."
            />

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <ReconciliationMismatchCard
                    v-for="item in items"
                    :key="item.id"
                    :item="item"
                >
                    <template
                        v-if="item.status === 'open'"
                        #actions
                    >
                        <PermissionGate permission="reconciliation.run">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="resolveTarget = { item, status: 'ignored' }"
                            >
                                Ignore
                            </Button>
                            <Button
                                size="sm"
                                @click="resolveTarget = { item, status: 'resolved' }"
                            >
                                Resolve
                            </Button>
                        </PermissionGate>
                    </template>
                </ReconciliationMismatchCard>
            </div>
        </section>

        <ConfirmDialog
            :open="runDialogOpen"
            title="Run reconciliation"
            description="Compare balances against ledger or provider records. Mismatches require authorised resolution."
            confirm-label="Run"
            :loading="runPending"
            @update:open="(open) => { if (!open) runDialogOpen = false; }"
            @confirm="confirmRun"
        >
            <div>
                <label :for="'rec-layer'" class="mb-1.5 block text-sm font-medium">Layer</label>
                <Select id="rec-layer" v-model="runLayer" :options="layerOptions" />
            </div>
        </ConfirmDialog>

        <ConfirmDialog
            :open="resolveTarget !== null"
            :title="resolveTitle"
            :variant="resolveTarget?.status === 'ignored' ? 'outline' : 'primary'"
            :require-reason="true"
            reason-label="Resolution note"
            confirm-label="Confirm"
            :loading="resolvePending"
            @update:open="(open) => { if (!open) resolveTarget = null; }"
            @confirm="confirmResolve"
        />
    </div>
</template>
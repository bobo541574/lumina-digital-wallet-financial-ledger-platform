<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import type { JournalEntry } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import PermissionGate from '@/components/ui/PermissionGate.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import DataTable from '@/components/ui/DataTable.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const { items, loading, errorMessage, reload } = useAdminList<JournalEntry>(() =>
    adminService.journalEntries(),
);
const { pending, run } = useAsyncAction();

function debitTotal(entry: JournalEntry): number {
    return entry.postings
        .filter((posting) => posting.direction === 'debit')
        .reduce((sum, posting) => sum + posting.amount, 0);
}

const canReverse = (entry: JournalEntry): boolean => entry.entry_type !== 'reversal';

const reversing = ref<JournalEntry | null>(null);

const reverseTitle = computed(() =>
    reversing.value ? `Reverse journal ${reversing.value.id.slice(0, 8)}` : '',
);

async function confirmReverse(reason: string): Promise<void> {
    if (!reversing.value) {
        return;
    }

    const succeeded = await run(
        () => adminService.reverseJournalEntry(reversing.value!.id, { reason }),
        { success: 'Journal entry reversed.' },
    );

    if (succeeded !== null) {
        reversing.value = null;
        await reload();
    }
}
</script>

<template>
    <div>
        <PageHeader
            title="Journal entries"
            description="The immutable double-entry ledger. Corrections are compensating entries."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <DataTable
            :columns="[
                { key: 'entry', label: 'Entry' },
                { key: 'source', label: 'Source' },
                { key: 'type', label: 'Type' },
                { key: 'status', label: 'Status' },
                { key: 'postings', label: 'Postings', align: 'right' },
                { key: 'total', label: 'Total', align: 'right' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="loading"
            :empty="items.length === 0"
            empty-text="No journal entries found."
        >
            <tr v-for="entry in items" :key="entry.id" class="border-t border-border">
                <td class="px-4 py-3">
                    <div class="font-mono text-xs">{{ entry.id.slice(0, 8) }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ entry.occurred_at?.slice(0, 19) ?? '—' }}
                    </div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ entry.source_type ?? '—' }}</td>
                <td class="px-4 py-3">{{ entry.entry_type }}</td>
                <td class="px-4 py-3"><StatusBadge :status="entry.status" /></td>
                <td class="px-4 py-3 text-right">{{ entry.postings.length }}</td>
                <td class="px-4 py-3 text-right">
                    <MoneyText :amount="debitTotal(entry)" :currency="entry.currency" />
                </td>
                <td class="px-4 py-3 text-right">
                    <PermissionGate permission="ledger.correct">
                        <Button
                            v-if="canReverse(entry)"
                            size="sm"
                            variant="outline"
                            @click="reversing = entry"
                        >
                            Reverse
                        </Button>
                    </PermissionGate>
                </td>
            </tr>
        </DataTable>

        <ConfirmDialog
            :open="reversing !== null"
            :title="reverseTitle"
            description="This posts a compensating reversal entry. The original entry stays immutable."
            variant="destructive"
            :require-reason="true"
            reason-label="Reverse reason"
            confirm-label="Reverse entry"
            :loading="pending"
            @update:open="(open) => { if (!open) reversing = null; }"
            @confirm="confirmReverse"
        />
    </div>
</template>
<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { walletsService } from '@/services/wallets';
import { apiErrorMessage } from '@/lib/errors';
import type { Wallet, WalletTransaction } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const wallets = ref<Wallet[]>([]);
const walletId = ref('');
const transactions = ref<WalletTransaction[]>([]);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

async function loadWallets(): Promise<void> {
    try {
        wallets.value = await walletsService.list();
        walletId.value = wallets.value[0]?.id ?? '';
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    }
}

async function loadTransactions(): Promise<void> {
    if (!walletId.value) {
        transactions.value = [];
        return;
    }

    loading.value = true;
    errorMessage.value = null;

    try {
        transactions.value = await walletsService.transactions(walletId.value);
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

watch(walletId, loadTransactions);

onMounted(async () => {
    await loadWallets();
    await loadTransactions();
});
</script>

<template>
    <div>
        <PageHeader title="Activity" description="Every movement across your wallet." />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <div class="mb-6 max-w-xs">
            <label for="wallet" class="mb-1.5 block text-sm font-medium">Wallet</label>
            <select
                id="wallet"
                v-model="walletId"
                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            >
                <option v-for="wallet in wallets" :key="wallet.id" :value="wallet.id">
                    {{ wallet.name ?? wallet.currency }} ({{ wallet.currency }})
                </option>
            </select>
        </div>

        <Spinner v-if="loading" />

        <EmptyState
            v-else-if="transactions.length === 0"
            title="No activity"
            description="This wallet has no movements yet."
        />

        <div v-else class="overflow-hidden rounded-xl border border-border">
            <table class="w-full text-sm">
                <thead class="bg-muted text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in transactions"
                        :key="entry.id"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ entry.occurred_at?.slice(0, 10) ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ entry.description ?? entry.source_type ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ entry.entry_type ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            <MoneyText
                                :amount="entry.signed_amount"
                                :currency="entry.currency"
                                coloured
                                signed
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

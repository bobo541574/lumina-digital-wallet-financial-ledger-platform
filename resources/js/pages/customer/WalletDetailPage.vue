<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { download } from '@/services/api/client';
import { walletsService } from '@/services/wallets';
import { apiErrorMessage } from '@/lib/errors';
import type { Wallet, WalletBalance, WalletTransaction } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const route = useRoute();
const walletId = computed(() => String(route.params.id));

const wallet = ref<Wallet | null>(null);
const balance = ref<WalletBalance | null>(null);
const transactions = ref<WalletTransaction[]>([]);
const loading = ref(true);
const errorMessage = ref<string | null>(null);
const exporting = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    try {
        const [wallets, walletBalance, history] = await Promise.all([
            walletsService.list(),
            walletsService.balance(walletId.value),
            walletsService.transactions(walletId.value),
        ]);

        wallet.value = wallets.find((w) => w.id === walletId.value) ?? null;
        balance.value = walletBalance;
        transactions.value = history;
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function exportStatement(): Promise<void> {
    if (!wallet.value) {
        return;
    }

    exporting.value = true;
    errorMessage.value = null;

    try {
        const { blob, filename } = await download(`/wallets/${walletId.value}/statement/export`);
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename ?? `statement-${walletId.value}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        exporting.value = false;
    }
}
onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="wallet?.name ?? wallet?.currency ?? 'Wallet'">
            <template #actions>
                <Button variant="outline" :loading="exporting" @click="exportStatement">
                    Export CSV
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <Spinner v-if="loading" />

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-3">
                <Card title="Available">
                    <p class="text-2xl font-semibold">
                        <MoneyText
                            :amount="balance?.available_balance ?? 0"
                            :currency="balance?.currency ?? 'USD'"
                        />
                    </p>
                </Card>
                <Card title="Posted">
                    <p class="text-2xl font-semibold">
                        <MoneyText
                            :amount="balance?.posted_balance ?? 0"
                            :currency="balance?.currency ?? 'USD'"
                        />
                    </p>
                </Card>
                <Card title="Reserved">
                    <p class="text-2xl font-semibold">
                        <MoneyText
                            :amount="balance?.reserved_balance ?? 0"
                            :currency="balance?.currency ?? 'USD'"
                        />
                    </p>
                </Card>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <StatusBadge v-if="wallet" :status="wallet.status" />
                <span class="text-sm text-muted-foreground">Wallet ID: {{ walletId }}</span>
            </div>

            <h2 class="mb-3 mt-8 text-lg font-semibold">Recent activity</h2>

            <div class="overflow-hidden rounded-xl border border-border">
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
                        <tr v-if="transactions.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                No activity yet.
                            </td>
                        </tr>
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

            <p class="mt-3 text-xs text-muted-foreground">
                Statement export covers the last 30 days by default.
            </p>
        </template>
    </div>
</template>

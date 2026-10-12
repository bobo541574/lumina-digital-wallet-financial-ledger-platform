<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { walletsService } from '@/services/wallets';
import { withdrawalsService } from '@/services/withdrawals';
import { apiErrorMessage } from '@/lib/errors';
import { parseAmountToMinor } from '@/lib/format/money';
import { newIdempotencyKey } from '@/lib/idempotency';
import type { Wallet, Withdrawal } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import RetryPanel from '@/components/ui/RetryPanel.vue';
import Select from '@/components/ui/Select.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const wallets = ref<Wallet[]>([]);
const withdrawals = ref<Withdrawal[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const walletId = ref('');
const amount = ref('');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const result = ref<Withdrawal | null>(null);

const wallet = computed(() => wallets.value.find((w) => w.id === walletId.value) ?? null);
const currency = computed(() => wallet.value?.currency ?? 'USD');

async function fetchWithdrawals(): Promise<void> {
    withdrawals.value = await withdrawalsService.list();
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [walletList, withdrawalList] = await Promise.all([
            walletsService.list(),
            withdrawalsService.list(),
        ]);
        wallets.value = walletList;
        withdrawals.value = withdrawalList;
        walletId.value = walletList[0]?.id ?? '';
    } catch (error) {
        loadError.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function submit(): Promise<void> {
    submitting.value = true;
    errorMessage.value = null;
    result.value = null;

    let minorAmount: number;

    try {
        minorAmount = parseAmountToMinor(amount.value, currency.value);
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
        submitting.value = false;
        return;
    }

    const idempotencyKey = newIdempotencyKey();

    try {
        const created = await withdrawalsService.create(
            { wallet_id: walletId.value, amount: minorAmount, provider: 'fake' },
            idempotencyKey,
        );

        result.value = created;
        amount.value = '';

        // Refresh the history after a successful request; failure here is
        // best-effort and must not turn a success into an error.
        try {
            await fetchWithdrawals();
        } catch {
            // Keep the success state; the next page visit refreshes.
        }
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}

const walletOptions = computed(() =>
    wallets.value.map((w) => ({
        value: w.id,
        label: `${w.name ?? w.currency} (${w.currency})`,
    })),
);

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Withdraw" description="Move money out to a payout destination." />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <Alert v-if="result" variant="success" class="mb-6" title="Withdrawal requested">
            <div class="flex items-center gap-2">
                <span>Reference {{ result.reference }}</span>
                <StatusBadge :status="result.status" />
            </div>
            <p class="mt-1 text-sm">Funds are reserved until the payout settles.</p>
        </Alert>

        <Spinner v-if="loading" />

        <RetryPanel v-else-if="loadError" :message="loadError" :retrying="loading" @retry="load" />

        <template v-else>
            <Card title="Withdraw funds" class="max-w-xl">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="wallet" required>From wallet</Label>
                        <Select
                            id="wallet"
                            v-model="walletId"
                            :options="walletOptions"
                            placeholder="Select a wallet"
                            required
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="amount" required>Amount ({{ currency }})</Label>
                        <Input
                            id="amount"
                            v-model="amount"
                            inputmode="decimal"
                            placeholder="0.00"
                            required
                        />
                    </div>

                    <Button type="submit" :loading="submitting" :disabled="walletId === ''">
                        Request withdrawal
                    </Button>
                </form>
            </Card>

            <h2 class="mb-3 mt-8 text-lg font-semibold">Your withdrawals</h2>

            <div class="overflow-x-auto rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="withdrawals.length === 0">
                            <td colspan="3" class="px-4 py-6 text-center text-muted-foreground">
                                No withdrawals yet.
                            </td>
                        </tr>
                        <tr v-for="row in withdrawals" :key="row.id" class="border-t border-border">
                            <td class="px-4 py-3 font-mono text-xs">{{ row.reference }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="row.status" />
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                <MoneyText :amount="row.amount" :currency="row.currency" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

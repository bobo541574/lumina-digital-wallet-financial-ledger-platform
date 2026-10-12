<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { walletsService } from '@/services/wallets';
import { depositsService } from '@/services/deposits';
import { apiErrorMessage } from '@/lib/errors';
import { parseAmountToMinor } from '@/lib/format/money';
import { newIdempotencyKey } from '@/lib/idempotency';
import type { Deposit, Wallet } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import RetryPanel from '@/components/ui/RetryPanel.vue';
import Select from '@/components/ui/Select.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const wallets = ref<Wallet[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const walletId = ref('');
const amount = ref('');
const channel = ref<'manual' | 'provider'>('manual');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const result = ref<Deposit | null>(null);

const wallet = computed(() => wallets.value.find((w) => w.id === walletId.value) ?? null);
const currency = computed(() => wallet.value?.currency ?? 'USD');

async function fetchWallets(): Promise<void> {
    wallets.value = await walletsService.list();
    walletId.value = wallets.value[0]?.id ?? '';
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        await fetchWallets();
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
        result.value =
            channel.value === 'manual'
                ? await depositsService.createManual(
                      { wallet_id: walletId.value, amount: minorAmount },
                      idempotencyKey,
                  )
                : await depositsService.createProvider(
                      { wallet_id: walletId.value, amount: minorAmount, provider: 'fake' },
                      idempotencyKey,
                  );

        amount.value = '';
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
        <PageHeader title="Add money" description="Top up a wallet balance." />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <Alert v-if="result" variant="success" class="mb-6" title="Deposit created">
            <div class="flex items-center gap-2">
                <span>Reference {{ result.reference }}</span>
                <StatusBadge :status="result.status" />
            </div>
            <p v-if="result.payment_url" class="mt-2">
                Complete payment:
                <a :href="result.payment_url" class="font-medium text-primary hover:underline">
                    {{ result.payment_url }}
                </a>
            </p>
        </Alert>

        <Spinner v-if="loading" />

        <RetryPanel v-else-if="loadError" :message="loadError" :retrying="loading" @retry="load" />

        <Card v-else title="Deposit details" class="max-w-xl">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="wallet" required>To wallet</Label>
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

                <div class="space-y-1.5">
                    <Label for="channel" required>Method</Label>
                    <select
                        id="channel"
                        v-model="channel"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="manual">Manual (bank transfer, pending approval)</option>
                        <option value="provider">Instant (payment provider)</option>
                    </select>
                </div>

                <Button type="submit" :loading="submitting" :disabled="walletId === ''"
                    >Create deposit</Button
                >
            </form>
        </Card>

        <p class="mt-4 max-w-xl text-xs text-muted-foreground">
            Manual deposits require administrator approval before funds become available.
        </p>
    </div>
</template>

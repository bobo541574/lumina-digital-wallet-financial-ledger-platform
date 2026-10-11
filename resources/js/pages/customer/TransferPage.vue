<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { walletsService } from '@/services/wallets';
import { transfersService } from '@/services/transfers';
import { apiErrorMessage } from '@/lib/errors';
import { formatMoney, parseAmountToMinor } from '@/lib/format/money';
import { newIdempotencyKey } from '@/lib/idempotency';
import type { Transfer, Wallet } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import Select from '@/components/ui/Select.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const wallets = ref<Wallet[]>([]);
const senderWalletId = ref('');
const recipientWalletId = ref('');
const amount = ref('');
const description = ref('');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const completed = ref<Transfer | null>(null);

const sender = computed(() => wallets.value.find((w) => w.id === senderWalletId.value) ?? null);
const currency = computed(() => sender.value?.currency ?? 'USD');

function walletLabel(wallet: Wallet): string {
    const name = wallet.name ?? wallet.currency;
    const available = formatMoney(wallet.balance?.available_balance ?? 0, wallet.currency);
    return `${name} — ${available}`;
}

async function load(): Promise<void> {
    try {
        wallets.value = await walletsService.list();
        senderWalletId.value = wallets.value[0]?.id ?? '';
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    }
}

async function submit(): Promise<void> {
    submitting.value = true;
    errorMessage.value = null;
    completed.value = null;

    let minorAmount: number;

    try {
        minorAmount = parseAmountToMinor(amount.value, currency.value);
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
        submitting.value = false;
        return;
    }

    // Reuse the same key across retries of this logical transfer.
    const idempotencyKey = newIdempotencyKey();

    try {
        completed.value = await transfersService.create(
            {
                sender_wallet_id: senderWalletId.value,
                recipient_wallet_id: recipientWalletId.value,
                currency: currency.value,
                amount: minorAmount,
                description: description.value || null,
            },
            idempotencyKey,
        );

        amount.value = '';
        description.value = '';
        await load();
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}

const walletOptions = computed(() =>
    wallets.value.map((w) => ({
        value: w.id,
        label: walletLabel(w),
    })),
);

const canSubmit = computed(
    () =>
        senderWalletId.value !== '' &&
        recipientWalletId.value !== '' &&
        amount.value !== '' &&
        !submitting.value,
);

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Send money" description="Transfer between wallets within Lumina." />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>
        <Alert v-else-if="completed" variant="success" class="mb-6" title="Transfer complete">
            Reference {{ completed.reference }} —
            <MoneyText :amount="completed.amount" :currency="completed.currency" /> sent.
        </Alert>

        <Card title="New transfer" class="max-w-xl">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="sender" required>From wallet</Label>
                    <Select
                        id="sender"
                        v-model="senderWalletId"
                        :options="walletOptions"
                        placeholder="Select a wallet"
                        required
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="recipient" required>Recipient wallet ID</Label>
                    <Input
                        id="recipient"
                        v-model="recipientWalletId"
                        placeholder="Recipient wallet UUID"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Ask the recipient for their wallet ID to send them money.
                    </p>
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
                    <Label for="description">Note (optional)</Label>
                    <Input id="description" v-model="description" maxlength="255" />
                </div>

                <Button type="submit" :loading="submitting" :disabled="!canSubmit">Send</Button>
            </form>
        </Card>
    </div>
</template>

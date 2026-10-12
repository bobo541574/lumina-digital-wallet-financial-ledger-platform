<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { walletsService } from '@/services/wallets';
import { apiErrorMessage } from '@/lib/errors';
import { useToast } from '@/composables/useToast';
import type { Wallet } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import RetryPanel from '@/components/ui/RetryPanel.vue';
import Select from '@/components/ui/Select.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const toast = useToast();
const wallets = ref<Wallet[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const submitting = ref(false);
const showForm = ref(false);

const currency = ref('USD');
const name = ref('');

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        wallets.value = await walletsService.list();
    } catch (error) {
        loadError.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

const currencyOptions = computed(() => [
    { value: 'USD', label: 'USD' },
    { value: 'MMK', label: 'MMK' },
]);

async function openWallet(): Promise<void> {
    submitting.value = true;
    errorMessage.value = null;

    try {
        await walletsService.create({ currency: currency.value, name: name.value || null });
        name.value = '';
        showForm.value = false;
        toast.success('Wallet opened.');
        await load();
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Wallets" description="Open wallets and manage your balances.">
            <template #actions>
                <Button variant="outline" @click="showForm = !showForm">
                    {{ showForm ? 'Cancel' : 'Open wallet' }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <template v-if="!loadError">
            <Card v-if="showForm" title="Open a wallet" class="mb-6">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="openWallet">
                    <div class="space-y-1.5">
                        <Label for="currency" required>Currency</Label>
                        <Select
                            id="currency"
                            v-model="currency"
                            :options="currencyOptions"
                            class="w-32"
                        />
                    </div>
                    <div class="min-w-48 flex-1 space-y-1.5">
                        <Label for="name">Name (optional)</Label>
                        <Input id="name" v-model="name" placeholder="e.g. Savings" />
                    </div>
                    <Button type="submit" :loading="submitting">Create</Button>
                </form>
            </Card>

            <Spinner v-if="loading" />

            <EmptyState
                v-else-if="wallets.length === 0"
                title="No wallets yet"
                description="Open a wallet to get started."
            />

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <RouterLink
                    v-for="wallet in wallets"
                    :key="wallet.id"
                    :to="{ name: 'wallet-detail', params: { id: wallet.id } }"
                    class="rounded-xl border border-border bg-card px-5 py-4 transition hover:border-primary/50"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ wallet.name ?? wallet.currency }}</span>
                        <StatusBadge :status="wallet.status" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">
                        <MoneyText
                            :amount="wallet.balance?.available_balance ?? 0"
                            :currency="wallet.currency"
                        />
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Available balance</p>
                </RouterLink>
            </div>
        </template>

        <RetryPanel v-else :message="loadError" :retrying="loading" @retry="load" />
    </div>
</template>

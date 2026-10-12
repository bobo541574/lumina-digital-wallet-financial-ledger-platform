<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { walletsService } from '@/services/wallets';
import { apiErrorMessage } from '@/lib/errors';
import type { Wallet } from '@/types/api';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MoneyText from '@/components/ui/MoneyText.vue';
import RetryPanel from '@/components/ui/RetryPanel.vue';
import Spinner from '@/components/ui/Spinner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const wallets = ref<Wallet[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

const totalsByCurrency = computed(() => {
    const totals = new Map<string, number>();

    for (const wallet of wallets.value) {
        const current = totals.get(wallet.currency) ?? 0;
        totals.set(wallet.currency, current + (wallet.balance?.available_balance ?? 0));
    }

    return [...totals.entries()].map(([currency, amount]) => ({ currency, amount }));
});

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

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Dashboard" description="Your balances at a glance." />

        <RetryPanel v-if="loadError" :message="loadError" :retrying="loading" @retry="load" />

        <template v-else>
            <Spinner v-if="loading" />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="total in totalsByCurrency"
                    :key="total.currency"
                    :title="`${total.currency} available`"
                >
                    <p class="text-2xl font-semibold">
                        <MoneyText :amount="total.amount" :currency="total.currency" />
                    </p>
                </Card>
            </div>

            <div v-if="wallets.length === 0" class="mt-6">
                <EmptyState
                    title="No wallets yet"
                    description="Open your first wallet to start sending and receiving money."
                >
                    <RouterLink :to="{ name: 'wallets' }">
                        <Button>Open a wallet</Button>
                    </RouterLink>
                </EmptyState>
            </div>

            <div v-else class="mt-8">
                <h2 class="mb-3 text-lg font-semibold">Wallets</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
            </div>
        </template>
    </div>
</template>

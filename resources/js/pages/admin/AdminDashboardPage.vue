<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { adminService } from '@/services/admin';
import { apiErrorMessage } from '@/lib/errors';
import Alert from '@/components/ui/Alert.vue';
import Card from '@/components/ui/Card.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

interface LinkCard {
    name: string;
    label: string;
    count: number | null;
}

const cards = ref<LinkCard[]>([
    { name: 'admin-deposits', label: 'Deposits', count: null },
    { name: 'admin-withdrawals', label: 'Withdrawals', count: null },
    { name: 'admin-transfers', label: 'Transfers', count: null },
    { name: 'admin-wallets', label: 'Wallets', count: null },
]);
const loading = ref(true);
const errorMessage = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    try {
        const [deposits, withdrawals, transfers, wallets] = await Promise.all([
            adminService.deposits(),
            adminService.withdrawals(),
            adminService.transfers(),
            adminService.wallets(),
        ]);

        const counts = [deposits.length, withdrawals.length, transfers.length, wallets.length];
        cards.value.forEach((card, index) => {
            card.count = counts[index] ?? 0;
        });
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            title="Admin overview"
            description="Operational snapshot across the platform."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <Spinner v-if="loading" />

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <RouterLink v-for="card in cards" :key="card.name" :to="{ name: card.name }">
                <Card :title="card.label">
                    <p class="text-3xl font-semibold">{{ card.count ?? '—' }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Most recent 25 records</p>
                </Card>
            </RouterLink>
        </div>
    </div>
</template>

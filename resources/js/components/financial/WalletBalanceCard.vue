<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import MoneyText from '@/components/ui/MoneyText.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import type { Wallet } from '@/types/api';

const props = withDefaults(defineProps<{ wallet: Wallet; link?: boolean }>(), { link: false });

const to = computed(() => ({ name: 'wallet-detail', params: { id: props.wallet.id } }));
const tag = computed(() => (props.link ? RouterLink : 'div'));
const linkProps = computed(() => (props.link ? { to: to.value } : {}));
</script>

<template>
    <component
        :is="tag"
        v-bind="linkProps"
        class="block rounded-xl border border-border bg-card px-5 py-4 text-card-foreground shadow-sm transition hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground">
            <div>
                <dt class="inline">Posted</dt>
                <dd class="inline">
                    <MoneyText
                        :amount="wallet.balance?.posted_balance ?? 0"
                        :currency="wallet.currency"
                    />
                </dd>
            </div>
            <div>
                <dt class="inline">Reserved</dt>
                <dd class="inline">
                    <MoneyText
                        :amount="wallet.balance?.reserved_balance ?? 0"
                        :currency="wallet.currency"
                    />
                </dd>
            </div>
        </dl>
    </component>
</template>

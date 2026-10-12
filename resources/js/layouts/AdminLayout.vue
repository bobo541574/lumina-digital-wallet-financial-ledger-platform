<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/Button.vue';
import ThemeToggle from '@/components/layout/ThemeToggle.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const navItems = computed(() =>
    [
        { name: 'admin-dashboard', label: 'Overview' },
        { name: 'admin-wallets', label: 'Wallets', permission: 'wallets.manage' },
        { name: 'admin-deposits', label: 'Deposits', permission: 'deposits.approve' },
        { name: 'admin-withdrawals', label: 'Withdrawals', permission: 'withdrawals.approve' },
        { name: 'admin-transfers', label: 'Transfers', permission: 'ledger.view' },
        { name: 'admin-journal-entries', label: 'Journal entries', permission: 'ledger.view' },
        {
            name: 'admin-reconciliation',
            label: 'Reconciliation',
            permission: 'reconciliation.view',
        },
        { name: 'admin-audit-logs', label: 'Audit log', permission: 'audit.view' },
        { name: 'admin-users', label: 'Users & access', permission: 'users.manage' },
        { name: 'admin-roles', label: 'Roles & permissions', permission: 'roles.manage' },
    ].filter((item) => !item.permission || auth.hasPermission(item.permission)),
);

async function signOut(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="grid min-h-screen grid-cols-1 md:grid-cols-[16rem_1fr]">
        <aside class="border-b border-border bg-card md:border-b-0 md:border-r">
            <div class="flex h-16 items-center px-6 text-lg font-semibold">Lumina Admin</div>
            <nav class="flex flex-col gap-1 px-3 pb-4">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.name"
                    :to="{ name: item.name }"
                    :aria-current="route.name === item.name ? 'page' : undefined"
                    class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    active-class="bg-accent text-accent-foreground"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>
            <div class="px-6 py-4">
                <RouterLink
                    :to="{ name: 'dashboard' }"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    Back to wallet
                </RouterLink>
            </div>
        </aside>

        <div class="flex flex-col">
            <header
                class="flex h-16 items-center justify-between border-b border-border bg-card px-6"
            >
                <span class="text-sm text-muted-foreground">{{ auth.user?.email }}</span>
                <div class="flex items-center gap-2">
                    <ThemeToggle />
                    <Button variant="outline" size="sm" @click="signOut">Sign out</Button>
                </div>
            </header>
            <main class="flex-1 px-6 py-8">
                <RouterView />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import Button from '@/components/ui/Button.vue';
import NotificationBell from '@/components/layout/NotificationBell.vue';
import ThemeToggle from '@/components/layout/ThemeToggle.vue';

const auth = useAuthStore();
const notifications = useNotificationsStore();
const router = useRouter();

const navItems = computed(() =>
    [
        { name: 'dashboard', label: 'Dashboard' },
        { name: 'wallets', label: 'Wallets' },
        { name: 'transfer', label: 'Send', permission: 'transfers.create' },
        { name: 'deposit', label: 'Add money', permission: 'deposits.create' },
        { name: 'withdraw', label: 'Withdraw', permission: 'withdrawals.create' },
        { name: 'history', label: 'Activity' },
    ].filter((item) => !item.permission || auth.hasPermission(item.permission)),
);

onMounted(() => {
    notifications.startPolling();

    if (auth.user) {
        notifications.startRealtime(auth.user.id);
    }
});
onUnmounted(() => {
    notifications.stopPolling();
    notifications.stopRealtime();
});

async function signOut(): Promise<void> {
    notifications.stopPolling();
    notifications.stopRealtime();
    notifications.reset();
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="border-b border-border bg-card">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <div class="flex items-center gap-6">
                    <RouterLink :to="{ name: 'dashboard' }" class="text-lg font-semibold">
                        Lumina
                    </RouterLink>
                    <nav class="hidden items-center gap-1 md:flex">
                        <RouterLink
                            v-for="item in navItems"
                            :key="item.name"
                            :to="{ name: item.name }"
                            class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            active-class="bg-accent text-accent-foreground"
                        >
                            {{ item.label }}
                        </RouterLink>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <RouterLink
                        v-if="auth.isAdmin"
                        :to="{ name: 'admin-dashboard' }"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Admin
                    </RouterLink>
                    <span class="hidden text-sm text-muted-foreground sm:inline">{{
                        auth.user?.email
                    }}</span>
                    <NotificationBell />
                    <ThemeToggle />
                    <Button variant="outline" size="sm" @click="signOut">Sign out</Button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>

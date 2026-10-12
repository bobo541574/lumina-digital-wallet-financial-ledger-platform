<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import Button from '@/components/ui/Button.vue';
import NotificationBell from '@/components/layout/NotificationBell.vue';
import ThemeToggle from '@/components/layout/ThemeToggle.vue';

const auth = useAuthStore();
const notifications = useNotificationsStore();
const router = useRouter();
const route = useRoute();

const mobileOpen = ref(false);

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

// Close the mobile drawer whenever navigation happens.
watch(
    () => route.fullPath,
    () => {
        mobileOpen.value = false;
    },
);

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        mobileOpen.value = false;
    }
}

onMounted(() => {
    notifications.startPolling();

    if (auth.user) {
        notifications.startRealtime(auth.user.id);
    }

    document.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    notifications.stopPolling();
    notifications.stopRealtime();
    document.removeEventListener('keydown', onKeydown);
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
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4">
                <div class="flex items-center gap-3 sm:gap-6">
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none md:hidden"
                        aria-label="Toggle navigation menu"
                        aria-controls="customer-mobile-nav"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.75"
                            class="size-5"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                    </button>

                    <RouterLink :to="{ name: 'dashboard' }" class="text-lg font-semibold">
                        Lumina
                    </RouterLink>
                    <nav class="hidden items-center gap-1 md:flex">
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

            <nav
                v-if="mobileOpen"
                id="customer-mobile-nav"
                class="border-t border-border px-4 py-2 md:hidden"
            >
                <RouterLink
                    v-for="item in navItems"
                    :key="item.name"
                    :to="{ name: item.name }"
                    :aria-current="route.name === item.name ? 'page' : undefined"
                    class="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    active-class="bg-accent text-accent-foreground"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>

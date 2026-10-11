<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useNotificationsStore } from '@/stores/notifications';
import { formatRelativeTime } from '@/lib/format/date';

const notifications = useNotificationsStore();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const badge = computed(() =>
    notifications.unreadCount > 9 ? '9+' : String(notifications.unreadCount),
);

function toggle(): void {
    open.value = !open.value;

    if (open.value) {
        void notifications.refresh();
    }
}

function close(): void {
    open.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none"
            aria-label="Notifications"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
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
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                />
            </svg>
            <span
                v-if="notifications.unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-4 text-destructive-foreground"
            >
                {{ badge }}
            </span>
        </button>

        <div
            v-if="open"
            role="menu"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-border bg-card shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                <span class="text-sm font-semibold">Notifications</span>
                <button
                    v-if="notifications.unreadCount > 0"
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click="notifications.markAllRead()"
                >
                    Mark all read
                </button>
            </div>

            <div v-if="notifications.loading" class="px-4 py-6 text-center text-sm text-muted-foreground">
                Loading…
            </div>

            <div
                v-else-if="notifications.recent.length === 0"
                class="px-4 py-6 text-center text-sm text-muted-foreground"
            >
                You have no notifications yet.
            </div>

            <ul v-else class="max-h-80 divide-y divide-border overflow-y-auto">
                <li v-for="item in notifications.recent" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full gap-3 px-4 py-3 text-left hover:bg-accent"
                        @click="notifications.markRead(item.id)"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="item.read ? 'bg-transparent' : 'bg-primary'"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ item.title }}</span>
                            <span
                                v-if="item.body"
                                class="mt-0.5 block text-xs text-muted-foreground"
                                >{{ item.body }}</span
                            >
                            <span class="mt-1 block text-[11px] text-muted-foreground">{{
                                formatRelativeTime(item.created_at)
                            }}</span>
                        </span>
                    </button>
                </li>
            </ul>

            <div class="border-t border-border px-4 py-2.5 text-center">
                <RouterLink
                    :to="{ name: 'notifications' }"
                    class="text-xs text-primary hover:underline"
                    @click="close"
                >
                    View all notifications
                </RouterLink>
            </div>
        </div>
    </div>
</template>

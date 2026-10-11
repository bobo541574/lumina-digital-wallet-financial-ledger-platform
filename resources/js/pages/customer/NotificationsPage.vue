<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import { notificationsService } from '@/services/notifications';
import { apiErrorMessage } from '@/lib/errors';
import { formatRelativeTime } from '@/lib/format/date';
import type { UserNotification } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const store = useNotificationsStore();

type Filter = 'all' | 'unread';

const filter = ref<Filter>('all');
const items = ref<UserNotification[]>([]);
const loading = ref(true);
const errorMessage = ref<string | null>(null);
const nextCursor = ref<string | null>(null);
const loadingMore = ref(false);

async function load(reset = true): Promise<void> {
    if (reset) {
        loading.value = true;
        errorMessage.value = null;
    } else {
        loadingMore.value = true;
    }

    try {
        const page = await notificationsService.list({
            unread: filter.value === 'unread',
            per_page: 20,
            cursor: reset ? undefined : (nextCursor.value ?? undefined),
        });

        items.value = reset ? page.items : [...items.value, ...page.items];
        nextCursor.value = page.nextCursor;
        store.unreadCount = page.unreadCount;
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

async function setFilter(next: Filter): Promise<void> {
    if (filter.value === next) {
        return;
    }

    filter.value = next;
    await load(true);
}

async function markRead(item: UserNotification): Promise<void> {
    await store.markRead(item.id);
    item.read = true;
}

async function markAllRead(): Promise<void> {
    await store.markAllRead();
    items.value = items.value.map((item) => ({ ...item, read: true }));
}

onMounted(() => load(true));
</script>

<template>
    <div>
        <PageHeader
            title="Notifications"
            description="Deposits, transfers and withdrawals on your wallets."
        >
            <template #actions>
                <Button
                    v-if="store.unreadCount > 0"
                    variant="outline"
                    size="sm"
                    @click="markAllRead"
                >
                    Mark all read
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 inline-flex rounded-md border border-border p-0.5" role="tablist">
            <button
                v-for="option in (['all', 'unread'] as const)"
                :key="option"
                type="button"
                role="tab"
                :aria-selected="filter === option"
                class="rounded px-3 py-1.5 text-sm capitalize"
                :class="
                    filter === option
                        ? 'bg-accent text-accent-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="setFilter(option)"
            >
                {{ option }}
            </button>
        </div>

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <Spinner v-if="loading" />

        <EmptyState
            v-else-if="items.length === 0"
            title="Nothing here"
            :description="
                filter === 'unread' ? 'You have read everything.' : 'You have no notifications yet.'
            "
        />

        <div v-else class="space-y-2">
            <article
                v-for="item in items"
                :key="item.id"
                class="flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3"
            >
                <span
                    class="mt-1.5 size-2 shrink-0 rounded-full"
                    :class="item.read ? 'bg-transparent' : 'bg-primary'"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-medium">{{ item.title }}</h2>
                        <Badge v-if="!item.read" tone="info">Unread</Badge>
                    </div>
                    <p v-if="item.body" class="mt-0.5 text-sm text-muted-foreground">
                        {{ item.body }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatRelativeTime(item.created_at) }}
                    </p>
                </div>
                <Button
                    v-if="!item.read"
                    variant="ghost"
                    size="sm"
                    @click="markRead(item)"
                >
                    Mark read
                </Button>
            </article>

            <div v-if="nextCursor" class="pt-2 text-center">
                <Button variant="outline" :loading="loadingMore" @click="load(false)">
                    Load more
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import { notificationsService } from '@/services/notifications';
import { apiErrorMessage } from '@/lib/errors';
import { formatRelativeTime } from '@/lib/format/date';
import type { UserNotification } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import RetryPanel from '@/components/ui/RetryPanel.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const store = useNotificationsStore();

type Filter = 'all' | 'unread';

const tabOptions: Filter[] = ['all', 'unread'];

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

function onTablistKeydown(event: KeyboardEvent): void {
    const currentIndex = tabOptions.indexOf(filter.value);
    let next = currentIndex;

    if (event.key === 'ArrowRight') {
        next = (currentIndex + 1) % tabOptions.length;
    } else if (event.key === 'ArrowLeft') {
        next = (currentIndex - 1 + tabOptions.length) % tabOptions.length;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = tabOptions.length - 1;
    } else {
        return;
    }

    event.preventDefault();
    const option = tabOptions[next] ?? 'all';
    void setFilter(option);

    void nextTick(() => document.getElementById(`tab-${option}`)?.focus());
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

        <div
            class="mb-4 inline-flex rounded-md border border-border p-0.5"
            role="tablist"
            aria-label="Filter notifications"
            @keydown="onTablistKeydown"
        >
            <button
                v-for="option in tabOptions"
                :id="`tab-${option}`"
                :key="option"
                type="button"
                role="tab"
                aria-controls="notifications-tabpanel"
                :aria-selected="filter === option"
                :tabindex="filter === option ? 0 : -1"
                class="rounded px-3 py-1.5 text-sm capitalize focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
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

        <div
            id="notifications-tabpanel"
            role="tabpanel"
            :aria-labelledby="`tab-${filter}`"
            class="mt-2"
        >
            <RetryPanel
                v-if="errorMessage && items.length === 0"
                :message="errorMessage"
                :retrying="loading"
                @retry="() => load(true)"
            />

            <Alert v-else-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

            <Spinner v-if="loading" />

            <EmptyState
                v-else-if="items.length === 0"
                title="Nothing here"
                :description="
                    filter === 'unread'
                        ? 'You have read everything.'
                        : 'You have no notifications yet.'
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
                    <Button v-if="!item.read" variant="ghost" size="sm" @click="markRead(item)">
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
    </div>
</template>

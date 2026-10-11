<script setup lang="ts">
import type { AuditEvent } from '@/types/api';

defineProps<{ events: AuditEvent[] }>();

function describe(action: string): string {
    return action.replace(/[._]/g, ' ');
}
</script>

<template>
    <p v-if="events.length === 0" class="text-sm text-muted-foreground">No audit events.</p>
    <ol v-else class="space-y-3">
        <li v-for="event in events" :key="event.id" class="rounded-lg border border-border p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium capitalize">{{ describe(event.action) }}</p>
                <span class="text-xs text-muted-foreground">
                    {{ event.occurred_at?.slice(0, 19).replace('T', ' ') ?? '—' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ event.actor_type ?? 'system' }}
                <span v-if="event.actor_id">{{ event.actor_id.slice(0, 8) }}</span>
                <span v-if="event.subject_type">
                    · {{ event.subject_type }}
                    {{ event.subject_id?.slice(0, 8) ?? '' }}
                </span>
            </p>
        </li>
    </ol>
</template>

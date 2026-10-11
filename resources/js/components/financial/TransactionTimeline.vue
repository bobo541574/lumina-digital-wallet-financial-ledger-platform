<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { statusTone } from '@/lib/status';
import type { BadgeTone } from '@/lib/status';
import type { TimelineItem } from '@/types/ui';

const props = defineProps<{ items: TimelineItem[] }>();

const dotByTone: Record<BadgeTone, string> = {
    neutral: 'bg-muted-foreground',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-destructive',
    info: 'bg-primary',
};

const last = computed(() => props.items.length - 1);
</script>

<template>
    <ol class="space-y-4">
        <li v-for="(item, index) in items" :key="index" class="flex gap-3">
            <div class="flex flex-col items-center">
                <span
                    class="mt-1 size-2.5 rounded-full"
                    :class="dotByTone[statusTone(item.status)]"
                    aria-hidden="true"
                />
                <span v-if="index < last" class="mt-1 w-px flex-1 bg-border" />
            </div>
            <div class="pb-1">
                <p class="text-sm font-medium">{{ item.label ?? item.status }}</p>
                <div class="mt-0.5 flex items-center gap-2">
                    <StatusBadge :status="item.status" />
                    <span v-if="item.at" class="text-xs text-muted-foreground">
                        {{ item.at.slice(0, 19).replace('T', ' ') }}
                    </span>
                </div>
            </div>
        </li>
    </ol>
</template>

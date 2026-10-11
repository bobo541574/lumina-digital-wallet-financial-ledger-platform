<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import { humanizeStatus, statusTone } from '@/lib/status';

const props = defineProps<{ status: string }>();

const tone = computed(() => statusTone(props.status));
const label = computed(() => humanizeStatus(props.status));
</script>

<template>
    <Badge :tone="tone">{{ label }}</Badge>
</template>

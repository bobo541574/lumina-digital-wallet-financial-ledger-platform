<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useTheme } from '@/composables/useTheme';

const { theme, toggle } = useTheme();

const label = computed(() => (theme.value === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'));
</script>

<template>
    <Button
        variant="ghost"
        size="sm"
        :aria-label="label"
        :title="label"
        :aria-pressed="theme === 'dark'"
        @click="toggle"
    >
        <span aria-hidden="true">{{ theme === 'dark' ? '☀' : '☾' }}</span>
    </Button>
</template>

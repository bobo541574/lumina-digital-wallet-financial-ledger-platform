<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

const props = withDefaults(
    defineProps<{ permission: string | string[]; requireAll?: boolean }>(),
    { requireAll: false },
);

const auth = useAuthStore();

const allowed = computed(() => {
    const required = Array.isArray(props.permission) ? props.permission : [props.permission];

    return props.requireAll
        ? required.every((permission) => auth.hasPermission(permission))
        : required.some((permission) => auth.hasPermission(permission));
});
</script>

<template>
    <slot v-if="allowed" />
    <slot v-else name="fallback" />
</template>

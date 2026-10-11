<script setup lang="ts">
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

withDefaults(defineProps<{ message?: string; retrying?: boolean }>(), {
    message: undefined,
    retrying: false,
});

defineEmits<{ (e: 'retry'): void }>();
</script>

<template>
    <div class="space-y-3">
        <Alert variant="error" title="Something went wrong">
            {{ message ?? 'We could not load this content. Please try again.' }}
        </Alert>
        <Button variant="outline" :loading="retrying" @click="$emit('retry')">Try again</Button>
    </div>
</template>

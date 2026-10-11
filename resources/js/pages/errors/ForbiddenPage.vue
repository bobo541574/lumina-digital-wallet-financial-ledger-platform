<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-sm font-medium text-destructive">403</p>
        <h1 class="text-3xl font-semibold">Access denied</h1>
        <p class="max-w-md text-muted-foreground">
            You do not have permission to view this page. Contact an administrator if you believe
            this is a mistake.
        </p>
        <RouterLink
            :to="{ name: 'dashboard' }"
            class="mt-2 text-sm font-medium text-primary hover:underline"
        >
            Back to dashboard
        </RouterLink>
    </div>
</template>

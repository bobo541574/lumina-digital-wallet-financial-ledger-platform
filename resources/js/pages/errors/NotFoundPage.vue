<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-sm font-medium text-muted-foreground">404</p>
        <h1 class="text-3xl font-semibold">Page not found</h1>
        <p class="max-w-md text-muted-foreground">
            The page you are looking for does not exist or may have moved.
        </p>
        <RouterLink
            :to="{ name: 'dashboard' }"
            class="mt-2 text-sm font-medium text-primary hover:underline"
        >
            Back to dashboard
        </RouterLink>
    </div>
</template>

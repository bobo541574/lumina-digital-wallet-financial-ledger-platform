<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-8 px-6 text-center">
        <div class="max-w-xl">
            <h1 class="text-4xl font-semibold tracking-tight">Lumina Digital Wallet</h1>
            <p class="mt-4 text-lg text-muted-foreground">
                A multi-currency wallet with a balanced, auditable ledger. Send, receive and
                reconcile money with confidence.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <RouterLink
                :to="{ name: 'login' }"
                class="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Sign in
            </RouterLink>
            <RouterLink
                :to="{ name: 'register' }"
                class="inline-flex h-10 items-center rounded-md border border-input px-5 text-sm font-medium hover:bg-accent"
            >
                Create account
            </RouterLink>
        </div>
    </div>
</template>

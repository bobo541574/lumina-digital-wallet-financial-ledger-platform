<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { apiErrorMessage } from '@/lib/errors';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

const auth = useAuthStore();
const { loading } = storeToRefs(auth);
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const errorMessage = ref<string | null>(null);

async function submit(): Promise<void> {
    errorMessage.value = null;

    if (password.value !== passwordConfirmation.value) {
        errorMessage.value = 'Passwords do not match.';
        return;
    }

    try {
        await auth.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            device_name: 'web',
        });

        await router.push({ name: 'dashboard' });
    } catch (error) {
        errorMessage.value = apiErrorMessage(error);
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-background px-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <RouterLink :to="{ name: 'landing' }" class="text-xl font-semibold"
                    >Lumina</RouterLink
                >
                <h1 class="mt-4 text-2xl font-semibold">Create your account</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Open a wallet in a couple of minutes.
                </p>
            </div>

            <Alert v-if="errorMessage" variant="error" class="mb-4">{{ errorMessage }}</Alert>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="name" required>Full name</Label>
                    <Input id="name" v-model="name" autocomplete="name" required />
                </div>

                <div class="space-y-1.5">
                    <Label for="email" required>Email</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        required
                        placeholder="you@example.com"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="password" required>Password</Label>
                    <Input
                        id="password"
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="password_confirmation" required>Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <Button type="submit" class="w-full" :loading="loading">Create account</Button>
            </form>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                Already registered?
                <RouterLink
                    :to="{ name: 'login' }"
                    class="font-medium text-primary hover:underline"
                >
                    Sign in
                </RouterLink>
            </p>
        </div>
    </div>
</template>

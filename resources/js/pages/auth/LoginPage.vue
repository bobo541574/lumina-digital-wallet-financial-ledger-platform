<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { apiErrorCode, apiErrorMessage } from '@/lib/errors';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

const auth = useAuthStore();
const { loading } = storeToRefs(auth);
const route = useRoute();
const router = useRouter();

const email = ref('');
const password = ref('');
const twoFactorCode = ref('');
const mfaRequired = ref(false);
const errorMessage = ref<string | null>(null);

const redirectTarget = computed(() => {
    const redirect = route.query.redirect;
    return typeof redirect === 'string' ? redirect : null;
});

async function submit(): Promise<void> {
    errorMessage.value = null;

    try {
        await auth.login({
            email: email.value,
            password: password.value,
            device_name: 'web',
            ...(twoFactorCode.value ? { two_factor_code: twoFactorCode.value } : {}),
        });

        await router.push(redirectTarget.value ?? { name: 'dashboard' });
    } catch (error) {
        if (apiErrorCode(error) === 'MFA_REQUIRED') {
            mfaRequired.value = true;
            errorMessage.value = 'Enter the code from your authenticator app to continue.';
            return;
        }

        if (apiErrorCode(error) === 'TWO_FACTOR_INVALID_CODE') {
            mfaRequired.value = true;
            errorMessage.value = 'That two-factor code is not valid. Try again.';
            return;
        }

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
                <h1 class="mt-4 text-2xl font-semibold">Sign in</h1>
                <p class="mt-1 text-sm text-muted-foreground">Access your wallets and activity.</p>
            </div>

            <Alert v-if="errorMessage" variant="error" class="mb-4">{{ errorMessage }}</Alert>

            <form class="space-y-4" @submit.prevent="submit">
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
                        autocomplete="current-password"
                        required
                    />
                </div>

                <div v-if="mfaRequired" class="space-y-1.5">
                    <Label for="two_factor_code" required>Two-factor code</Label>
                    <Input
                        id="two_factor_code"
                        v-model="twoFactorCode"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                    />
                </div>

                <Button type="submit" class="w-full" :loading="loading">Sign in</Button>
            </form>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                No account?
                <RouterLink
                    :to="{ name: 'register' }"
                    class="font-medium text-primary hover:underline"
                >
                    Create one
                </RouterLink>
            </p>
        </div>
    </div>
</template>

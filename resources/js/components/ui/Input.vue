<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const model = defineModel<string | number>();
</script>

<template>
    <input
        v-model="model"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ' +
                    'placeholder:text-muted-foreground focus-visible:outline-none ' +
                    'disabled:cursor-not-allowed disabled:opacity-50',
            )
        "
        v-bind="$attrs"
    />
</template>

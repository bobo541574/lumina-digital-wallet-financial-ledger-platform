<script setup lang="ts">
import { computed } from 'vue';
import { groupPermissions } from '@/lib/permissions';
import type { Permission } from '@/types/api';

const props = withDefaults(
    defineProps<{
        permissions: Permission[];
        modelValue: string[];
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const groups = computed(() => groupPermissions(props.permissions));
const selected = computed(() => new Set(props.modelValue));

function toggle(name: string): void {
    const next = new Set(props.modelValue);

    if (next.has(name)) {
        next.delete(name);
    } else {
        next.add(name);
    }

    emit('update:modelValue', [...next]);
}

function toggleGroup(names: string[]): void {
    const next = new Set(props.modelValue);
    const allOn = names.every((name) => next.has(name));

    for (const name of names) {
        if (allOn) {
            next.delete(name);
        } else {
            next.add(name);
        }
    }

    emit('update:modelValue', [...next]);
}
</script>

<template>
    <div class="space-y-6">
        <fieldset
            v-for="group in groups"
            :key="group.key"
            class="rounded-lg border border-border p-4"
            :disabled="disabled"
        >
            <div class="mb-3 flex items-center justify-between">
                <legend class="text-sm font-semibold">{{ group.label }}</legend>
                <button
                    type="button"
                    class="text-xs text-primary hover:underline disabled:opacity-50"
                    :disabled="disabled"
                    @click="
                        toggleGroup(group.permissions.map((permission) => permission.name))
                    "
                >
                    {{
                        group.permissions.every((p) => selected.has(p.name))
                            ? 'Clear all'
                            : 'Select all'
                    }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label
                    v-for="permission in group.permissions"
                    :key="permission.name"
                    class="flex items-start gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring"
                        :checked="selected.has(permission.name)"
                        :disabled="disabled"
                        :value="permission.name"
                        @change="toggle(permission.name)"
                    />
                    <span>
                        <span class="block">{{ permission.label }}</span>
                        <span class="block font-mono text-xs text-muted-foreground">{{
                            permission.name
                        }}</span>
                    </span>
                </label>
            </div>
        </fieldset>
    </div>
</template>

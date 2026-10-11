<script setup lang="ts">
import Spinner from '@/components/ui/Spinner.vue';
import type { TableColumn } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        columns: TableColumn[];
        loading?: boolean;
        empty?: boolean;
        emptyText?: string;
        caption?: string;
    }>(),
    {
        loading: false,
        empty: false,
        emptyText: 'No records found.',
        caption: 'Data table',
    },
);

function alignClass(align?: TableColumn['align']): string {
    if (align === 'right') {
        return 'text-right';
    }

    if (align === 'center') {
        return 'text-center';
    }

    return 'text-left';
}

const colspan = props.columns.length;
</script>

<template>
    <Spinner v-if="loading" />
    <div v-else class="overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead class="bg-muted text-left text-muted-foreground">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-3 font-medium"
                        :class="[alignClass(column.align), column.class]"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="empty">
                    <td :colspan="colspan" class="px-4 py-6 text-center text-muted-foreground">
                        {{ emptyText }}
                    </td>
                </tr>
                <slot v-else />
            </tbody>
        </table>
    </div>
</template>

<script setup lang="ts">
import DataTable from '@/components/ui/DataTable.vue';
import type { TableColumn } from '@/types/ui';

// Thin compatibility wrapper around <DataTable>. Prefer DataTable in new code.
withDefaults(
    defineProps<{
        columns: TableColumn[];
        loading?: boolean;
        empty?: boolean;
        emptyText?: string;
        colspan?: number;
    }>(),
    {
        loading: false,
        empty: false,
        emptyText: 'No records found.',
        colspan: undefined,
    },
);
</script>

<template>
    <DataTable
        :columns="columns"
        :loading="loading"
        :empty="empty"
        :empty-text="emptyText"
        :caption="emptyText"
    >
        <slot />
    </DataTable>
</template>

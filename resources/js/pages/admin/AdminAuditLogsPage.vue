<script setup lang="ts">
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import type { AuditEvent } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import ResourceTable from '@/components/layout/ResourceTable.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const { items, loading, errorMessage } = useAdminList<AuditEvent>(() => adminService.auditEvents());
</script>

<template>
    <div>
        <PageHeader title="Audit log" description="Append-only record of privileged actions." />
        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <ResourceTable
            :columns="[
                { key: 'occurred_at', label: 'When' },
                { key: 'action', label: 'Action' },
                { key: 'actor', label: 'Actor' },
                { key: 'subject', label: 'Subject' },
            ]"
            :loading="loading"
            :empty="items.length === 0"
            :colspan="4"
        >
            <tr v-for="event in items" :key="event.id" class="border-t border-border">
                <td class="px-4 py-3 text-muted-foreground">
                    {{ event.occurred_at?.slice(0, 19) ?? '—' }}
                </td>
                <td class="px-4 py-3 font-medium">{{ event.action }}</td>
                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ event.actor_id ?? 'system' }}
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{
                        event.subject_type
                            ? `${event.subject_type} #${event.subject_id?.slice(0, 8)}`
                            : '—'
                    }}
                </td>
            </tr>
        </ResourceTable>
    </div>
</template>

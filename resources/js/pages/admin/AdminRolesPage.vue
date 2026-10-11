<script setup lang="ts">
import { ref } from 'vue';
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import type { Permission, Role } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DataTable from '@/components/ui/DataTable.vue';
import PermissionGate from '@/components/ui/PermissionGate.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PermissionChecklist from '@/components/admin/PermissionChecklist.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const {
    items: roleList,
    loading: rolesLoading,
    errorMessage: rolesError,
    reload: reloadRoles,
} = useAdminList<Role>(() => adminService.roles());
const { items: catalog, loading: catalogLoading } = useAdminList<Permission>(() =>
    adminService.permissions(),
);
const { pending, run } = useAsyncAction();

const editing = ref<Role | null>(null);
const selected = ref<string[]>([]);

function open(role: Role): void {
    editing.value = role;
    selected.value = [...(role.permissions ?? [])];
}

function close(): void {
    editing.value = null;
}

async function save(): Promise<void> {
    const role = editing.value;

    if (!role) {
        return;
    }

    const result = await run(() => adminService.updateRolePermissions(role.id, selected.value), {
        success: 'Role permissions updated.',
    });

    if (result !== null) {
        close();
        await reloadRoles();
    }
}
</script>

<template>
    <div>
        <PageHeader
            title="Roles & permissions"
            description="Review the built-in roles and adjust the permission set each one grants."
        />

        <Alert v-if="rolesError" variant="error" class="mb-6">{{ rolesError }}</Alert>

        <DataTable
            :columns="[
                { key: 'role', label: 'Role' },
                { key: 'permissions', label: 'Permissions', align: 'right' },
                { key: 'users', label: 'Users', align: 'right' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="rolesLoading"
            :empty="roleList.length === 0"
            empty-text="No roles defined."
        >
            <tr v-for="role in roleList" :key="role.id" class="border-t border-border">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ role.label }}</span>
                        <Badge v-if="role.is_system" tone="neutral">System</Badge>
                    </div>
                    <div class="font-mono text-xs text-muted-foreground">{{ role.name }}</div>
                </td>
                <td class="px-4 py-3 text-right">{{ role.permissions?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-right">{{ role.users_count ?? 0 }}</td>
                <td class="px-4 py-3 text-right">
                    <PermissionGate permission="roles.manage">
                        <Button size="sm" variant="outline" @click="open(role)">
                            Edit permissions
                        </Button>
                    </PermissionGate>
                </td>
            </tr>
        </DataTable>

        <ConfirmDialog
            :open="editing !== null"
            :title="editing ? `Edit permissions · ${editing.label}` : ''"
            description="Changes take effect immediately and are recorded in the audit trail."
            confirm-label="Save permissions"
            :loading="pending"
            @update:open="(open) => { if (!open) close(); }"
            @confirm="save"
        >
            <Spinner v-if="catalogLoading" />
            <template v-else>
                <p
                    v-if="editing?.name === 'admin'"
                    class="mb-4 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                >
                    The admin role must keep the roles.manage permission.
                </p>
                <PermissionChecklist v-model="selected" :permissions="catalog" :disabled="pending" />
            </template>
        </ConfirmDialog>
    </div>
</template>

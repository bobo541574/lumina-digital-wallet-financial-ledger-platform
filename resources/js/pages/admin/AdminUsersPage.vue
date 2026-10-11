<script setup lang="ts">
import { computed, ref } from 'vue';
import { adminService } from '@/services/admin';
import { useAdminList } from '@/composables/useAdminList';
import { useAsyncAction } from '@/composables/useAsyncAction';
import { apiErrorMessage } from '@/lib/errors';
import { selectionChanged } from '@/lib/permissions';
import type { AdminUser, AdminUserDetail, Permission, Role } from '@/types/api';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FilterBar from '@/components/ui/FilterBar.vue';
import PermissionGate from '@/components/ui/PermissionGate.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import Select from '@/components/ui/Select.vue';
import Spinner from '@/components/ui/Spinner.vue';
import PermissionChecklist from '@/components/admin/PermissionChecklist.vue';
import PageHeader from '@/components/layout/PageHeader.vue';

const {
    items: users,
    loading,
    errorMessage,
    reload: reloadUsers,
} = useAdminList<AdminUser>(() => adminService.users());
const { items: roles } = useAdminList<Role>(() => adminService.roles());
const { items: catalog } = useAdminList<Permission>(() => adminService.permissions());
const { pending, run } = useAsyncAction();

const search = ref('');
const roleFilter = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return users.value.filter((user) => {
        const matchesTerm =
            term === '' ||
            user.name.toLowerCase().includes(term) ||
            user.email.toLowerCase().includes(term);
        const matchesRole = roleFilter.value === '' || user.roles.includes(roleFilter.value);

        return matchesTerm && matchesRole;
    });
});

const hasActiveFilters = computed(() => search.value !== '' || roleFilter.value !== '');

const roleOptions = computed(() => roles.value.map((role) => ({ value: role.name, label: role.label })));

const target = ref<AdminUser | null>(null);
const detail = ref<AdminUserDetail | null>(null);
const detailLoading = ref(false);
const detailError = ref<string | null>(null);
const selectedRoles = ref<string[]>([]);
const selectedPermissions = ref<string[]>([]);
let originals: { roles: string[]; permissions: string[] } = { roles: [], permissions: [] };

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString() : '—';
}

function toggleRole(name: string): void {
    const next = new Set(selectedRoles.value);

    if (next.has(name)) {
        next.delete(name);
    } else {
        next.add(name);
    }

    selectedRoles.value = [...next];
}

async function open(user: AdminUser): Promise<void> {
    target.value = user;
    detail.value = null;
    detailError.value = null;
    detailLoading.value = true;
    selectedRoles.value = [];
    selectedPermissions.value = [];

    try {
        const loaded = await adminService.user(user.id);
        detail.value = loaded;
        selectedRoles.value = [...loaded.roles];
        selectedPermissions.value = [...loaded.direct_permissions];
        originals = {
            roles: [...loaded.roles],
            permissions: [...loaded.direct_permissions],
        };
    } catch (error) {
        detailError.value = apiErrorMessage(error);
    } finally {
        detailLoading.value = false;
    }
}

function close(): void {
    target.value = null;
    detail.value = null;
}

async function save(): Promise<void> {
    const user = target.value;

    if (!user || !detail.value) {
        return;
    }

    const result = await run(
        async () => {
            if (selectionChanged(originals.roles, selectedRoles.value)) {
                await adminService.updateUserRoles(user.id, selectedRoles.value);
            }

            if (selectionChanged(originals.permissions, selectedPermissions.value)) {
                await adminService.updateUserPermissions(user.id, selectedPermissions.value);
            }
        },
        { success: 'Access updated.' },
    );

    if (result !== null) {
        close();
        await reloadUsers();
    }
}
</script>

<template>
    <div>
        <PageHeader
            title="Users & access"
            description="Search customers and operators, then assign roles and direct permissions."
        />

        <Alert v-if="errorMessage" variant="error" class="mb-6">{{ errorMessage }}</Alert>

        <FilterBar
            class="mb-4"
            :has-active-filters="hasActiveFilters"
            @clear="
                () => {
                    search = '';
                    roleFilter = '';
                }
            "
        >
            <div class="w-64">
                <SearchInput v-model="search" placeholder="Search name or email" />
            </div>
            <div class="w-48">
                <Select
                    id="user-role-filter"
                    v-model="roleFilter"
                    :options="roleOptions"
                    placeholder="All roles"
                    aria-label="Filter by role"
                />
            </div>
        </FilterBar>

        <DataTable
            :columns="[
                { key: 'user', label: 'User' },
                { key: 'roles', label: 'Roles' },
                { key: 'created', label: 'Joined' },
                { key: 'actions', label: 'Actions', align: 'right' },
            ]"
            :loading="loading"
            :empty="filtered.length === 0"
            empty-text="No users match these filters."
        >
            <tr v-for="user in filtered" :key="user.id" class="border-t border-border">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="role in user.roles"
                            :key="role"
                            :tone="role === 'admin' ? 'info' : 'neutral'"
                        >
                            {{ role }}
                        </Badge>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-muted-foreground">
                    {{ formatDate(user.created_at) }}
                </td>
                <td class="px-4 py-3 text-right">
                    <PermissionGate permission="users.manage">
                        <Button size="sm" variant="outline" @click="open(user)">
                            Manage access
                        </Button>
                    </PermissionGate>
                </td>
            </tr>
        </DataTable>

        <ConfirmDialog
            :open="target !== null"
            :title="target ? `Access · ${target.name}` : ''"
            description="Roles grant a baseline permission set. Direct permissions are added on top."
            confirm-label="Save access"
            :loading="pending"
            @update:open="(open) => { if (!open) close(); }"
            @confirm="save"
        >
            <Spinner v-if="detailLoading" />
            <Alert v-else-if="detailError" variant="error">{{ detailError }}</Alert>
            <div v-else class="space-y-6">
                <fieldset class="rounded-lg border border-border p-4" :disabled="pending">
                    <legend class="mb-3 text-sm font-semibold">Roles</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring"
                                :checked="selectedRoles.includes(role.name)"
                                :disabled="pending"
                                @change="toggleRole(role.name)"
                            />
                            <span>{{ role.label }}</span>
                        </label>
                    </div>
                </fieldset>

                <div>
                    <h3 class="mb-3 text-sm font-semibold">Direct permissions</h3>
                    <PermissionChecklist
                        v-model="selectedPermissions"
                        :permissions="catalog"
                        :disabled="pending"
                    />
                </div>
            </div>
        </ConfirmDialog>
    </div>
</template>

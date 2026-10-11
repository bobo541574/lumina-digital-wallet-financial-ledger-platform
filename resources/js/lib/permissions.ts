import type { Permission } from '@/types/api';

export interface PermissionGroup {
    key: string;
    label: string;
    permissions: Permission[];
}

/**
 * Turn a permission key into a human label, e.g. `withdrawals.approve` -> the
 * group key `withdrawals`.
 */
export function permissionGroupKey(name: string): string {
    return name.split('.')[0] ?? name;
}

function humanize(key: string): string {
    const spaced = key.replace(/[_-]+/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/**
 * Group a flat permission catalog by its domain prefix (the part before the
 * first dot), preserving first-seen order so the UI stays stable.
 */
export function groupPermissions(permissions: Permission[]): PermissionGroup[] {
    const groups = new Map<string, Permission[]>();

    for (const permission of permissions) {
        const key = permissionGroupKey(permission.name);
        const bucket = groups.get(key) ?? [];
        bucket.push(permission);
        groups.set(key, bucket);
    }

    return [...groups.entries()].map(([key, items]) => ({
        key,
        label: humanize(key),
        permissions: items,
    }));
}

/** Whether the selection differs from the original set (order-insensitive). */
export function selectionChanged(original: string[], selected: string[]): boolean {
    if (original.length !== selected.length) {
        return true;
    }

    const originalSet = new Set(original);

    return selected.some((name) => !originalSet.has(name));
}

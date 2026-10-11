import { describe, expect, it } from 'vitest';
import { groupPermissions, permissionGroupKey, selectionChanged } from '@/lib/permissions';
import type { Permission } from '@/types/api';

const catalog: Permission[] = [
    { name: 'wallets.view', label: 'Wallets View' },
    { name: 'wallets.manage', label: 'Wallets Manage' },
    { name: 'deposits.approve', label: 'Deposits Approve' },
    { name: 'audit.view', label: 'Audit View' },
];

describe('permissionGroupKey', () => {
    it('returns the prefix before the first dot', () => {
        expect(permissionGroupKey('withdrawals.approve')).toBe('withdrawals');
    });

    it('falls back to the whole name when there is no dot', () => {
        expect(permissionGroupKey('misc')).toBe('misc');
    });
});

describe('groupPermissions', () => {
    it('groups by domain prefix, preserving first-seen order', () => {
        const groups = groupPermissions(catalog);

        expect(groups.map((group) => group.key)).toEqual(['wallets', 'deposits', 'audit']);
        expect(groups[0]?.label).toBe('Wallets');
        expect(groups[0]?.permissions).toHaveLength(2);
        expect(groups[1]?.permissions).toHaveLength(1);
    });

    it('returns an empty array for an empty catalog', () => {
        expect(groupPermissions([])).toEqual([]);
    });
});

describe('selectionChanged', () => {
    it('is false for identical sets regardless of order', () => {
        expect(selectionChanged(['a', 'b'], ['b', 'a'])).toBe(false);
    });

    it('detects additions, removals and replacements', () => {
        expect(selectionChanged(['a'], ['a', 'b'])).toBe(true);
        expect(selectionChanged(['a', 'b'], ['a'])).toBe(true);
        expect(selectionChanged(['a'], ['b'])).toBe(true);
    });
});

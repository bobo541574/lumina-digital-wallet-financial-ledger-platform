import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        /** The route requires an authenticated session. */
        requiresAuth?: boolean;
        /** The route requires a specific permission. */
        permission?: string;
        /** The route requires an admin role. */
        requiresAdmin?: boolean;
        /** Human-readable title used by layouts and document.title. */
        title?: string;
    }
}

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'landing',
        component: () => import('@/pages/LandingPage.vue'),
        meta: { title: 'Lumina Digital Wallet' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { title: 'Sign in' },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/pages/auth/RegisterPage.vue'),
        meta: { title: 'Create account' },
    },
    {
        path: '/app',
        component: () => import('@/layouts/CustomerLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/customer/DashboardPage.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'wallets',
                name: 'wallets',
                component: () => import('@/pages/customer/WalletsPage.vue'),
                meta: { title: 'Wallets' },
            },
            {
                path: 'wallets/:id',
                name: 'wallet-detail',
                component: () => import('@/pages/customer/WalletDetailPage.vue'),
                meta: { title: 'Wallet' },
            },
            {
                path: 'transfer',
                name: 'transfer',
                component: () => import('@/pages/customer/TransferPage.vue'),
                meta: { title: 'Send money', permission: 'transfers.create' },
            },
            {
                path: 'deposit',
                name: 'deposit',
                component: () => import('@/pages/customer/DepositPage.vue'),
                meta: { title: 'Add money', permission: 'deposits.create' },
            },
            {
                path: 'withdraw',
                name: 'withdraw',
                component: () => import('@/pages/customer/WithdrawPage.vue'),
                meta: { title: 'Withdraw', permission: 'withdrawals.create' },
            },
            {
                path: 'history',
                name: 'history',
                component: () => import('@/pages/customer/HistoryPage.vue'),
                meta: { title: 'Activity' },
            },
            {
                path: 'notifications',
                name: 'notifications',
                component: () => import('@/pages/customer/NotificationsPage.vue'),
                meta: { title: 'Notifications' },
            },
        ],
    },
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            {
                path: '',
                name: 'admin-dashboard',
                component: () => import('@/pages/admin/AdminDashboardPage.vue'),
                meta: { title: 'Admin overview' },
            },
            {
                path: 'wallets',
                name: 'admin-wallets',
                component: () => import('@/pages/admin/AdminWalletsPage.vue'),
                meta: { title: 'Wallets', permission: 'wallets.manage' },
            },
            {
                path: 'deposits',
                name: 'admin-deposits',
                component: () => import('@/pages/admin/AdminDepositsPage.vue'),
                meta: { title: 'Deposits', permission: 'deposits.approve' },
            },
            {
                path: 'withdrawals',
                name: 'admin-withdrawals',
                component: () => import('@/pages/admin/AdminWithdrawalsPage.vue'),
                meta: { title: 'Withdrawals', permission: 'withdrawals.approve' },
            },
            {
                path: 'transfers',
                name: 'admin-transfers',
                component: () => import('@/pages/admin/AdminTransfersPage.vue'),
                meta: { title: 'Transfers', permission: 'ledger.view' },
            },
            {
                path: 'journal-entries',
                name: 'admin-journal-entries',
                component: () => import('@/pages/admin/AdminJournalEntriesPage.vue'),
                meta: { title: 'Journal entries', permission: 'ledger.view' },
            },
            {
                path: 'reconciliation',
                name: 'admin-reconciliation',
                component: () => import('@/pages/admin/AdminReconciliationPage.vue'),
                meta: { title: 'Reconciliation', permission: 'reconciliation.view' },
            },
            {
                path: 'audit-logs',
                name: 'admin-audit-logs',
                component: () => import('@/pages/admin/AdminAuditLogsPage.vue'),
                meta: { title: 'Audit log', permission: 'audit.view' },
            },
            {
                path: 'users',
                name: 'admin-users',
                component: () => import('@/pages/admin/AdminUsersPage.vue'),
                meta: { title: 'Users & access', permission: 'users.manage' },
            },
            {
                path: 'roles',
                name: 'admin-roles',
                component: () => import('@/pages/admin/AdminRolesPage.vue'),
                meta: { title: 'Roles & permissions', permission: 'roles.manage' },
            },
        ],
    },
    {
        path: '/forbidden',
        name: 'forbidden',
        component: () => import('@/pages/errors/ForbiddenPage.vue'),
        meta: { title: 'Forbidden' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/errors/NotFoundPage.vue'),
        meta: { title: 'Not found' },
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.bootstrap();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'forbidden' };
    }

    if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
        return { name: 'forbidden' };
    }

    if ((to.name === 'login' || to.name === 'register') && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    return true;
});

router.afterEach((to) => {
    const base = 'Lumina Digital Wallet';
    document.title = to.meta.title ? `${to.meta.title} · ${base}` : base;
});

export default router;

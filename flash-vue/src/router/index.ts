import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { getPublicMaintenanceStatus } from '@/services/maintenanceService'
import { getAuthenticatedHome, getStoredAuthUser, isAdminUser } from '@/utils/auth'

const routes: RouteRecordRaw[] = [
  // ── Landing page (standalone) ──────────────────
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/LandingView.vue'),
  },
  {
    path: '/',
    component: () => import('@/layouts/StandaloneLayout.vue'),
    children: [
      {
        path: 'pricing',
        name: 'pricing',
        component: () => import('@/views/client/PricingView.vue'),
      },
      {
        path: 'support',
        name: 'support',
        component: () => import('@/views/client/SupportView.vue'),
        meta: { requiresAuth: true },
      },
    ],
  },
  // ── Client (authenticated) ─────────────────────
  {
    path: '/',
    component: () => import('@/layouts/ClientLayout.vue'),
    children: [
      {
        path: 'chat',
        name: 'chat',
        component: () => import('@/views/client/HomeView.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/client/ProfileView.vue'),
        meta: { requiresAuth: true },
      },
    ],
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true, skipMaintenanceCheck: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/privacy-policy',
    name: 'privacy-policy',
    component: () => import('@/views/PrivacyPolicyView.vue'),
  },
  {
    path: '/maintenance',
    name: 'maintenance',
    component: () => import('@/views/MaintenanceView.vue'),
  },
  {
    path: '/oauth/callback',
    name: 'google-callback',
    component: () => import('@/views/GoogleCallbackView.vue'),
    meta: { skipMaintenanceCheck: true },
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('@/views/admin/DashboardView.vue'),
      },
      {
        path: 'users',
        name: 'admin-users',
        component: () => import('@/views/admin/UsersView.vue'),
        meta: { title: 'Users' },
      },
      {
        path: 'roles',
        name: 'admin-roles',
        component: () => import('@/views/admin/RolesView.vue'),
        meta: { title: 'Roles & Permissions' },
      },
      {
        path: 'ai-requests',
        name: 'admin-ai-requests',
        component: () => import('@/views/admin/AiRequestsView.vue'),
        meta: { title: 'AI Requests' },
      },
      {
        path: 'plans',
        name: 'admin-plans',
        component: () => import('@/views/admin/PlansView.vue'),
        meta: { title: 'Plans & Features' },
      },
      {
        path: 'styles',
        name: 'admin-styles',
        component: () => import('@/views/admin/StylesView.vue'),
        meta: { title: 'Visual Styles' },
      },
      {
        path: 'products',
        name: 'admin-products',
        component: () => import('@/views/admin/ProductsView.vue'),
        meta: { title: 'Products' },
      },
      {
        path: 'payments',
        name: 'admin-payments',
        component: () => import('@/views/admin/PaymentsView.vue'),
        meta: { title: 'Payments & Billing' },
      },
      {
        path: 'support',
        name: 'admin-support',
        component: () => import('@/views/admin/SupportView.vue'),
        meta: { title: 'Support Tickets' },
      },
      {
        path: 'settings',
        name: 'admin-settings',
        component: () => import('@/views/admin/SettingsView.vue'),
        meta: { title: 'System Settings' },
      },
      {
        path: 'profile',
        name: 'admin-profile',
        component: () => import('@/views/client/ProfileView.vue'),
        meta: { title: 'Profile' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// ── Navigation Guard ────────────────────────────────────────
router.beforeEach(async (to) => {
  const token = localStorage.getItem('auth_token')
  const isAuthenticated = !!token
  const storedUser = getStoredAuthUser()

  if (!to.meta.skipMaintenanceCheck) {
    try {
      const maintenance = await getPublicMaintenanceStatus()
      const canBypass = maintenance.can_bypass || isAdminUser(storedUser)

      if (maintenance.is_enabled && !canBypass && to.name !== 'maintenance') {
        return {
          name: 'maintenance',
          query: to.fullPath !== '/maintenance' ? { redirect: to.fullPath } : undefined,
        }
      }

      if (to.name === 'maintenance' && (!maintenance.is_enabled || canBypass)) {
        return isAuthenticated ? getAuthenticatedHome(storedUser) : '/'
      }
    } catch {
      // Do not block navigation when the status endpoint is temporarily unreachable.
    }
  }

  // Going to a protected route without a token → redirect to login
  if (to.matched.some(r => r.meta.requiresAuth) && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Going to an admin-only route with a non-admin account → redirect to profile
  if (to.matched.some(r => r.meta.requiresAdmin) && isAuthenticated && storedUser && !isAdminUser(storedUser)) {
    return '/profile'
  }

  // Going to a guest page while authenticated → redirect by role
  if (to.meta.guest && isAuthenticated) {
    return getAuthenticatedHome(storedUser)
  }

  return true
})

export default router

import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'
import ClientLayout from '@/layouts/ClientLayout.vue'
import { getAuthenticatedHome, getStoredAuthUser, isAdminUser } from '@/utils/auth'

const routes: RouteRecordRaw[] = [
  // ── Landing page (standalone) ──────────────────
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/LandingView.vue'),
  },
  // ── Client (authenticated) ─────────────────────
  {
    path: '/',
    component: ClientLayout,
    children: [
      {
        path: 'chat',
        name: 'chat',
        component: () => import('@/views/client/HomeView.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'pricing',
        name: 'pricing',
        component: () => import('@/views/client/PricingView.vue'),
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
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/oauth/callback',
    name: 'google-callback',
    component: () => import('@/views/GoogleCallbackView.vue'),
  },
  {
    path: '/admin',
    component: AdminLayout,
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
router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('auth_token')
  const isAuthenticated = !!token
  const storedUser = getStoredAuthUser()

  // Going to a protected route without a token → redirect to login
  if (to.matched.some(r => r.meta.requiresAuth) && !isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Going to an admin-only route with a non-admin account → redirect to profile
  if (to.matched.some(r => r.meta.requiresAdmin) && isAuthenticated && storedUser && !isAdminUser(storedUser)) {
    return next('/profile')
  }

  // Going to a guest page while authenticated → redirect by role
  if (to.meta.guest && isAuthenticated) {
    return next(getAuthenticatedHome(storedUser))
  }

  next()
})

export default router

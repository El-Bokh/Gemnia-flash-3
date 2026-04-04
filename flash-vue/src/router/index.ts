import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true },
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
    ],
  },
  {
    path: '/',
    redirect: '/admin',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/admin',
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

  // Going to a protected route without a token → redirect to login
  if (to.matched.some(r => r.meta.requiresAuth) && !isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Going to login while already authenticated → redirect to admin
  if (to.meta.guest && isAuthenticated) {
    return next('/admin')
  }

  next()
})

export default router

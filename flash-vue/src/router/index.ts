import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/admin',
    component: AdminLayout,
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
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router

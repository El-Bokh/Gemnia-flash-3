import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Notification } from '@/types/notifications'
import {
  getUserNotifications,
  getUserUnreadCount,
  markNotificationRead,
  markAllNotificationsRead,
  deleteNotification,
  getAdminNotifications,
  getAdminUnreadCount,
  markAdminNotificationRead,
  markAllAdminNotificationsRead,
  deleteAdminNotification,
} from '@/services/notificationService'

export const useNotificationStore = defineStore('notification', () => {
  // ─── State ───────────────────────────────────────────────

  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const isAdmin = ref(false)
  const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)

  // ─── Computed ────────────────────────────────────────────

  const hasUnread = computed(() => unreadCount.value > 0)

  // ─── Actions ─────────────────────────────────────────────

  function setMode(admin: boolean) {
    isAdmin.value = admin
  }

  async function fetchNotifications(page = 1) {
    loading.value = true
    try {
      const res = isAdmin.value
        ? await getAdminNotifications({ page, per_page: 10 })
        : await getUserNotifications({ page, per_page: 10 })
      notifications.value = res.data
    } catch {
      // Silently fail — don't break the UI
    } finally {
      loading.value = false
    }
  }

  async function fetchUnreadCount() {
    try {
      const res = isAdmin.value
        ? await getAdminUnreadCount()
        : await getUserUnreadCount()
      unreadCount.value = res.data.count
    } catch {
      // Silently fail
    }
  }

  async function markRead(id: number) {
    try {
      isAdmin.value
        ? await markAdminNotificationRead(id)
        : await markNotificationRead(id)

      const n = notifications.value.find(n => n.id === id)
      if (n && !n.is_read) {
        n.is_read = true
        n.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch {
      // Silently fail
    }
  }

  async function markAllRead() {
    try {
      isAdmin.value
        ? await markAllAdminNotificationsRead()
        : await markAllNotificationsRead()

      notifications.value.forEach(n => {
        n.is_read = true
        n.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    } catch {
      // Silently fail
    }
  }

  async function remove(id: number) {
    try {
      isAdmin.value
        ? await deleteAdminNotification(id)
        : await deleteNotification(id)

      const idx = notifications.value.findIndex(n => n.id === id)
      if (idx !== -1) {
        const removed = notifications.value[idx]
        if (removed && !removed.is_read) {
          unreadCount.value = Math.max(0, unreadCount.value - 1)
        }
        notifications.value.splice(idx, 1)
      }
    } catch {
      // Silently fail
    }
  }

  function startPolling(intervalMs = 30_000) {
    stopPolling()
    fetchUnreadCount()
    pollTimer.value = setInterval(() => {
      fetchUnreadCount()
    }, intervalMs)
  }

  function stopPolling() {
    if (pollTimer.value) {
      clearInterval(pollTimer.value)
      pollTimer.value = null
    }
  }

  function $reset() {
    stopPolling()
    notifications.value = []
    unreadCount.value = 0
    loading.value = false
  }

  return {
    // state
    notifications,
    unreadCount,
    loading,
    isAdmin,
    // computed
    hasUnread,
    // actions
    setMode,
    fetchNotifications,
    fetchUnreadCount,
    markRead,
    markAllRead,
    remove,
    startPolling,
    stopPolling,
    $reset,
  }
})

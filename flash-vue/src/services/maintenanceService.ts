import axios from 'axios'
import type { PublicMaintenanceStatus } from '@/types/settings'
import {
  getMaintenanceStatusFromCache,
  peekMaintenanceStatus,
  setMaintenanceStatus,
} from '@/utils/maintenanceState'

interface MaintenanceApiResponse {
  success: boolean
  data: PublicMaintenanceStatus
}

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://klek.studio/api'

export async function getPublicMaintenanceStatus(force = false): Promise<PublicMaintenanceStatus> {
  const cached = force ? null : getMaintenanceStatusFromCache()

  if (cached) {
    return cached
  }

  const token = localStorage.getItem('auth_token')
  const response = await axios.get<MaintenanceApiResponse>(`${API_BASE_URL}/maintenance/status`, {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    timeout: 10_000,
  })

  setMaintenanceStatus(response.data.data)

  return response.data.data
}

export function getCachedMaintenanceStatus(): PublicMaintenanceStatus | null {
  return peekMaintenanceStatus()
}
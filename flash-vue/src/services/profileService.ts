import { apiPost, apiPut } from '@/api/client'
import type { ApiResponse } from '@/api/client'

// ─── Types ──────────────────────────────────────────────────

export interface ProfileData {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  locale: string
  timezone: string
  roles: string[]
}

export interface UpdateProfilePayload {
  name?: string
  email?: string
  phone?: string | null
  locale?: string
  timezone?: string
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

// ─── Service ────────────────────────────────────────────────

export function updateProfile(data: UpdateProfilePayload) {
  return apiPut<ApiResponse<ProfileData>>('/profile', data)
}

export function uploadProfileAvatar(file: File) {
  const formData = new FormData()
  formData.append('avatar', file)

  return apiPost<ApiResponse<ProfileData>>('/profile/avatar', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}

export function changePassword(data: ChangePasswordPayload) {
  return apiPut<ApiResponse<null>>('/profile/password', data)
}

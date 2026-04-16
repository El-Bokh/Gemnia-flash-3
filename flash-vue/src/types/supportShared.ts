export interface SupportAttachment {
  name: string
  url: string | null
  mime_type: string | null
  size: number | null
  is_image: boolean
}
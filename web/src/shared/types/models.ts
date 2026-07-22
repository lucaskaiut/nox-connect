import type { Permission } from '@/shared/constants/permissions'

export interface Role {
  id: number
  name: string
  description: string | null
  is_default: boolean
  permissions?: Permission[]
}

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  roles?: Role[]
  created_at: string | null
  updated_at: string | null
}

export interface Tenant {
  id: string
  name: string
  document: string
  email: string
  phone: string | null
  domain: string
  created_at: string | null
  updated_at: string | null
}

export interface ApiToken {
  id: number
  name: string
  permissions: string[] | null
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

export interface Webhook {
  id: number
  name: string
  url: string
  method: string
  event: string
  headers: Record<string, string> | null
  query_params: Record<string, string> | null
  body_template: Record<string, unknown> | null
  is_active: boolean
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export interface WebhookLog {
  id: number
  status_code: number | null
  response_body: string | null
  request_payload: Record<string, unknown> | null
  error_message: string | null
  duration_ms: number | null
  created_at: string | null
}

export interface Session {
  user: User
  tenant: Tenant
  roles: Role[]
  permissions: Permission[]
}

export interface WhatsAppConfig {
  id: number
  name: string
  waba_id: string
  phone_number_id: string
  is_active: boolean
  webhook_url: string | null
  last_connected_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface WhatsAppContact {
  id: number
  wa_id: string
  profile_name: string | null
  display_name: string | null
}

export interface WhatsAppMessage {
  id: number
  conversation_id: number
  direction: 'inbound' | 'outbound'
  message_type: string
  content: string | null
  media: Record<string, unknown> | null
  wa_message_id: string | null
  status: string
  metadata: Record<string, unknown> | null
  delivered_at: string | null
  read_at: string | null
  created_at: string | null
}

export interface WhatsAppAssignment {
  id: number
  user: { id: string; name: string } | null
  assigned_at: string | null
}

export interface WhatsAppNote {
  id: number
  content: string
  user: { id: string; name: string } | null
  created_at: string | null
}

export interface WhatsAppTag {
  id: number
  name: string
  color: string | null
  sort_order: number
  created_at: string | null
  updated_at: string | null
}

export interface KanbanStage {
  id: number
  name: string
  color: string | null
  sort_order: number
  is_default: boolean
  created_at: string | null
  updated_at: string | null
}

export interface WhatsAppConversation {
  id: number
  contact: WhatsAppContact
  status: string
  last_message_preview: string | null
  last_message_at: string | null
  is_unread: boolean
  current_assignment: WhatsAppAssignment | null
  tags: WhatsAppTag[]
  current_stage: { id: number; name: string; color: string | null } | null
  message_count?: number
  messages?: WhatsAppMessage[]
  notes?: WhatsAppNote[]
  created_at: string | null
  updated_at: string | null
}

export interface KanbanColumn {
  stage: KanbanStage
  conversations: WhatsAppConversation[]
}

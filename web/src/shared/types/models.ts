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
  is_master: boolean
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
  is_umbrella: boolean
  onboarding_completed?: boolean
  needs_onboarding?: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AvailableTenant {
  id: string
  name: string
  is_home?: boolean
  is_umbrella?: boolean
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
  is_master: boolean
  available_tenants: AvailableTenant[]
  onboarding?: {
    required: boolean
    completed: boolean
    company_completed: boolean
    whatsapp_completed: boolean
    current_step: string
    completed_at: string | null
    provider: string
    whatsapp?: {
      connected: boolean
      phone_number?: string | null
      connection_id?: string | null
    }
  } | null
}

export interface Plan {
  id: string
  name: string
  description: string | null
  price: string
  recurrence_value: number
  recurrence_unit: 'days' | 'weeks' | 'months' | 'years'
  free_trial_days: number
  trial_days?: number
  is_trial?: boolean
  requires_immediate_payment?: boolean
  active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface SubscriptionEvent {
  id: number
  event: string
  payload: Record<string, unknown> | null
  created_at: string | null
}

export interface Subscription {
  id: string
  status: 'ACTIVE' | 'TRIALING' | 'PAST_DUE' | 'SUSPENDED' | 'CANCELLED'
  payment_gateway: string | null
  started_at: string | null
  trial_ends_at: string | null
  last_billed_at: string | null
  next_billing_at: string | null
  cancelled_at: string | null
  plan?: Plan
  events?: SubscriptionEvent[]
  created_at: string | null
  updated_at: string | null
}

export interface PaymentGatewayOption {
  key: string
  label: string
  payment_method: string
}

export interface Invoice {
  id: string
  gateway: string | null
  amount: string
  status: 'PENDING' | 'PROCESSING' | 'PAID' | 'EXPIRED' | 'FAILED' | 'CANCELLED'
  payment_method: 'pix' | 'credit_card' | 'boleto' | null
  external_id: string | null
  pix_code: string | null
  pix_qrcode: string | null
  invoice_url?: string | null
  awaiting_payment_method?: boolean
  due_date: string | null
  paid_at: string | null
  expires_at: string | null
  subscription?: Subscription
  created_at: string | null
  updated_at: string | null
}

export interface WhatsAppConnection {
  provider: string
  connected: boolean
  status_message: string | null
  settings: Record<string, unknown>
  webhook_url: string
}

export interface WhatsAppContact {
  id: number
  external_contact_id: string
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
  external_message_id: string | null
  status: string
  metadata: Record<string, unknown> | null
  sender_name: string | null
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
  is_window_open: boolean
  window_expires_at: string | null
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

export interface MessageTemplate {
  id: string
  name: string | null
  language: string | null
  category: string | null
  sub_category: string | null
  status: string | null
  components: Record<string, unknown>[] | null
  parameter_format: string | null
  display_format: string | null
  quality_score: Record<string, unknown> | null
  health_status: Record<string, unknown> | null
  rejected_reason: string | null
  source: string | null
  message_send_ttl_seconds: number | null
  cta_url_link_tracking_opted_out: boolean | null
  allow_category_change: boolean | null
  is_primary_device_delivery_only: boolean | null
  is_sms_fallback_enabled: boolean | null
  library_template_name: string | null
  previous_category: string | null
  correct_category: string | null
  last_updated_time: string | null
  created_at: string | null
}

export interface MessageTemplatesResponse {
  data: MessageTemplate[]
  paging: {
    cursors: { before: string; after: string }
    next?: string
    previous?: string
  } | null
}

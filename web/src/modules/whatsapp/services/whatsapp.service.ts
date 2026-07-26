import { http } from '@/shared/api/http'
import type { ApiResponse, CursorPaginatedResponse } from '@/shared/types/api'
import type {
  KanbanColumn,
  KanbanStage,
  MessageTemplate,
  MessageTemplatesResponse,
  WhatsAppConnection,
  WhatsAppConversation,
  WhatsAppMessage,
  WhatsAppNote,
  WhatsAppTag,
} from '@/shared/types/models'

export interface WhatsAppConnectPayload {
  account_id?: string
  channel_id?: string
  session_id?: string
  connection_id?: string
  workspace_id?: string
  instance_id?: string
  webhook_verify_token?: string
}

export interface ConversationFilters {
  status?: string
  assigned_to?: string
  stage_id?: number
  tag_id?: number
  search?: string
  unassigned?: boolean
  per_page?: number
  cursor?: string | null
}

export interface ConversationStats {
  open: number
  closed: number
  unassigned: number
}

export interface WebhookLogEntry {
  id: number
  method: string
  url: string | null
  request_headers: Record<string, string[]> | null
  request_payload: Record<string, unknown> | null
  response_status: number | null
  response_body: string | null
  error_message: string | null
  duration_ms: number | null
  created_at: string | null
}

export interface TemplateParams {
  fields?: string
  limit?: number
  after?: string
  before?: string
}

export interface CreateTemplatePayload {
  name: string
  language: string
  category: string
  parameter_format?: string
  components?: Record<string, unknown>[]
  allow_category_change?: boolean
  cta_url_link_tracking_opted_out?: boolean
  message_send_ttl_seconds?: number
  sub_category?: string
  display_format?: string
  library_template_name?: string
  library_template_button_inputs?: Record<string, unknown>[]
  library_template_body_inputs?: Record<string, unknown>
  is_primary_device_delivery_only?: boolean
  send_type?: string
}

export interface UpdateTemplatePayload {
  category?: string
  parameter_format?: string
  components?: Record<string, unknown>[]
  allow_category_change?: boolean
  cta_url_link_tracking_opted_out?: boolean
  message_send_ttl_seconds?: number
  sub_category?: string
  display_format?: string
  is_primary_device_delivery_only?: boolean
}

export const whatsappService = {
  async getConnection(): Promise<WhatsAppConnection> {
    const response = await http.get<ApiResponse<WhatsAppConnection>>('/whatsapp/connection')
    return response.data.data
  },

  async connect(payload: WhatsAppConnectPayload): Promise<WhatsAppConnection> {
    const response = await http.post<ApiResponse<WhatsAppConnection>>('/whatsapp/connection', payload)
    return response.data.data
  },

  async disconnect(): Promise<void> {
    await http.delete('/whatsapp/connection')
  },

  async testConnection(): Promise<{ message: string }> {
    const response = await http.post<ApiResponse<null>>('/whatsapp/connection/test')
    return { message: response.data.message ?? '' }
  },

  async getWebhookLogs(): Promise<WebhookLogEntry[]> {
    const response = await http.get<ApiResponse<WebhookLogEntry[]>>('/whatsapp/connection/webhook-logs')
    return response.data.data
  },

  async listConversations(filters: ConversationFilters): Promise<CursorPaginatedResponse<WhatsAppConversation>> {
    const response = await http.get<CursorPaginatedResponse<WhatsAppConversation>>('/whatsapp/conversations', { params: filters })
    return response.data
  },

  async getConversation(id: number): Promise<WhatsAppConversation> {
    const response = await http.get<ApiResponse<WhatsAppConversation>>(`/whatsapp/conversations/${id}`)
    return response.data.data
  },

  async sendMessage(id: number, content: string): Promise<WhatsAppMessage> {
    const response = await http.post<ApiResponse<WhatsAppMessage>>(`/whatsapp/conversations/${id}/messages`, { content })
    return response.data.data
  },

  async assignConversation(id: number, userId: string): Promise<void> {
    await http.post(`/whatsapp/conversations/${id}/assign`, { user_id: userId })
  },

  async transferConversation(id: number, userId: string): Promise<void> {
    await http.post(`/whatsapp/conversations/${id}/transfer`, { user_id: userId })
  },

  async removeAssignment(id: number): Promise<void> {
    await http.post(`/whatsapp/conversations/${id}/remove-assignment`)
  },

  async closeConversation(id: number): Promise<void> {
    await http.post(`/whatsapp/conversations/${id}/close`)
  },

  async reopenConversation(id: number): Promise<void> {
    await http.post(`/whatsapp/conversations/${id}/reopen`)
  },

  async getNotes(id: number): Promise<WhatsAppNote[]> {
    const response = await http.get<ApiResponse<WhatsAppNote[]>>(`/whatsapp/conversations/${id}/notes`)
    return response.data.data
  },

  async addNote(id: number, content: string): Promise<WhatsAppNote> {
    const response = await http.post<ApiResponse<WhatsAppNote>>(`/whatsapp/conversations/${id}/notes`, { content })
    return response.data.data
  },

  async getConversationTags(id: number): Promise<WhatsAppTag[]> {
    const response = await http.get<ApiResponse<WhatsAppTag[]>>(`/whatsapp/conversations/${id}/tags`)
    return response.data.data
  },

  async syncConversationTags(id: number, tagIds: number[]): Promise<WhatsAppTag[]> {
    const response = await http.post<ApiResponse<WhatsAppTag[]>>(`/whatsapp/conversations/${id}/tags`, { tag_ids: tagIds })
    return response.data.data
  },

  async getConversationStats(): Promise<ConversationStats> {
    const response = await http.get<ApiResponse<ConversationStats>>('/whatsapp/conversations/stats')
    return response.data.data
  },

  async listTags(): Promise<WhatsAppTag[]> {
    const response = await http.get<ApiResponse<WhatsAppTag[]>>('/whatsapp/tags')
    return response.data.data
  },

  async createTag(payload: { name: string; color?: string; sort_order?: number }): Promise<WhatsAppTag> {
    const response = await http.post<ApiResponse<WhatsAppTag>>('/whatsapp/tags', payload)
    return response.data.data
  },

  async updateTag(id: number, payload: Partial<{ name: string; color: string; sort_order: number }>): Promise<WhatsAppTag> {
    const response = await http.patch<ApiResponse<WhatsAppTag>>(`/whatsapp/tags/${id}`, payload)
    return response.data.data
  },

  async deleteTag(id: number): Promise<void> {
    await http.delete(`/whatsapp/tags/${id}`)
  },

  async getKanbanBoard(): Promise<KanbanColumn[]> {
    const response = await http.get<ApiResponse<KanbanColumn[]>>('/whatsapp/kanban/board')
    return response.data.data
  },

  async listKanbanStages(): Promise<KanbanStage[]> {
    const response = await http.get<ApiResponse<KanbanStage[]>>('/whatsapp/kanban/stages')
    return response.data.data
  },

  async moveConversationStage(conversationId: number, stageId: number | null): Promise<void> {
    await http.post(`/whatsapp/kanban/conversations/${conversationId}/move`, { stage_id: stageId })
  },

  async createKanbanStage(payload: { name: string; color?: string; sort_order?: number }): Promise<KanbanStage> {
    const response = await http.post<ApiResponse<KanbanStage>>('/whatsapp/kanban/stages', payload)
    return response.data.data
  },

  async updateKanbanStage(id: number, payload: Partial<{ name: string; color: string; sort_order: number }>): Promise<KanbanStage> {
    const response = await http.patch<ApiResponse<KanbanStage>>(`/whatsapp/kanban/stages/${id}`, payload)
    return response.data.data
  },

  async deleteKanbanStage(id: number): Promise<void> {
    await http.delete(`/whatsapp/kanban/stages/${id}`)
  },

  async seedDefaultStages(): Promise<void> {
    await http.post('/whatsapp/kanban/seed-defaults')
  },

  async listTemplates(params?: TemplateParams): Promise<MessageTemplatesResponse> {
    const response = await http.get<ApiResponse<MessageTemplatesResponse>>('/whatsapp/templates', { params })
    return response.data.data
  },

  async getTemplate(templateId: string): Promise<MessageTemplate> {
    const response = await http.get<ApiResponse<MessageTemplate>>(`/whatsapp/templates/${templateId}`)
    return response.data.data
  },

  async createTemplate(payload: CreateTemplatePayload): Promise<{ id: string; status: string; category: string }> {
    const response = await http.post<ApiResponse<{ id: string; status: string; category: string }>>('/whatsapp/templates', payload)
    return response.data.data
  },

  async updateTemplate(templateId: string, payload: UpdateTemplatePayload): Promise<Record<string, unknown>> {
    const response = await http.patch<ApiResponse<Record<string, unknown>>>(`/whatsapp/templates/${templateId}`, payload)
    return response.data.data
  },

  async sendTemplate(conversationId: number, payload: { template_name: string; language: string; variables: string[] }): Promise<WhatsAppMessage> {
    const response = await http.post<ApiResponse<WhatsAppMessage>>(`/whatsapp/conversations/${conversationId}/template`, payload)
    return response.data.data
  },

  async getWindowStatus(conversationId: number): Promise<{ window_open: boolean; window_expires_at: string | null; remaining_seconds: number | null }> {
    const response = await http.get<ApiResponse<{ window_open: boolean; window_expires_at: string | null; remaining_seconds: number | null }>>(`/whatsapp/conversations/${conversationId}/window`)
    return response.data.data
  },

  async deleteTemplate(params: { name?: string; hsm_id?: string; hsm_ids?: string }): Promise<void> {
    await http.delete('/whatsapp/templates', { params })
  },
}

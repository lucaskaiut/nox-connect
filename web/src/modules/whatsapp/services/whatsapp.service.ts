import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { KanbanColumn, KanbanStage, WhatsAppConfig, WhatsAppConversation, WhatsAppMessage, WhatsAppNote, WhatsAppTag } from '@/shared/types/models'

export interface WhatsAppConfigPayload {
  name: string
  waba_id: string
  phone_number_id: string
  access_token: string
  verify_token: string
}

export interface ConversationFilters {
  status?: string
  assigned_to?: string
  stage_id?: number
  tag_id?: number
  search?: string
  unassigned?: boolean
  per_page?: number
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

export const whatsappService = {
  async listConfigs(): Promise<WhatsAppConfig[]> {
    const response = await http.get<ApiResponse<WhatsAppConfig[]>>('/whatsapp-configs')
    return response.data.data
  },

  async createConfig(payload: WhatsAppConfigPayload): Promise<WhatsAppConfig> {
    const response = await http.post<ApiResponse<WhatsAppConfig>>('/whatsapp-configs', payload)
    return response.data.data
  },

  async getConfig(id: number): Promise<WhatsAppConfig> {
    const response = await http.get<ApiResponse<WhatsAppConfig>>(`/whatsapp-configs/${id}`)
    return response.data.data
  },

  async updateConfig(id: number, payload: Partial<WhatsAppConfigPayload>): Promise<WhatsAppConfig> {
    const response = await http.patch<ApiResponse<WhatsAppConfig>>(`/whatsapp-configs/${id}`, payload)
    return response.data.data
  },

  async deleteConfig(id: number): Promise<void> {
    await http.delete(`/whatsapp-configs/${id}`)
  },

  async testConnection(id: number): Promise<{ message: string }> {
    const response = await http.post<ApiResponse<null>>(`/whatsapp-configs/${id}/test-connection`)
    return { message: response.data.message ?? '' }
  },

  async toggleConfig(id: number): Promise<WhatsAppConfig> {
    const response = await http.post<ApiResponse<WhatsAppConfig>>(`/whatsapp-configs/${id}/toggle`)
    return response.data.data
  },

  async listConversations(filters: ConversationFilters): Promise<PaginatedResponse<WhatsAppConversation>> {
    const response = await http.get<PaginatedResponse<WhatsAppConversation>>('/whatsapp/conversations', { params: filters })
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

  async getWebhookLogs(configId: number): Promise<WebhookLogEntry[]> {
    const response = await http.get<ApiResponse<WebhookLogEntry[]>>(`/whatsapp-configs/${configId}/webhook-logs`)
    return response.data.data
  },
}

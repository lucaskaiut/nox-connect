import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { whatsappService, type ConversationFilters, type WhatsAppConfigPayload } from '../services/whatsapp.service'

export function useWebhookLogsQuery(configId: number) {
  return useQuery({
    queryKey: queryKeys.whatsapp.configs.webhookLogs(configId),
    queryFn: () => whatsappService.getWebhookLogs(configId),
    enabled: configId > 0,
  })
}

export function useWhatsAppConfigsQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.configs.list(),
    queryFn: whatsappService.listConfigs,
  })
}

export function useWhatsAppConfigQuery(id: number) {
  return useQuery({
    queryKey: queryKeys.whatsapp.configs.detail(id),
    queryFn: () => whatsappService.getConfig(id),
    enabled: id > 0,
  })
}

export function useCreateWhatsAppConfig() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: WhatsAppConfigPayload) => whatsappService.createConfig(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.configs.all })
      toast.success('Configuração criada com sucesso.')
    },
  })
}

export function useUpdateWhatsAppConfig() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: WhatsAppConfigPayload & { id: number }) =>
      whatsappService.updateConfig(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.configs.all })
      toast.success('Configuração atualizada com sucesso.')
    },
  })
}

export function useDeleteWhatsAppConfig() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.deleteConfig(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.configs.all })
      toast.success('Configuração removida com sucesso.')
    },
  })
}

export function useTestConnection() {
  return useMutation({
    mutationFn: (id: number) => whatsappService.testConnection(id),
  })
}

export function useToggleConfig() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.toggleConfig(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.configs.all })
      toast.success('Status da conexão alterado.')
    },
  })
}

export function useConversationsQuery(filters: ConversationFilters) {
  return useQuery({
    queryKey: queryKeys.whatsapp.conversations.list(filters),
    queryFn: () => whatsappService.listConversations(filters),
  })
}

export function useConversationQuery(id: number) {
  return useQuery({
    queryKey: queryKeys.whatsapp.conversations.detail(id),
    queryFn: () => whatsappService.getConversation(id),
    enabled: id > 0,
  })
}

export function useSendMessage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, content }: { id: number; content: string }) =>
      whatsappService.sendMessage(id, content),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
    },
  })
}

export function useAssignConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, userId }: { id: number; userId: string }) =>
      whatsappService.assignConversation(id, userId),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Atendimento atribuído.')
    },
  })
}

export function useTransferConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, userId }: { id: number; userId: string }) =>
      whatsappService.transferConversation(id, userId),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Atendimento transferido.')
    },
  })
}

export function useRemoveAssignment() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.removeAssignment(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Responsável removido.')
    },
  })
}

export function useCloseConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.closeConversation(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Conversa finalizada.')
    },
  })
}

export function useReopenConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.reopenConversation(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Conversa reaberta.')
    },
  })
}

export function useAddNote() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, content }: { id: number; content: string }) =>
      whatsappService.addNote(id, content),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.id) })
      toast.success('Nota adicionada.')
    },
  })
}

export function useSyncConversationTags() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, tagIds }: { id: number; tagIds: number[] }) =>
      whatsappService.syncConversationTags(id, tagIds),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      toast.success('Tags atualizadas.')
    },
  })
}

export function useConversationStatsQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.conversations.stats(),
    queryFn: whatsappService.getConversationStats,
  })
}

export function useTagsQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.tags.list(),
    queryFn: whatsappService.listTags,
  })
}

export function useCreateTag() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: { name: string; color?: string }) => whatsappService.createTag(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.tags.all })
      toast.success('Tag criada com sucesso.')
    },
  })
}

export function useUpdateTag() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number; name?: string; color?: string }) =>
      whatsappService.updateTag(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.tags.all })
      toast.success('Tag atualizada com sucesso.')
    },
  })
}

export function useDeleteTag() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.deleteTag(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.tags.all })
      toast.success('Tag removida com sucesso.')
    },
  })
}

export function useKanbanBoardQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.kanban.board(),
    queryFn: whatsappService.getKanbanBoard,
  })
}

export function useMoveConversationStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ conversationId, stageId }: { conversationId: number; stageId: number | null }) =>
      whatsappService.moveConversationStage(conversationId, stageId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
    },
  })
}

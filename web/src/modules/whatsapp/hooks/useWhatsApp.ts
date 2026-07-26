import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  whatsappService,
  type ConversationFilters,
  type CreateTemplatePayload,
  type TemplateParams,
  type UpdateTemplatePayload,
  type WhatsAppConnectPayload,
} from '../services/whatsapp.service'

export function useWhatsAppConnectionQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.connection.detail(),
    queryFn: whatsappService.getConnection,
  })
}

export function useWebhookLogsQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.connection.webhookLogs(),
    queryFn: whatsappService.getWebhookLogs,
  })
}

export function useConnectWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: WhatsAppConnectPayload) => whatsappService.connect(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.connection.all })
      toast.success('Conexão estabelecida com sucesso.')
    },
  })
}

export function useDisconnectWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => whatsappService.disconnect(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.connection.all })
      toast.success('Conexão removida.')
    },
  })
}

export function useTestWhatsAppConnection() {
  return useMutation({
    mutationFn: () => whatsappService.testConnection(),
  })
}

export function useConversationsQuery(filters: ConversationFilters) {
  const { cursor: _, ...queryKeyFilters } = filters

  return useInfiniteQuery({
    queryKey: queryKeys.whatsapp.conversations.list(queryKeyFilters),
    queryFn: ({ pageParam }) =>
      whatsappService.listConversations({
        ...filters,
        cursor: pageParam ?? null,
      }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
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
    },
  })
}

export function useKanbanStagesQuery() {
  return useQuery({
    queryKey: queryKeys.whatsapp.kanban.stages(),
    queryFn: whatsappService.listKanbanStages,
  })
}

export function useCreateKanbanStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: { name: string; color?: string; sort_order?: number }) =>
      whatsappService.createKanbanStage(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
      toast.success('Etapa criada com sucesso.')
    },
  })
}

export function useUpdateKanbanStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number; name?: string; color?: string; sort_order?: number }) =>
      whatsappService.updateKanbanStage(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
      toast.success('Etapa atualizada com sucesso.')
    },
  })
}

export function useDeleteKanbanStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => whatsappService.deleteKanbanStage(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
      toast.success('Etapa removida com sucesso.')
    },
  })
}

export function useSeedDefaultStages() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => whatsappService.seedDefaultStages(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
      toast.success('Etapas padrão criadas com sucesso.')
    },
  })
}

export function useTemplatesQuery(params?: TemplateParams) {
  return useQuery({
    queryKey: queryKeys.whatsapp.templates.list(params),
    queryFn: () => whatsappService.listTemplates(params),
  })
}

export function useTemplateQuery(templateId: string) {
  return useQuery({
    queryKey: queryKeys.whatsapp.templates.detail(templateId),
    queryFn: () => whatsappService.getTemplate(templateId),
    enabled: templateId.length > 0,
  })
}

export function useCreateTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateTemplatePayload) => whatsappService.createTemplate(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.templates.all })
      toast.success('Template criado com sucesso.')
    },
    onError: (error: { message?: string }) => {
      toast.error(error?.message ?? 'Erro ao criar template.')
    },
  })
}

export function useUpdateTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ templateId, ...payload }: UpdateTemplatePayload & { templateId: string }) =>
      whatsappService.updateTemplate(templateId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.templates.all })
      toast.success('Template atualizado com sucesso.')
    },
    onError: (error: { message?: string }) => {
      toast.error(error?.message ?? 'Erro ao atualizar template.')
    },
  })
}

export function useDeleteTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (params: { name?: string; hsm_id?: string; hsm_ids?: string }) =>
      whatsappService.deleteTemplate(params),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.templates.all })
      toast.success('Template(s) removido(s) com sucesso.')
    },
    onError: (error: { message?: string }) => {
      toast.error(error?.message ?? 'Erro ao remover template(s).')
    },
  })
}

export function useSendTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ conversationId, ...payload }: { conversationId: number; template_name: string; language: string; variables: string[] }) =>
      whatsappService.sendTemplate(conversationId, payload),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.detail(variables.conversationId) })
      toast.success('Template enviado com sucesso.')
    },
    onError: (error: { message?: string }) => {
      toast.error(error?.message ?? 'Erro ao enviar template.')
    },
  })
}

export function useWindowStatus(conversationId: number) {
  return useQuery({
    queryKey: [...queryKeys.whatsapp.conversations.detail(conversationId), 'window'] as const,
    queryFn: () => whatsappService.getWindowStatus(conversationId),
    enabled: conversationId > 0,
    refetchInterval: 30_000,
  })
}

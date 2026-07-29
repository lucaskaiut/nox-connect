import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { InfiniteData } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import { sessionQueryOptions } from '@/modules/auth/services/auth.service'
import { useSessionStore } from '@/shared/stores/session.store'
import type { CursorPaginatedResponse } from '@/shared/types/api'
import type { WhatsAppConversation, WhatsAppMessage } from '@/shared/types/models'
import {
  whatsappService,
  type ConversationFilters,
  type CreateTemplatePayload,
  type TemplateParams,
  type UpdateTemplatePayload,
  type WhatsAppConnectPayload,
} from '../services/whatsapp.service'

type MessagesInfiniteData = InfiniteData<CursorPaginatedResponse<WhatsAppMessage>, string | null>

function prependMessageToCache(
  data: MessagesInfiniteData | undefined,
  message: WhatsAppMessage,
): MessagesInfiniteData | undefined {
  if (!data || data.pages.length === 0) {
    return {
      pages: [
        {
          success: true,
          message: null,
          data: [message],
          meta: {
            path: '',
            per_page: 50,
            next_cursor: null,
            prev_cursor: null,
            next_page_url: null,
            prev_page_url: null,
          },
        },
      ],
      pageParams: [null],
    }
  }

  const [firstPage, ...rest] = data.pages
  const exists = firstPage.data.some((item) => item.id === message.id)
  if (exists) {
    return {
      ...data,
      pages: [
        {
          ...firstPage,
          data: firstPage.data.map((item) => (item.id === message.id ? message : item)),
        },
        ...rest,
      ],
    }
  }

  return {
    ...data,
    pages: [{ ...firstPage, data: [message, ...firstPage.data] }, ...rest],
  }
}

function replaceMessageInCache(
  data: MessagesInfiniteData | undefined,
  optimisticId: number,
  next: WhatsAppMessage,
): MessagesInfiniteData | undefined {
  if (!data) return data

  return {
    ...data,
    pages: data.pages.map((page) => {
      const hasOptimistic = page.data.some((message) => message.id === optimisticId)
      if (hasOptimistic) {
        return {
          ...page,
          data: page.data.map((message) => (message.id === optimisticId ? next : message)),
        }
      }

      if (page.data.some((message) => message.id === next.id)) {
        return {
          ...page,
          data: page.data.map((message) => (message.id === next.id ? next : message)),
        }
      }

      return page
    }),
  }
}

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

async function refreshSession(queryClient: ReturnType<typeof useQueryClient>) {
  await queryClient.invalidateQueries({ queryKey: queryKeys.session })
  const session = await queryClient.fetchQuery(sessionQueryOptions)
  useSessionStore.getState().setSession(session)
}

export function useConnectWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: WhatsAppConnectPayload) => whatsappService.connect(payload),
    onSuccess: async () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.connection.all })
      await refreshSession(queryClient)
      toast.success('Conexão estabelecida com sucesso.')
    },
  })
}

export function useDisconnectWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => whatsappService.disconnect(),
    onSuccess: async () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.connection.all })
      await refreshSession(queryClient)
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

export function useMessagesQuery(conversationId: number, perPage = 50) {
  return useInfiniteQuery({
    queryKey: queryKeys.whatsapp.conversations.messages(conversationId),
    queryFn: ({ pageParam }) =>
      whatsappService.listMessages(conversationId, {
        cursor: pageParam ?? null,
        per_page: perPage,
      }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
    enabled: conversationId > 0,
  })
}

export type SendMessageVariables = {
  id: number
  content: string
  /** Reuses the same bubble when retrying a failed send */
  optimisticId?: number
}

let optimisticSeq = 0

function isWhatsAppMessage(value: unknown): value is WhatsAppMessage {
  return (
    typeof value === 'object' &&
    value !== null &&
    'id' in value &&
    typeof (value as WhatsAppMessage).id === 'number' &&
    'conversation_id' in value &&
    'direction' in value &&
    'status' in value
  )
}

export function useSendMessage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, content }: SendMessageVariables) =>
      whatsappService.sendMessage(id, content),

    onMutate: async ({ id, content, optimisticId }) => {
      const messagesKey = queryKeys.whatsapp.conversations.messages(id)
      const detailKey = queryKeys.whatsapp.conversations.detail(id)
      await queryClient.cancelQueries({ queryKey: messagesKey })

      const previousMessages = queryClient.getQueryData<MessagesInfiniteData>(messagesKey)
      const previousDetail = queryClient.getQueryData<WhatsAppConversation>(detailKey)
      const tempId = optimisticId ?? -(Date.now() * 1000 + (optimisticSeq++ % 1000))
      const senderName = useSessionStore.getState().user?.name ?? null
      const createdAt = new Date().toISOString()

      if (optimisticId != null) {
        queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) =>
          replaceMessageInCache(old, optimisticId, {
            ...(old?.pages.flatMap((page) => page.data).find((m) => m.id === optimisticId) ?? {
              id: optimisticId,
              conversation_id: id,
              direction: 'outbound',
              message_type: 'text',
              content,
              media: null,
              external_message_id: null,
              status: 'pending',
              metadata: null,
              sender_name: senderName,
              delivered_at: null,
              read_at: null,
              created_at: createdAt,
            }),
            status: 'pending',
            content,
          }),
        )
      } else {
        const optimisticMessage: WhatsAppMessage = {
          id: tempId,
          conversation_id: id,
          direction: 'outbound',
          message_type: 'text',
          content,
          media: null,
          external_message_id: null,
          status: 'pending',
          metadata: null,
          sender_name: senderName,
          delivered_at: null,
          read_at: null,
          created_at: createdAt,
        }

        queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) =>
          prependMessageToCache(old, optimisticMessage),
        )
      }

      queryClient.setQueryData<WhatsAppConversation>(detailKey, (old) =>
        old
          ? {
              ...old,
              last_message_preview: content,
              last_message_at: createdAt,
            }
          : old,
      )

      return { previousMessages, previousDetail, tempId, conversationId: id }
    },

    onSuccess: (data, _variables, context) => {
      if (!context) return

      const messagesKey = queryKeys.whatsapp.conversations.messages(context.conversationId)
      const detailKey = queryKeys.whatsapp.conversations.detail(context.conversationId)

      queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) =>
        replaceMessageInCache(old, context.tempId, data),
      )

      queryClient.setQueryData<WhatsAppConversation>(detailKey, (old) =>
        old
          ? {
              ...old,
              last_message_preview: data.content,
              last_message_at: data.created_at,
            }
          : old,
      )

      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
    },

    onError: (error, _variables, context) => {
      if (!context) return

      const messagesKey = queryKeys.whatsapp.conversations.messages(context.conversationId)
      const serverMessage = isApiError(error) && isWhatsAppMessage(error.data) ? error.data : null

      if (serverMessage) {
        queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) =>
          replaceMessageInCache(old, context.tempId, {
            ...serverMessage,
            status: serverMessage.status || 'failed',
          }),
        )
        queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
        return
      }

      queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) => {
        if (!old) return old
        return {
          ...old,
          pages: old.pages.map((page) => ({
            ...page,
            data: page.data.map((message) =>
              message.id === context.tempId ? { ...message, status: 'failed' } : message,
            ),
          })),
        }
      })
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

export function useKanbanStageConversationsQuery(stageId: number, perPage = 20) {
  return useInfiniteQuery({
    queryKey: queryKeys.whatsapp.kanban.stageConversations(stageId),
    queryFn: ({ pageParam }) =>
      whatsappService.listKanbanStageConversations(stageId, {
        cursor: pageParam ?? null,
        per_page: perPage,
      }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
    enabled: stageId > 0,
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
    queryKey: queryKeys.whatsapp.templates.list(params as Record<string, unknown> | undefined),
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

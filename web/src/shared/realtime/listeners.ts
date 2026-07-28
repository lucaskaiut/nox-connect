import type { InfiniteData, QueryClient } from '@tanstack/react-query'
import type {
  WhatsAppConversation,
  WhatsAppMessage,
  WhatsAppNote,
  WhatsAppTag,
} from '@/shared/types/models'
import type { CursorPaginatedResponse } from '@/shared/types/api'
import { queryKeys } from '@/shared/constants/query-keys'

// ---------------------------------------------------------------------------
// Event payload types
// ---------------------------------------------------------------------------

interface MessageReceivedPayload {
  conversation_id: number
  message: WhatsAppMessage
}

interface MessageSentPayload {
  conversation_id: number
  message: WhatsAppMessage
}

interface MessageStatusPayload {
  conversation_id: number
  external_message_id: string
  status: string
  delivered_at?: string | null
  read_at?: string | null
}

interface ConversationAssignmentPayload {
  conversation_id: number
  conversation?: WhatsAppConversation
}

interface ConversationClosedPayload {
  conversation_id: number
}

interface TagPayload {
  conversation_id: number
  tag: WhatsAppTag
}

interface InternalNoteCreatedPayload {
  conversation_id: number
  note: WhatsAppNote
}

type MessagesInfiniteData = InfiniteData<CursorPaginatedResponse<WhatsAppMessage>, string | null>

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function updateConversationInCache(
  queryClient: QueryClient,
  conversationId: number,
  updater: (conversation: WhatsAppConversation) => WhatsAppConversation,
) {
  const queryKey = queryKeys.whatsapp.conversations.detail(conversationId)
  queryClient.setQueryData<WhatsAppConversation>(queryKey, (old) => {
    if (!old) return old
    return updater(old)
  })
}

function prependMessage(
  queryClient: QueryClient,
  conversationId: number,
  message: WhatsAppMessage,
) {
  const messagesKey = queryKeys.whatsapp.conversations.messages(conversationId)

  queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) => {
    if (!old || old.pages.length === 0) {
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

    const [firstPage, ...rest] = old.pages
    if (firstPage.data.some((item) => item.id === message.id)) {
      return {
        ...old,
        pages: [
          {
            ...firstPage,
            data: firstPage.data.map((item) => (item.id === message.id ? message : item)),
          },
          ...rest,
        ],
      }
    }

    const pendingIndex = firstPage.data.findIndex(
      (msg) =>
        msg.id < 0 &&
        msg.status === 'pending' &&
        msg.direction === 'outbound' &&
        msg.content === message.content,
    )

    if (pendingIndex >= 0) {
      return {
        ...old,
        pages: [
          {
            ...firstPage,
            data: firstPage.data.map((msg, index) => (index === pendingIndex ? message : msg)),
          },
          ...rest,
        ],
      }
    }

    return {
      ...old,
      pages: [{ ...firstPage, data: [message, ...firstPage.data] }, ...rest],
    }
  })
}

function patchMessageByExternalId(
  queryClient: QueryClient,
  conversationId: number,
  externalMessageId: string,
  patch: Partial<WhatsAppMessage>,
) {
  const messagesKey = queryKeys.whatsapp.conversations.messages(conversationId)

  queryClient.setQueryData<MessagesInfiniteData>(messagesKey, (old) => {
    if (!old) return old

    return {
      ...old,
      pages: old.pages.map((page) => ({
        ...page,
        data: page.data.map((msg) =>
          msg.external_message_id === externalMessageId ? { ...msg, ...patch } : msg,
        ),
      })),
    }
  })
}

// ---------------------------------------------------------------------------
// Event handlers
// ---------------------------------------------------------------------------

export function handleMessageReceived(queryClient: QueryClient, payload: MessageReceivedPayload) {
  const { conversation_id: conversationId, message } = payload
  const windowExpiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()

  prependMessage(queryClient, conversationId, message)

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    last_message_preview: message.content,
    last_message_at: message.created_at,
    last_customer_message_at: message.created_at,
    window_expires_at: windowExpiresAt,
    is_window_open: true,
    is_unread: true,
  }))

  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
}

export function handleMessageSent(queryClient: QueryClient, payload: MessageSentPayload) {
  const { conversation_id: conversationId, message } = payload

  prependMessage(queryClient, conversationId, message)

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    last_message_preview: message.content,
    last_message_at: message.created_at,
  }))
}

export function handleMessageDelivered(queryClient: QueryClient, payload: MessageStatusPayload) {
  const { conversation_id: conversationId, external_message_id: externalMessageId, status, delivered_at } =
    payload

  patchMessageByExternalId(queryClient, conversationId, externalMessageId, {
    status,
    delivered_at: delivered_at ?? undefined,
  })
}

export function handleMessageRead(queryClient: QueryClient, payload: MessageStatusPayload) {
  const { conversation_id: conversationId, external_message_id: externalMessageId, status, read_at } =
    payload

  patchMessageByExternalId(queryClient, conversationId, externalMessageId, {
    status,
    read_at: read_at ?? undefined,
  })
}

export function handleConversationAssigned(
  queryClient: QueryClient,
  payload: ConversationAssignmentPayload,
) {
  const { conversation_id: conversationId, conversation } = payload

  if (conversation) {
    queryClient.setQueryData(queryKeys.whatsapp.conversations.detail(conversationId), conversation)
  }

  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
}

export function handleConversationTransferred(
  queryClient: QueryClient,
  payload: ConversationAssignmentPayload,
) {
  const { conversation_id: conversationId, conversation } = payload

  if (conversation) {
    queryClient.setQueryData(queryKeys.whatsapp.conversations.detail(conversationId), conversation)
  }

  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
}

export function handleConversationClosed(
  queryClient: QueryClient,
  payload: ConversationClosedPayload,
) {
  const { conversation_id: conversationId } = payload

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    status: 'closed',
  }))

  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
}

export function handleKanbanCardMoved(queryClient: QueryClient) {
  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
}

export function handleKanbanCardCreated(queryClient: QueryClient) {
  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.kanban.all })
}

export function handleTagAttached(queryClient: QueryClient, payload: TagPayload) {
  const { conversation_id: conversationId, tag } = payload

  updateConversationInCache(queryClient, conversationId, (old) => {
    const hasTag = old.tags.some((t) => t.id === tag.id)
    if (hasTag) return old

    return { ...old, tags: [...old.tags, tag] }
  })
}

export function handleTagDetached(queryClient: QueryClient, payload: TagPayload) {
  const { conversation_id: conversationId, tag } = payload

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    tags: old.tags.filter((t) => t.id !== tag.id),
  }))
}

export function handleInternalNoteCreated(
  queryClient: QueryClient,
  payload: InternalNoteCreatedPayload,
) {
  const { conversation_id: conversationId, note } = payload

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    notes: old.notes ? [...old.notes, note] : old.notes,
  }))
}

// ---------------------------------------------------------------------------
// Channel subscriptions
// ---------------------------------------------------------------------------

interface EchoChannel {
  listen(event: string, callback: (data: unknown) => void): unknown
}

export function setupTenantChannelListeners(channel: EchoChannel, queryClient: QueryClient) {
  // Leading "." is required when the backend uses broadcastAs()
  channel.listen('.message.received', (payload) =>
    handleMessageReceived(queryClient, payload as MessageReceivedPayload),
  )

  channel.listen('.conversation.assigned', (payload) =>
    handleConversationAssigned(queryClient, payload as ConversationAssignmentPayload),
  )

  channel.listen('.conversation.transferred', (payload) =>
    handleConversationTransferred(queryClient, payload as ConversationAssignmentPayload),
  )

  channel.listen('.conversation.closed', (payload) =>
    handleConversationClosed(queryClient, payload as ConversationClosedPayload),
  )

  channel.listen('.kanban.card.moved', () => {
    handleKanbanCardMoved(queryClient)
  })

  channel.listen('.kanban.card.created', () => {
    handleKanbanCardCreated(queryClient)
  })
}

export function setupConversationChannelListeners(channel: EchoChannel, queryClient: QueryClient) {
  channel.listen('.message.received', (payload) =>
    handleMessageReceived(queryClient, payload as MessageReceivedPayload),
  )

  channel.listen('.message.sent', (payload) =>
    handleMessageSent(queryClient, payload as MessageSentPayload),
  )

  channel.listen('.message.delivered', (payload) =>
    handleMessageDelivered(queryClient, payload as MessageStatusPayload),
  )

  channel.listen('.message.read', (payload) =>
    handleMessageRead(queryClient, payload as MessageStatusPayload),
  )

  channel.listen('.tag.attached', (payload) =>
    handleTagAttached(queryClient, payload as TagPayload),
  )

  channel.listen('.tag.detached', (payload) =>
    handleTagDetached(queryClient, payload as TagPayload),
  )

  channel.listen('.internal.note.created', (payload) =>
    handleInternalNoteCreated(queryClient, payload as InternalNoteCreatedPayload),
  )
}

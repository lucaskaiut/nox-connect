import type { QueryClient } from '@tanstack/react-query'
import type {
  WhatsAppConversation,
  WhatsAppMessage,
  WhatsAppNote,
  WhatsAppTag,
} from '@/shared/types/models'
import { queryKeys } from '@/shared/constants/query-keys'

// ---------------------------------------------------------------------------
// Event payload types
// ---------------------------------------------------------------------------

interface MessageReceivedPayload {
  conversation: WhatsAppConversation
  message: WhatsAppMessage
}

interface MessageSentPayload {
  conversation_id: number
  message: WhatsAppMessage
}

interface MessageStatusPayload {
  conversation_id: number
  message_id: number
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

// ---------------------------------------------------------------------------
// Event handlers
// ---------------------------------------------------------------------------

export function handleMessageReceived(queryClient: QueryClient, payload: MessageReceivedPayload) {
  const { conversation, message } = payload

  updateConversationInCache(queryClient, conversation.id, (old) => ({
    ...old,
    messages: old.messages ? [message, ...old.messages] : old.messages,
    last_message_preview: message.content,
    last_message_at: message.created_at,
  }))

  queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.conversations.all })
}

export function handleMessageSent(queryClient: QueryClient, payload: MessageSentPayload) {
  const { conversation_id: conversationId, message } = payload

  updateConversationInCache(queryClient, conversationId, (old) => ({
    ...old,
    messages: old.messages ? [...old.messages, message] : old.messages,
    last_message_preview: message.content,
    last_message_at: message.created_at,
  }))
}

export function handleMessageDelivered(queryClient: QueryClient, payload: MessageStatusPayload) {
  const { conversation_id: conversationId, message_id: messageId, status, delivered_at } = payload

  updateConversationInCache(queryClient, conversationId, (old) => {
    if (!old.messages) return old

    return {
      ...old,
      messages: old.messages.map((msg) =>
        msg.id === messageId ? { ...msg, status, delivered_at: delivered_at ?? msg.delivered_at } : msg,
      ),
    }
  })
}

export function handleMessageRead(queryClient: QueryClient, payload: MessageStatusPayload) {
  const { conversation_id: conversationId, message_id: messageId, status, read_at } = payload

  updateConversationInCache(queryClient, conversationId, (old) => {
    if (!old.messages) return old

    return {
      ...old,
      messages: old.messages.map((msg) =>
        msg.id === messageId ? { ...msg, status, read_at: read_at ?? msg.read_at } : msg,
      ),
    }
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
  channel.listen('message.received', (payload) =>
    handleMessageReceived(queryClient, payload as MessageReceivedPayload),
  )

  channel.listen('conversation.assigned', (payload) =>
    handleConversationAssigned(queryClient, payload as ConversationAssignmentPayload),
  )

  channel.listen('conversation.transferred', (payload) =>
    handleConversationTransferred(queryClient, payload as ConversationAssignmentPayload),
  )

  channel.listen('conversation.closed', (payload) =>
    handleConversationClosed(queryClient, payload as ConversationClosedPayload),
  )

  channel.listen('kanban.card.moved', () => {
    handleKanbanCardMoved(queryClient)
  })

  channel.listen('kanban.card.created', () => {
    handleKanbanCardCreated(queryClient)
  })
}

export function setupConversationChannelListeners(channel: EchoChannel, queryClient: QueryClient) {
  channel.listen('message.sent', (payload) =>
    handleMessageSent(queryClient, payload as MessageSentPayload),
  )

  channel.listen('message.delivered', (payload) =>
    handleMessageDelivered(queryClient, payload as MessageStatusPayload),
  )

  channel.listen('message.read', (payload) =>
    handleMessageRead(queryClient, payload as MessageStatusPayload),
  )

  channel.listen('tag.attached', (payload) =>
    handleTagAttached(queryClient, payload as TagPayload),
  )

  channel.listen('tag.detached', (payload) =>
    handleTagDetached(queryClient, payload as TagPayload),
  )

  channel.listen('internal-note.created', (payload) =>
    handleInternalNoteCreated(queryClient, payload as InternalNoteCreatedPayload),
  )
}

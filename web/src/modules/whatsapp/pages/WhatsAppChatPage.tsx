import { useCallback, useEffect, useRef, useState } from 'react'
import {
  Send,
  UserPlus,
  UserMinus,
  Tag,
  MessageSquarePlus,
  X,
  Lock,
  Unlock,
  ArrowLeft,
  MessageCircle,
} from 'lucide-react'
import {
  Avatar,
  Badge,
  Button,
  EmptyState,
  FilterBar,
  Textarea,
  Modal,
  ConfirmDialog,
  SearchInput,
  Skeleton,
  Loading,
  type SelectOption,
} from '@/shared/design-system'
import { Select } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useSessionStore } from '@/shared/stores/session.store'
import { formatRelative } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import {
  useConversationsQuery,
  useConversationQuery,
  useMessagesQuery,
  useSendMessage,
  useAssignConversation,
  useTransferConversation,
  useRemoveAssignment,
  useCloseConversation,
  useReopenConversation,
  useAddNote,
  useSyncConversationTags,
} from '../hooks/useWhatsApp'
import { useUsersQuery } from '@/modules/users/hooks/useUsers'
import { useConversationChannel } from '@/shared/realtime/useRealtime'
import {
  MessageBubble,
  NoteCard,
  TagManagementModal,
} from '../components/ChatComponents'
import { TemplateModal } from '../components/TemplateModal'
import type { ConversationFilters } from '../services/whatsapp.service'
import type { WhatsAppMessage } from '@/shared/types/models'

const PER_PAGE = 20

type FilterTab = 'all' | 'unassigned' | 'mine' | 'closed'

function ConversationSkeleton() {
  return (
    <div className="flex items-center gap-3 px-5 py-3.5 shadow-[inset_0_1px_0_var(--app-surface-2)]">
      <Skeleton className="size-9 shrink-0 rounded-full" />
      <div className="min-w-0 flex-1 space-y-1.5">
        <Skeleton className="h-4 w-32" />
        <Skeleton className="h-3.5 w-48" />
      </div>
      <div className="hidden shrink-0 items-center gap-3 sm:flex">
        <Skeleton className="h-5 w-20 rounded-full" />
        <Skeleton className="h-5 w-16 rounded-full" />
        <Skeleton className="h-4 w-14" />
      </div>
    </div>
  )
}

export default function WhatsAppChatPage() {
  const [search, setSearch] = useState('')
  const [activeTab, setActiveTab] = useState<FilterTab>('all')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const debouncedSearch = useDebounce(search)
  const loadMoreRef = useRef<HTMLDivElement>(null)

  const currentUser = useSessionStore((state) => state.user)

  const filters: ConversationFilters = {
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
  }

  if (activeTab === 'unassigned') {
    filters.unassigned = true
  } else if (activeTab === 'mine') {
    filters.assigned_to = currentUser?.id
  } else if (activeTab === 'closed') {
    filters.status = 'closed'
  }

  const conversationsQuery = useConversationsQuery(filters)
  const conversations = conversationsQuery.data?.pages.flatMap((p) => p.data) ?? []
  const showEmpty = !conversationsQuery.isPending && conversations.length === 0
  const { fetchNextPage, hasNextPage, isFetchingNextPage } = conversationsQuery

  useEffect(() => {
    const el = loadMoreRef.current
    if (!el) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          fetchNextPage()
        }
      },
      { threshold: 0.1 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage, isFetchingNextPage])

  const handleSearch = (value: string) => {
    setSearch(value)
  }

  const handleTabChange = (tab: FilterTab) => {
    setActiveTab(tab)
  }

  const tabs: Array<{ key: FilterTab; label: string }> = [
    { key: 'all', label: 'Todas' },
    { key: 'unassigned', label: 'Sem responsável' },
    { key: 'mine', label: 'Minhas' },
    { key: 'closed', label: 'Finalizadas' },
  ]

  return (
    <div className="flex h-[calc(100dvh-4.5rem)] -mx-6 -mt-2 overflow-hidden lg:-mx-10">
        <div className="flex w-full max-w-[1440px] mx-auto">
          <div className="flex w-96 shrink-0 flex-col border-r border-surface-2 bg-surface">
            <div className="shrink-0 space-y-3 p-4">
              <FilterBar>
                <div className="flex flex-wrap items-center gap-1.5">
                  {tabs.map((tab) => (
                    <Button
                      key={tab.key}
                      variant={activeTab === tab.key ? 'secondary' : 'ghost'}
                      size="sm"
                      onClick={() => handleTabChange(tab.key)}
                    >
                      {tab.label}
                    </Button>
                  ))}
                </div>
                <SearchInput
                  placeholder="Buscar..."
                  aria-label="Buscar conversas"
                  value={search}
                  onChange={(event) => handleSearch(event.target.value)}
                />
              </FilterBar>
            </div>

            <div className="flex-1 overflow-y-auto">
              {conversationsQuery.isPending ? (
                Array.from({ length: 8 }).map((_, i) => <ConversationSkeleton key={i} />)
              ) : showEmpty ? (
                <div className="p-8">
                  <EmptyState
                    icon={MessageCircle}
                    title="Nenhuma conversa"
                    description={
                      debouncedSearch || activeTab !== 'all'
                        ? 'Tente ajustar os filtros.'
                        : 'As conversas aparecerão aqui.'
                    }
                  />
                </div>
              ) : (
                conversations.map((conv) => (
                  <button
                    key={conv.id}
                    type="button"
                    onClick={() => setSelectedId(conv.id)}
                    className={cn(
                      'flex w-full items-center gap-3 px-5 py-3.5 text-left shadow-[inset_0_1px_0_var(--app-surface-2)] transition-colors hover:bg-surface-2/40',
                      conv.is_unread && 'bg-primary-soft/30',
                      selectedId === conv.id && 'bg-surface-2',
                    )}
                  >
                    <div className="relative shrink-0">
                      <Avatar name={conv.contact.display_name || conv.contact.profile_name || conv.contact.external_contact_id} />
                      {conv.is_unread && (
                        <span className="absolute top-0 right-0 size-3 rounded-full border-2 border-surface-1 bg-primary" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <p className={cn('truncate text-sm', conv.is_unread ? 'font-semibold' : 'font-medium')}>
                          {conv.contact.display_name || conv.contact.profile_name || conv.contact.external_contact_id}
                        </p>
                      </div>
                      <div className="mt-0.5 flex items-center gap-2">
                        {conv.last_message_preview && (
                          <p className={cn('truncate text-[13px]', conv.is_unread ? 'font-medium text-foreground' : 'text-muted')}>
                            {conv.last_message_preview}
                          </p>
                        )}
                        {conv.last_message_at && (
                          <>
                            <span className="text-[13px] text-muted">&middot;</span>
                            <span className="shrink-0 text-[13px] text-muted">{formatRelative(conv.last_message_at)}</span>
                          </>
                        )}
                      </div>
                    </div>
                    <div className="hidden shrink-0 items-center gap-1.5 sm:flex">
                      {conv.current_assignment?.user ? (
                        <Badge variant="neutral">{conv.current_assignment.user.name}</Badge>
                      ) : (
                        <Badge>Sem</Badge>
                      )}
                      {conv.current_stage && <Badge variant="primary">{conv.current_stage.name}</Badge>}
                      {conv.tags?.length ? (
                        <Badge variant="neutral">
                          {conv.tags[0].name}
                          {conv.tags.length > 1 && ` +${conv.tags.length - 1}`}
                        </Badge>
                      ) : null}
                    </div>
                  </button>
                ))
              )}
            </div>

            <div ref={loadMoreRef} className="shrink-0">
              {isFetchingNextPage && (
                <div className="flex justify-center py-3">
                  <Loading />
                </div>
              )}
            </div>
          </div>

          <div className="flex flex-1 flex-col min-w-0">
            {selectedId ? (
              <ChatPanel
                conversationId={selectedId}
                onClose={() => setSelectedId(null)}
                onBack={() => setSelectedId(null)}
              />
            ) : (
              <div className="flex h-full items-center justify-center text-muted">
                <EmptyState
                  icon={MessageCircle}
                  title="Selecione uma conversa"
                  description="Escolha uma conversa na lista ao lado para começar."
                />
              </div>
            )}
          </div>
        </div>
      </div>
  )
}

function ChatPanel({
  conversationId,
  onBack,
}: {
  conversationId: number
  onClose: () => void
  onBack: () => void
}) {
  const currentUser = useSessionStore((state) => state.user)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const messagesTopRef = useRef<HTMLDivElement>(null)
  const messagesContainerRef = useRef<HTMLDivElement>(null)
  const prevScrollHeightRef = useRef<number | null>(null)

  const { data: conversation, isLoading } = useConversationQuery(conversationId)
  const messagesQuery = useMessagesQuery(conversationId)
  useConversationChannel(conversationId)
  const { data: usersData } = useUsersQuery({ per_page: 100 })

  const sendMessage = useSendMessage()
  const assignConversation = useAssignConversation()
  const transferConversation = useTransferConversation()
  const removeAssignment = useRemoveAssignment()
  const closeConversation = useCloseConversation()
  const reopenConversation = useReopenConversation()
  const addNote = useAddNote()
  const syncTags = useSyncConversationTags()

  const [messageText, setMessageText] = useState('')
  const [noteText, setNoteText] = useState('')
  const [showNotes, setShowNotes] = useState(false)
  const [showTagModal, setShowTagModal] = useState(false)
  const [showTransferModal, setShowTransferModal] = useState(false)
  const [selectedUserId, setSelectedUserId] = useState('')
  const [showCloseConfirm, setShowCloseConfirm] = useState(false)
  const [showTemplateModal, setShowTemplateModal] = useState(false)

  const isClosed = conversation?.status === 'closed'
  const windowOpen = conversation?.is_window_open ?? true
  const messages = [...(messagesQuery.data?.pages.flatMap((page) => page.data) ?? [])].reverse()
  const notes = conversation?.notes ?? []
  const assignedUser = conversation?.current_assignment?.user ?? null
  const selectedTagIds = conversation?.tags?.map((t) => t.id) ?? []

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages.length])

  useEffect(() => {
    if (conversation) {
      messagesEndRef.current?.scrollIntoView({ behavior: 'auto' })
    }
  }, [conversation?.id])

  useEffect(() => {
    const container = messagesContainerRef.current
    if (!container || prevScrollHeightRef.current == null) return
    container.scrollTop = container.scrollHeight - prevScrollHeightRef.current
    prevScrollHeightRef.current = null
  }, [messagesQuery.data?.pages.length])

  useEffect(() => {
    const sentinel = messagesTopRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver(
      (entries) => {
        if (
          entries[0]?.isIntersecting &&
          messagesQuery.hasNextPage &&
          !messagesQuery.isFetchingNextPage
        ) {
          const container = messagesContainerRef.current
          if (container) {
            prevScrollHeightRef.current = container.scrollHeight
          }
          void messagesQuery.fetchNextPage()
        }
      },
      { root: messagesContainerRef.current, rootMargin: '80px 0px 0px 0px' },
    )

    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [messagesQuery.hasNextPage, messagesQuery.isFetchingNextPage, messagesQuery.fetchNextPage])

  const handleSendMessage = () => {
    const text = messageText.trim()
    if (!text || isClosed) return
    sendMessage.mutate({ id: conversationId, content: text })
    setMessageText('')
  }

  const handleRetryMessage = (message: WhatsAppMessage) => {
    const content = message.content?.trim()
    if (!content || isClosed || message.status === 'pending') return
    sendMessage.mutate({
      id: conversationId,
      content,
      optimisticId: message.id,
    })
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSendMessage()
    }
  }

  const handleAssign = () => {
    if (!currentUser) return
    assignConversation.mutate({ id: conversationId, userId: currentUser.id })
  }

  const handleTransfer = () => {
    if (!selectedUserId) return
    transferConversation.mutate(
      { id: conversationId, userId: selectedUserId },
      { onSettled: () => setShowTransferModal(false) },
    )
  }

  const handleRemoveAssignment = () => {
    removeAssignment.mutate(conversationId)
  }

  const handleClose = () => {
    closeConversation.mutate(conversationId, {
      onSettled: () => setShowCloseConfirm(false),
      onSuccess: () => onBack(),
    })
  }

  const handleReopen = () => {
    reopenConversation.mutate(conversationId)
  }

  const handleAddNote = () => {
    const text = noteText.trim()
    if (!text) return
    addNote.mutate(
      { id: conversationId, content: text },
      { onSettled: () => setNoteText('') },
    )
  }

  const handleToggleTag = useCallback(
    (tagId: number) => {
      const next = selectedTagIds.includes(tagId)
        ? selectedTagIds.filter((t) => t !== tagId)
        : [...selectedTagIds, tagId]
      syncTags.mutate({ id: conversationId, tagIds: next })
    },
    [selectedTagIds, conversationId, syncTags],
  )

  const closeTagModal = useCallback(() => setShowTagModal(false), [])

  const userOptions: SelectOption[] =
    usersData?.data
      ?.filter((u) => u.id !== currentUser?.id)
      .map((u) => ({ value: u.id, label: u.name })) ?? []

  if (isLoading) {
    return (
      <div className="flex h-full items-center justify-center">
        <Loading />
      </div>
    )
  }

  if (!conversation) {
    return (
      <div className="flex h-full items-center justify-center text-muted">
        <EmptyState icon={MessageCircle} title="Conversa não encontrada" />
      </div>
    )
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex shrink-0 items-center gap-3 border-b border-surface-2 px-4 py-3">
        <button
          type="button"
          onClick={onBack}
          className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:hidden"
        >
          <ArrowLeft className="size-4.5" />
        </button>
        <Avatar name={conversation.contact.profile_name ?? conversation.contact.external_contact_id} size="sm" />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-foreground">
            {conversation.contact.profile_name ?? conversation.contact.external_contact_id}
          </p>
          <p className="truncate text-[12px] text-muted">{conversation.contact.external_contact_id}</p>
        </div>
        <div className="flex items-center gap-1">
          {isClosed ? (
            <Badge variant="neutral">Fechada</Badge>
          ) : !windowOpen ? (
            <Badge variant="warning">Janela encerrada</Badge>
          ) : (
            <Badge variant="success">Aberta</Badge>
          )}
        </div>
      </div>

      <div className="flex shrink-0 flex-wrap items-center gap-2 border-b border-surface-2 px-4 py-2">
        <div className="flex items-center gap-2">
          {assignedUser ? (
            <>
              <Avatar name={assignedUser.name} size="xs" />
              <span className="text-xs text-foreground">{assignedUser.name}</span>
              <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
                <Button variant="ghost" size="xs" onClick={() => setShowTransferModal(true)} title="Transferir">
                  <UserPlus className="size-3" />
                </Button>
                <Button
                  variant="ghost" size="xs" onClick={handleRemoveAssignment}
                  loading={removeAssignment.isPending} title="Remover responsável"
                >
                  <UserMinus className="size-3" />
                </Button>
              </Can>
            </>
          ) : (
            <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
              <Button variant="secondary" size="xs" onClick={handleAssign} loading={assignConversation.isPending}>
                <UserPlus className="size-3" /> Assumir
              </Button>
            </Can>
          )}
        </div>

        <div className="h-4 w-px bg-surface-3" />

        {conversation.current_stage && (
          <Badge variant="primary" style={{ backgroundColor: conversation.current_stage.color ?? undefined }}>
            {conversation.current_stage.name}
          </Badge>
        )}

        <div className="h-4 w-px bg-surface-3" />

        <div className="flex items-center gap-1">
          <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
            <Button variant="ghost" size="xs" onClick={() => setShowTagModal(true)}>
              <Tag className="size-3" /> Tags
            </Button>
          </Can>
          {conversation.tags?.map((tag) => (
            <Badge key={tag.id} style={{ backgroundColor: tag.color ?? undefined }} variant="neutral">
              {tag.name}
            </Badge>
          ))}
        </div>

        <div className="ml-auto flex items-center gap-1">
          <Button variant="ghost" size="xs" onClick={() => setShowNotes((v) => !v)}>
            <MessageSquarePlus className="size-3" />
            Notas {notes.length > 0 && `(${notes.length})`}
          </Button>
          <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
            {isClosed ? (
              <Button variant="secondary" size="xs" onClick={handleReopen} loading={reopenConversation.isPending}>
                <Unlock className="size-3" /> Reabrir
              </Button>
            ) : (
              <Button
                variant="ghost" size="xs"
                onClick={() => setShowCloseConfirm(true)}
                className="text-warning hover:bg-warning-soft hover:text-warning"
              >
                <Lock className="size-3" /> Fechar
              </Button>
            )}
          </Can>
        </div>
      </div>

      {showNotes && (
        <div className="shrink-0 border-b border-surface-2 bg-surface p-4">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-foreground">Notas internas</h3>
            <Button variant="ghost" size="xs" onClick={() => setShowNotes(false)}>
              <X className="size-4" />
            </Button>
          </div>
          {notes.length > 0 ? (
            <div className="mb-4 space-y-2 max-h-48 overflow-y-auto">
              {notes.map((note) => <NoteCard key={note.id} note={note} />)}
            </div>
          ) : (
            <p className="mb-4 text-sm text-muted">Nenhuma nota registrada.</p>
          )}
          <div className="flex gap-2">
            <Textarea
              value={noteText}
              onChange={(e) => setNoteText(e.target.value)}
              placeholder="Adicionar nota interna..."
              rows={2}
              className="min-h-0 resize-none"
            />
            <Button size="xs" onClick={handleAddNote} loading={addNote.isPending} disabled={!noteText.trim()}>
              Salvar
            </Button>
          </div>
        </div>
      )}

      <div ref={messagesContainerRef} className="flex-1 overflow-y-auto p-4 space-y-3">
        <div ref={messagesTopRef} className="h-px" />
        {messagesQuery.isFetchingNextPage && (
          <p className="py-2 text-center text-xs text-muted">Carregando mensagens anteriores...</p>
        )}
        {messages.length === 0 && !messagesQuery.isPending && (
          <div className="flex h-full items-center justify-center text-muted text-sm">
            Nenhuma mensagem nesta conversa.
          </div>
        )}
        {messages.map((message) => (
          <MessageBubble
            key={message.id}
            message={message}
            isOutbound={message.direction === 'outbound'}
            onRetry={handleRetryMessage}
          />
        ))}
        <div ref={messagesEndRef} />
      </div>

      <div className="shrink-0 flex items-end gap-2 border-t border-surface-2 p-3">
        {!windowOpen ? (
          <>
            <Textarea
              value=""
              placeholder="A janela do WhatsApp está encerrada"
              disabled
              rows={1}
              className="min-h-10 max-h-32 flex-1 resize-none opacity-50"
            />
            <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
              <Button size="sm" variant="secondary" onClick={() => setShowTemplateModal(true)}>
                <Send className="size-4" />
                Enviar Template
              </Button>
            </Can>
          </>
        ) : (
          <>
            <Textarea
              value={messageText}
              onChange={(e) => setMessageText(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={isClosed ? 'Conversa fechada' : 'Digite sua mensagem...'}
              disabled={isClosed}
              rows={1}
              className="min-h-10 max-h-32 resize-none"
            />
            <Button size="sm" onClick={handleSendMessage} disabled={!messageText.trim() || isClosed}>
              <Send className="size-4" />
            </Button>
          </>
        )}
      </div>

      <TagManagementModal
        open={showTagModal}
        onClose={closeTagModal}
        conversationId={conversationId}
        selectedTagIds={selectedTagIds}
        onToggleTag={handleToggleTag}
      />

      <Modal
        open={showTransferModal}
        onClose={() => setShowTransferModal(false)}
        title="Transferir conversa"
        size="sm"
      >
        <div className="space-y-3">
          <Select
            options={userOptions}
            placeholder="Selecionar usuário"
            value={selectedUserId}
            onChange={(e) => setSelectedUserId(e.target.value)}
          />
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <Button variant="secondary" onClick={() => setShowTransferModal(false)}>
            Cancelar
          </Button>
          <Button onClick={handleTransfer} loading={transferConversation.isPending} disabled={!selectedUserId}>
            Transferir
          </Button>
        </div>
      </Modal>

      <ConfirmDialog
        open={showCloseConfirm}
        onClose={() => setShowCloseConfirm(false)}
        onConfirm={handleClose}
        loading={closeConversation.isPending}
        title="Fechar conversa"
        description={
          <>
            Tem certeza que deseja fechar a conversa com{' '}
            <strong>{conversation.contact.profile_name ?? conversation.contact.external_contact_id}</strong>?
          </>
        }
        confirmLabel="Fechar"
        variant="danger"
      />

      <TemplateModal
        open={showTemplateModal}
        onClose={() => setShowTemplateModal(false)}
        conversationId={conversationId}
      />
    </div>
  )
}

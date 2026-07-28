import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import {
  ArrowLeft,
  Send,
  UserPlus,
  UserMinus,
  Tag,
  MessageSquarePlus,
  X,
  Lock,
  Unlock,
} from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  Textarea,
  Page,
  PageContent,
  PageHeader,
  Loading,
  Modal,
  ConfirmDialog,
  Dropdown,
  DropdownItem,
  DropdownSeparator,
  Avatar,
  type SelectOption,
} from '@/shared/design-system'
import { Select } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { TemplateModal } from '../components/TemplateModal'
import { MessageBubble, NoteCard, TagManagementModal } from '../components/ChatComponents'
import type { WhatsAppMessage } from '@/shared/types/models'
import {
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

export default function WhatsAppConversationPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const conversationId = Number(id)
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
  const isAssignedToMe =
    conversation?.current_assignment?.user?.id === currentUser?.id

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
      onSuccess: () => navigate('/whatsapp/inbox'),
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
      <Page>
        <Loading />
      </Page>
    )
  }

  if (!conversation) {
    return (
      <Page>
        <PageHeader
          title="Conversa não encontrada"
          breadcrumb={[
            { label: 'Dashboard', to: '/dashboard' },
            { label: 'WhatsApp', to: '/whatsapp/inbox' },
            { label: 'Conversa' },
          ]}
        />
      </Page>
    )
  }

  return (
    <Page>
      <PageHeader
        title={conversation.contact.profile_name ?? conversation.contact.external_contact_id}
        description={
          <span className="flex items-center gap-1.5">
            {isClosed ? (
              <Badge variant="neutral">Fechada</Badge>
            ) : !windowOpen ? (
              <Badge variant="warning">Janela encerrada</Badge>
            ) : (
              <Badge variant="success">Aberta</Badge>
            )}
            {conversation.contact.external_contact_id}
          </span>
        }
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp/inbox' },
          { label: conversation.contact.profile_name ?? 'Conversa' },
        ]}
        actions={
          <ButtonLink to="/whatsapp/inbox" variant="ghost" size="sm">
            <ArrowLeft className="size-4" />
            Voltar
          </ButtonLink>
        }
      />

      <div className="flex flex-wrap items-center gap-2 rounded-xl bg-surface p-3">
        <div className="flex items-center gap-2">
          {assignedUser ? (
            <>
              <Avatar name={assignedUser.name} size="sm" />
              <span className="text-sm text-foreground">{assignedUser.name}</span>
              <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowTransferModal(true)}
                  title="Transferir"
                >
                  <UserPlus className="size-3.5" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleRemoveAssignment}
                  loading={removeAssignment.isPending}
                  title="Remover responsável"
                >
                  <UserMinus className="size-3.5" />
                </Button>
              </Can>
            </>
          ) : (
            <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
              <Button
                variant="secondary"
                size="sm"
                onClick={handleAssign}
                loading={assignConversation.isPending}
              >
                <UserPlus className="size-3.5" />
                Assumir
              </Button>
            </Can>
          )}
        </div>

        <div className="h-5 w-px bg-surface-3" />

        {conversation.current_stage && (
          <Badge
            variant="primary"
            style={{ backgroundColor: conversation.current_stage.color ?? undefined }}
          >
            {conversation.current_stage.name}
          </Badge>
        )}

        <div className="h-5 w-px bg-surface-3" />

        <div className="flex items-center gap-1.5">
          <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
            <Button variant="ghost" size="sm" onClick={() => setShowTagModal(true)}>
              <Tag className="size-3.5" />
              Tags
            </Button>
          </Can>
          {conversation.tags?.map((tag) => (
            <Badge
              key={tag.id}
              style={{ backgroundColor: tag.color ?? undefined }}
              variant="neutral"
            >
              {tag.name}
            </Badge>
          ))}
        </div>

        <div className="ml-auto flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setShowNotes((v) => !v)}
          >
            <MessageSquarePlus className="size-3.5" />
            Notas {notes.length > 0 && `(${notes.length})`}
          </Button>

          <Can permission={Permission.WHATSAPP_CONVERSATION_UPDATE}>
            {isClosed ? (
              <Button
                variant="secondary"
                size="sm"
                onClick={handleReopen}
                loading={reopenConversation.isPending}
              >
                <Unlock className="size-3.5" />
                Reabrir
              </Button>
            ) : (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setShowCloseConfirm(true)}
                className="text-warning hover:bg-warning-soft hover:text-warning"
              >
                <Lock className="size-3.5" />
                Fechar
              </Button>
            )}
          </Can>
        </div>
      </div>

      {showNotes && (
        <div className="rounded-xl bg-surface p-4">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-foreground">Notas internas</h3>
            <Button variant="ghost" size="sm" onClick={() => setShowNotes(false)}>
              <X className="size-4" />
            </Button>
          </div>

          {notes.length > 0 ? (
            <div className="mb-4 space-y-2 max-h-48 overflow-y-auto">
              {notes.map((note) => (
                <NoteCard key={note.id} note={note} />
              ))}
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
            <Button
              size="sm"
              onClick={handleAddNote}
              loading={addNote.isPending}
              disabled={!noteText.trim()}
            >
              Salvar
            </Button>
          </div>
        </div>
      )}

      <PageContent className="flex-1">
        <Card className="flex h-[calc(100vh-20rem)] flex-col overflow-hidden">
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

          <div className="flex items-end gap-2 border-t border-surface-3 p-3">
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
                <Button
                  size="sm"
                  onClick={handleSendMessage}
                  disabled={!messageText.trim() || isClosed}
                >
                  <Send className="size-4" />
                </Button>
              </>
            )}
          </div>
        </Card>
      </PageContent>

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
          <Button
            onClick={handleTransfer}
            loading={transferConversation.isPending}
            disabled={!selectedUserId}
          >
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
    </Page>
  )
}

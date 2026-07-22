import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router'
import {
  ArrowLeft,
  Send,
  UserPlus,
  UserMinus,
  Paperclip,
  Check,
  CheckCheck,
  Clock,
  Tag,
  MessageSquarePlus,
  X,
  Lock,
  Unlock,
  Image,
  FileText,
  Video,
  Music,
} from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
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
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'
import type { WhatsAppMessage, WhatsAppNote, WhatsAppTag } from '@/shared/types/models'
import {
  useConversationQuery,
  useSendMessage,
  useAssignConversation,
  useTransferConversation,
  useRemoveAssignment,
  useCloseConversation,
  useReopenConversation,
  useAddNote,
  useSyncConversationTags,
  useTagsQuery,
} from '../hooks/useWhatsApp'
import { useUsersQuery } from '@/modules/users/hooks/useUsers'

function formatTime(value: string | null | undefined): string {
  if (!value) return ''
  return new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

function MessageStatus({ status }: { status: string }) {
  const base = 'inline-flex items-center shrink-0 ml-1'

  switch (status) {
    case 'received':
      return (
        <span className={base} title="Recebida">
          <Clock className="size-3 text-muted" />
        </span>
      )
    case 'sent':
      return (
        <span className={base} title="Enviada">
          <Check className="size-3.5 text-muted" />
        </span>
      )
    case 'delivered':
      return (
        <span className={base} title="Entregue">
          <CheckCheck className="size-3.5 text-muted" />
        </span>
      )
    case 'read':
      return (
        <span className={base} title="Lida">
          <CheckCheck className="size-3.5 text-blue-500" />
        </span>
      )
    default:
      return null
  }
}

function MediaPlaceholder({ type }: { type: string }) {
  switch (type) {
    case 'image':
      return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted">
          <Image className="size-4" />
          Imagem
        </span>
      )
    case 'document':
      return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted">
          <FileText className="size-4" />
          Documento
        </span>
      )
    case 'video':
      return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted">
          <Video className="size-4" />
          Vídeo
        </span>
      )
    case 'audio':
      return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted">
          <Music className="size-4" />
          Áudio
        </span>
      )
    default:
      return (
        <span className="inline-flex items-center gap-1.5 text-sm text-muted">
          <Paperclip className="size-4" />
          Mídia
        </span>
      )
  }
}

function MessageBubble({ message, isOutbound }: { message: WhatsAppMessage; isOutbound: boolean }) {
  return (
    <div className={cn('flex', isOutbound ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[75%] rounded-2xl px-4 py-2.5 text-sm',
          isOutbound
            ? 'rounded-br-md bg-primary text-primary-foreground'
            : 'rounded-bl-md bg-surface-2 text-foreground',
        )}
      >
        {message.message_type !== 'text' || !message.content ? (
          <MediaPlaceholder type={message.message_type} />
        ) : (
          <p className="whitespace-pre-wrap break-words">{message.content}</p>
        )}
        <div
          className={cn(
            'mt-1 flex items-center justify-end gap-0.5 text-[11px]',
            isOutbound ? 'text-primary-foreground/70' : 'text-muted',
          )}
        >
          <span>{formatTime(message.created_at)}</span>
          {isOutbound && <MessageStatus status={message.status} />}
        </div>
      </div>
    </div>
  )
}

function NoteCard({ note }: { note: WhatsAppNote }) {
  return (
    <div className="rounded-lg border border-surface-3 bg-surface-2/50 p-3">
      <div className="mb-1.5 flex items-center gap-2">
        {note.user?.name && <Avatar name={note.user.name} size="sm" />}
        <div>
          <span className="text-xs font-medium text-foreground">{note.user?.name ?? 'Usuário'}</span>
          <span className="ml-2 text-[11px] text-muted">{formatDateTime(note.created_at)}</span>
        </div>
      </div>
      <p className="text-sm text-foreground whitespace-pre-wrap">{note.content}</p>
    </div>
  )
}

export default function WhatsAppConversationPage() {
  const { id } = useParams<{ id: string }>()
  const conversationId = Number(id)
  const currentUser = useSessionStore((state) => state.user)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const { data: conversation, isLoading } = useConversationQuery(conversationId)
  const { data: tags } = useTagsQuery()
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

  const isClosed = conversation?.status === 'closed'
  const isAssignedToMe =
    conversation?.current_assignment?.user?.id === currentUser?.id

  const messages = conversation?.messages ?? []
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

  const handleSendMessage = () => {
    const text = messageText.trim()
    if (!text || isClosed) return
    sendMessage.mutate({ id: conversationId, content: text })
    setMessageText('')
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
    closeConversation.mutate(conversationId, { onSettled: () => setShowCloseConfirm(false) })
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

  const handleToggleTag = (tagId: number) => {
    const next = selectedTagIds.includes(tagId)
      ? selectedTagIds.filter((t) => t !== tagId)
      : [...selectedTagIds, tagId]
    syncTags.mutate({ id: conversationId, tagIds: next })
  }

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
        title={conversation.contact.profile_name ?? conversation.contact.wa_id}
        description={
          <span className="flex items-center gap-1.5">
            {isClosed ? (
              <Badge variant="neutral">Fechada</Badge>
            ) : (
              <Badge variant="success">Aberta</Badge>
            )}
            {conversation.contact.wa_id}
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
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {messages.length === 0 && (
              <div className="flex h-full items-center justify-center text-muted text-sm">
                Nenhuma mensagem nesta conversa.
              </div>
            )}
            {messages.map((message) => (
              <MessageBubble
                key={message.id}
                message={message}
                isOutbound={message.direction === 'outbound'}
              />
            ))}
            <div ref={messagesEndRef} />
          </div>

          <div className="flex items-end gap-2 border-t border-surface-3 p-3">
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
              loading={sendMessage.isPending}
              disabled={!messageText.trim() || isClosed}
            >
              <Send className="size-4" />
            </Button>
          </div>
        </Card>
      </PageContent>

      <Modal
        open={showTagModal}
        onClose={() => setShowTagModal(false)}
        title="Gerenciar tags"
        size="sm"
      >
        <div className="space-y-2">
          {tags?.length ? (
            tags.map((tag) => {
              const checked = selectedTagIds.includes(tag.id)
              return (
                <label
                  key={tag.id}
                  className="flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors hover:bg-surface-2"
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => handleToggleTag(tag.id)}
                    className="size-4 rounded accent-primary"
                  />
                  <span
                    className="size-3 rounded-full"
                    style={{ backgroundColor: tag.color ?? '#888' }}
                  />
                  <span className="text-sm text-foreground">{tag.name}</span>
                </label>
              )
            })
          ) : (
            <p className="text-sm text-muted">Nenhuma tag disponível.</p>
          )}
        </div>
        <div className="mt-4 flex justify-end">
          <Button variant="secondary" onClick={() => setShowTagModal(false)}>
            Fechar
          </Button>
        </div>
      </Modal>

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
            <strong>{conversation.contact.profile_name ?? conversation.contact.wa_id}</strong>?
          </>
        }
        confirmLabel="Fechar"
        variant="danger"
      />
    </Page>
  )
}

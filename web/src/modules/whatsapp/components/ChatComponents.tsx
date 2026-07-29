import { useCallback, useState } from 'react'
import {
  AlertCircle,
  Check,
  CheckCheck,
  Clock,
  Paperclip,
  Image,
  FileText,
  Video,
  Music,
  Plus,
  RotateCcw,
  X,
} from 'lucide-react'
import {
  Button,
  Input,
  Modal,
  Avatar,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'
import type { WhatsAppMessage, WhatsAppNote } from '@/shared/types/models'
import {
  useTagsQuery,
  useCreateTag,
  useSyncConversationTags,
} from '../hooks/useWhatsApp'

export function formatChatTime(value: string | null | undefined): string {
  if (!value) return ''
  return new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export function MessageStatus({ status }: { status: string }) {
  const base = 'inline-flex items-center shrink-0 ml-1'

  switch (status) {
    case 'pending':
      return <span className={base} title="Enviando..."><Clock className="size-3 animate-pulse opacity-70" /></span>
    case 'received':
      return <span className={base} title="Recebida"><Clock className="size-3 text-muted" /></span>
    case 'sent':
      return <span className={base} title="Enviada"><Check className="size-3.5 text-muted" /></span>
    case 'delivered':
      return <span className={base} title="Entregue"><CheckCheck className="size-3.5 text-muted" /></span>
    case 'read':
      return <span className={base} title="Lida"><CheckCheck className="size-3.5 text-blue-500" /></span>
    case 'failed':
      return <span className={base} title="Falha no envio"><AlertCircle className="size-3.5 text-danger" /></span>
    default:
      return null
  }
}

export function MediaPlaceholder({ type }: { type: string }) {
  switch (type) {
    case 'image':
      return <span className="inline-flex items-center gap-1.5 text-sm text-muted"><Image className="size-4" />Imagem</span>
    case 'document':
      return <span className="inline-flex items-center gap-1.5 text-sm text-muted"><FileText className="size-4" />Documento</span>
    case 'video':
      return <span className="inline-flex items-center gap-1.5 text-sm text-muted"><Video className="size-4" />Vídeo</span>
    case 'audio':
      return <span className="inline-flex items-center gap-1.5 text-sm text-muted"><Music className="size-4" />Áudio</span>
    default:
      return <span className="inline-flex items-center gap-1.5 text-sm text-muted"><Paperclip className="size-4" />Mídia</span>
  }
}

function mediaUrl(message: WhatsAppMessage): string | null {
  const url = message.media?.url
  return typeof url === 'string' && url.length > 0 ? url : null
}

function ImagePreview({
  src,
  caption,
  isOutbound,
}: {
  src: string
  caption?: string | null
  isOutbound: boolean
}) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="block overflow-hidden rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <img
          src={src}
          alt={caption || 'Imagem'}
          loading="lazy"
          className="max-h-72 max-w-full cursor-zoom-in rounded-xl object-cover"
        />
      </button>
      {caption ? (
        <p className={cn('mt-2 whitespace-pre-wrap break-words', isOutbound && 'text-primary-foreground')}>
          {caption}
        </p>
      ) : null}

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-overlay p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Pré-visualização da imagem"
          onClick={() => setOpen(false)}
        >
          <button
            type="button"
            onClick={() => setOpen(false)}
            className="absolute right-4 top-4 rounded-full bg-surface/90 p-2 text-foreground shadow-raised"
            aria-label="Fechar"
          >
            <X className="size-5" />
          </button>
          <img
            src={src}
            alt={caption || 'Imagem'}
            className="max-h-[90vh] max-w-[min(960px,100%)] rounded-lg object-contain shadow-pop"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </>
  )
}

function AudioPlayer({ src }: { src: string }) {
  return (
    <div className="min-w-[220px] max-w-full">
      <audio controls preload="metadata" src={src} className="h-10 w-full max-w-[280px]">
        Seu navegador não suporta áudio.
      </audio>
    </div>
  )
}

function MessageBody({
  message,
  isOutbound,
}: {
  message: WhatsAppMessage
  isOutbound: boolean
}) {
  const url = mediaUrl(message)
  const caption = message.content

  if (message.message_type === 'image' && url) {
    return <ImagePreview src={url} caption={caption} isOutbound={isOutbound} />
  }

  if (message.message_type === 'audio' && url) {
    return (
      <div className="space-y-2">
        <AudioPlayer src={url} />
        {caption ? <p className="whitespace-pre-wrap break-words">{caption}</p> : null}
      </div>
    )
  }

  if (message.message_type === 'video' && url) {
    return (
      <div className="space-y-2">
        <video controls preload="metadata" src={url} className="max-h-72 max-w-full rounded-xl">
          Seu navegador não suporta vídeo.
        </video>
        {caption ? <p className="whitespace-pre-wrap break-words">{caption}</p> : null}
      </div>
    )
  }

  if (message.message_type === 'document' && url) {
    return (
      <div className="space-y-2">
        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          className={cn(
            'inline-flex items-center gap-1.5 text-sm underline underline-offset-2',
            isOutbound ? 'text-primary-foreground' : 'text-primary',
          )}
        >
          <FileText className="size-4" />
          Abrir documento
        </a>
        {caption ? <p className="whitespace-pre-wrap break-words">{caption}</p> : null}
      </div>
    )
  }

  if (message.message_type !== 'text') {
    return (
      <div className="space-y-2">
        <MediaPlaceholder type={message.message_type} />
        {caption ? <p className="whitespace-pre-wrap break-words">{caption}</p> : null}
      </div>
    )
  }

  if (caption) {
    return <p className="whitespace-pre-wrap break-words">{caption}</p>
  }

  return <MediaPlaceholder type={message.message_type || 'unknown'} />
}

export function MessageBubble({
  message,
  isOutbound,
  onRetry,
}: {
  message: WhatsAppMessage
  isOutbound: boolean
  onRetry?: (message: WhatsAppMessage) => void
}) {
  const isFailed = isOutbound && message.status === 'failed'
  const isPending = isOutbound && message.status === 'pending'

  return (
    <div className={cn('flex flex-col gap-1', isOutbound ? 'items-end' : 'items-start')}>
      <div
        className={cn(
          'max-w-[75%] rounded-2xl px-4 py-2.5 text-sm',
          isOutbound
            ? cn(
                'rounded-br-md bg-primary text-primary-foreground',
                isPending && 'opacity-80',
                isFailed && 'ring-1 ring-danger/40',
              )
            : 'rounded-bl-md bg-surface-2 text-foreground',
        )}
      >
        {isOutbound && message.sender_name && (
          <span className="mb-2 block text-[11px] font-semibold text-primary-foreground/80">
            {message.sender_name}
          </span>
        )}
        <MessageBody message={message} isOutbound={isOutbound} />
        <div
          className={cn(
            'mt-1 flex items-center justify-end gap-0.5 text-[11px]',
            isOutbound ? 'text-primary-foreground/70' : 'text-muted',
          )}
        >
          <span>{formatChatTime(message.created_at)}</span>
          {isOutbound && <MessageStatus status={message.status} />}
        </div>
      </div>
      {isFailed && (
        <button
          type="button"
          onClick={() => onRetry?.(message)}
          className="inline-flex items-center gap-1 px-1 text-[11px] font-medium text-danger transition-opacity hover:opacity-80"
        >
          <AlertCircle className="size-3" />
          Falha no envio
          <span className="inline-flex items-center gap-0.5 underline underline-offset-2">
            <RotateCcw className="size-3" />
            Tentar novamente
          </span>
        </button>
      )}
    </div>
  )
}

export function NoteCard({ note }: { note: WhatsAppNote }) {
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

export function TagManagementModal({
  open,
  onClose,
  conversationId,
  selectedTagIds,
  onToggleTag,
}: {
  open: boolean
  onClose: () => void
  conversationId: number
  selectedTagIds: number[]
  onToggleTag: (tagId: number) => void
}) {
  const { data: tags } = useTagsQuery()
  const createTag = useCreateTag()
  const syncTags = useSyncConversationTags()

  const [search, setSearch] = useState('')

  const filtered = tags?.filter((t) =>
    t.name.toLowerCase().includes(search.toLowerCase()),
  ) ?? []

  const hasExactMatch = filtered.some(
    (t) => t.name.toLowerCase() === search.trim().toLowerCase(),
  )
  const showCreate = search.trim().length > 0 && !hasExactMatch

  const handleCreateAndAdd = async () => {
    const name = search.trim()
    if (!name) return
    const newTag = await createTag.mutateAsync({ name })
    syncTags.mutate({ id: conversationId, tagIds: [...selectedTagIds, newTag.id] })
    setSearch('')
  }

  const handleClose = useCallback(() => {
    setSearch('')
    onClose()
  }, [onClose])

  return (
    <Modal open={open} onClose={handleClose} title="Gerenciar tags" size="sm">
      <div className="space-y-3">
        <Input
          placeholder="Buscar ou criar tag..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <div className="space-y-2">
          {filtered.length === 0 && !showCreate ? (
            <p className="text-sm text-muted">Nenhuma tag disponível.</p>
          ) : (
            <>
              {filtered.map((tag) => {
                const checked = selectedTagIds.includes(tag.id)
                return (
                  <label
                    key={tag.id}
                    className="flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors hover:bg-surface-2"
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => onToggleTag(tag.id)}
                      className="size-4 rounded accent-primary"
                    />
                    <span
                      className="size-3 rounded-full"
                      style={{ backgroundColor: tag.color ?? '#888' }}
                    />
                    <span className="text-sm text-foreground">{tag.name}</span>
                  </label>
                )
              })}
              {showCreate && (
                <button
                  type="button"
                  onClick={handleCreateAndAdd}
                  disabled={createTag.isPending || syncTags.isPending}
                  className="flex w-full items-center gap-2 rounded-lg p-2 text-sm text-primary transition-colors hover:bg-surface-2 disabled:opacity-50"
                >
                  <Plus className="size-4" />
                  Adicionar &quot;{search.trim()}&quot;
                </button>
              )}
            </>
          )}
        </div>
      </div>
      <div className="mt-4 flex justify-end">
        <Button variant="secondary" onClick={handleClose}>
          Fechar
        </Button>
      </div>
    </Modal>
  )
}

import { useState, useCallback, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router'
import { GripVertical, Settings } from 'lucide-react'
import { Badge, ButtonLink, Card, CardContent, Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import type { KanbanStage, WhatsAppConversation } from '@/shared/types/models'
import {
  useKanbanStagesQuery,
  useKanbanStageConversationsQuery,
  useMoveConversationStage,
} from '../hooks/useWhatsApp'

export default function WhatsAppKanbanPage() {
  const navigate = useNavigate()
  const stagesQuery = useKanbanStagesQuery()
  const moveConversation = useMoveConversationStage()

  const [draggedId, setDraggedId] = useState<number | null>(null)
  const [dropTarget, setDropTarget] = useState<number | null>(null)

  const handleDragStart = useCallback((conversationId: number) => {
    setDraggedId(conversationId)
  }, [])

  const handleDragOver = useCallback((e: React.DragEvent, stageId: number) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
    setDropTarget(stageId)
  }, [])

  const handleDragLeave = useCallback(() => {
    setDropTarget(null)
  }, [])

  const handleDrop = useCallback(
    (stageId: number) => {
      setDropTarget(null)
      if (draggedId === null) return

      moveConversation.mutate(
        { conversationId: draggedId, stageId },
        { onSettled: () => setDraggedId(null) },
      )
    },
    [draggedId, moveConversation],
  )

  const handleDragEnd = useCallback(() => {
    setDraggedId(null)
    setDropTarget(null)
  }, [])

  return (
    <Page>
      <PageHeader
        title="Kanban de Qualificação"
        description="Organize as conversas por etapa de atendimento."
        breadcrumb={[{ label: 'WhatsApp', to: '/whatsapp/dashboard' }, { label: 'Kanban' }]}
        actions={
          <Can permission={Permission.WHATSAPP_KANBAN_UPDATE}>
            <ButtonLink to="/whatsapp/kanban/stages" variant="secondary" size="sm">
              <Settings className="size-4" />
              Etapas
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        {stagesQuery.isPending ? (
          <Loading label="Carregando kanban..." />
        ) : (
          <div className="-mx-6 overflow-x-auto px-6 pb-4">
            <div className="flex gap-4 min-w-max">
              {(stagesQuery.data ?? []).map((stage) => (
                <KanbanColumnView
                  key={stage.id}
                  stage={stage}
                  draggedId={draggedId}
                  dropTarget={dropTarget}
                  onDragStart={handleDragStart}
                  onDragOver={handleDragOver}
                  onDragLeave={handleDragLeave}
                  onDrop={handleDrop}
                  onDragEnd={handleDragEnd}
                  onClickCard={(id) => navigate(`/whatsapp/conversations/${id}`)}
                />
              ))}
            </div>
          </div>
        )}
      </PageContent>
    </Page>
  )
}

function KanbanColumnView({
  stage,
  draggedId,
  dropTarget,
  onDragStart,
  onDragOver,
  onDragLeave,
  onDrop,
  onDragEnd,
  onClickCard,
}: {
  stage: KanbanStage
  draggedId: number | null
  dropTarget: number | null
  onDragStart: (id: number) => void
  onDragOver: (e: React.DragEvent, stageId: number) => void
  onDragLeave: () => void
  onDrop: (stageId: number) => void
  onDragEnd: () => void
  onClickCard: (id: number) => void
}) {
  const isDropZone = dropTarget === stage.id
  const conversationsQuery = useKanbanStageConversationsQuery(stage.id)
  const loadMoreRef = useRef<HTMLDivElement>(null)

  const conversations = conversationsQuery.data?.pages.flatMap((page) => page.data) ?? []

  useEffect(() => {
    const sentinel = loadMoreRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver(
      (entries) => {
        if (
          entries[0]?.isIntersecting &&
          conversationsQuery.hasNextPage &&
          !conversationsQuery.isFetchingNextPage
        ) {
          void conversationsQuery.fetchNextPage()
        }
      },
      { rootMargin: '120px' },
    )

    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [
    conversationsQuery.hasNextPage,
    conversationsQuery.isFetchingNextPage,
    conversationsQuery.fetchNextPage,
  ])

  return (
    <div
      className={cn(
        'w-72 shrink-0 rounded-xl bg-surface-2/60 transition-colors',
        isDropZone && 'bg-primary-soft/30 ring-2 ring-primary/40',
      )}
      onDragOver={(e) => onDragOver(e, stage.id)}
      onDragLeave={onDragLeave}
      onDrop={() => onDrop(stage.id)}
    >
      <div className="flex items-center justify-between p-3">
        <div className="flex items-center gap-2 min-w-0">
          <span
            className="size-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: stage.color ?? '#6b7280' }}
            aria-hidden="true"
          />
          <h3 className="truncate text-sm font-semibold text-foreground">{stage.name}</h3>
          <span className="shrink-0 rounded-full bg-surface-3 px-2 py-0.5 text-xs font-medium text-muted">
            {conversations.length}
            {conversationsQuery.hasNextPage ? '+' : ''}
          </span>
        </div>
      </div>

      <div className="flex max-h-[calc(100vh-16rem)] flex-col gap-2 overflow-y-auto p-3 pt-0">
        {conversationsQuery.isPending ? (
          <p className="py-8 text-center text-sm text-muted">Carregando...</p>
        ) : conversations.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted">Sem conversas</p>
        ) : (
          conversations.map((conversation) => (
            <ConversationCard
              key={conversation.id}
              conversation={conversation}
              isDragging={draggedId === conversation.id}
              onDragStart={onDragStart}
              onDragEnd={onDragEnd}
              onClick={() => onClickCard(conversation.id)}
            />
          ))
        )}
        <div ref={loadMoreRef} className="h-px" />
        {conversationsQuery.isFetchingNextPage && (
          <p className="py-2 text-center text-xs text-muted">Carregando mais...</p>
        )}
      </div>
    </div>
  )
}

function ConversationCard({
  conversation,
  isDragging,
  onDragStart,
  onDragEnd,
  onClick,
}: {
  conversation: WhatsAppConversation
  isDragging: boolean
  onDragStart: (id: number) => void
  onDragEnd: () => void
  onClick: () => void
}) {
  const displayName = conversation.contact.display_name || conversation.contact.profile_name || 'Sem nome'

  return (
    <Card
      draggable
      className={cn(
        'cursor-grab transition-opacity active:cursor-grabbing select-none',
        isDragging && 'opacity-40',
      )}
      onDragStart={() => onDragStart(conversation.id)}
      onDragEnd={onDragEnd}
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          onClick()
        }
      }}
    >
      <CardContent className="flex flex-col gap-2 p-3">
        <div className="flex items-start gap-1.5">
          <GripVertical className="mt-px size-3.5 shrink-0 text-muted" aria-hidden="true" />
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <p className="truncate text-sm font-medium text-foreground">{displayName}</p>
              {conversation.is_unread && <Badge variant="primary">Nova</Badge>}
            </div>
            {conversation.last_message_preview && (
              <p className="mt-0.5 line-clamp-2 text-xs text-muted">{conversation.last_message_preview}</p>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

import { useState, useCallback } from 'react'
import { useNavigate } from 'react-router'
import { GripVertical, Plus, Settings } from 'lucide-react'
import { Badge, Button, ButtonLink, Card, CardContent, Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import type { KanbanColumn, WhatsAppConversation } from '@/shared/types/models'
import { useKanbanBoardQuery, useMoveConversationStage } from '../hooks/useWhatsApp'

export default function WhatsAppKanbanPage() {
  const navigate = useNavigate()
  const query = useKanbanBoardQuery()
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
        {query.isPending ? (
          <Loading label="Carregando kanban..." />
        ) : (
          <div className="-mx-6 overflow-x-auto px-6 pb-4">
            <div className="flex gap-4 min-w-max">
              {(query.data ?? []).map((column) => (
                <KanbanColumnView
                  key={column.stage.id}
                  column={column}
                  draggedId={draggedId}
                  dropTarget={dropTarget}
                  onDragStart={handleDragStart}
                  onDragOver={handleDragOver}
                  onDragLeave={handleDragLeave}
                  onDrop={handleDrop}
                  onDragEnd={handleDragEnd}
                  onClickCard={(id) => navigate(`/whatsapp/conversations/${id}`)}
                  isMoving={moveConversation.isPending}
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
  column,
  draggedId,
  dropTarget,
  onDragStart,
  onDragOver,
  onDragLeave,
  onDrop,
  onDragEnd,
  onClickCard,
  isMoving,
}: {
  column: KanbanColumn
  draggedId: number | null
  dropTarget: number | null
  onDragStart: (id: number) => void
  onDragOver: (e: React.DragEvent, stageId: number) => void
  onDragLeave: () => void
  onDrop: (stageId: number) => void
  onDragEnd: () => void
  onClickCard: (id: number) => void
  isMoving: boolean
}) {
  const isDropZone = dropTarget === column.stage.id

  return (
    <div
      className={cn(
        'w-72 shrink-0 rounded-xl bg-surface-2/60 transition-colors',
        isDropZone && 'bg-primary-soft/30 ring-2 ring-primary/40',
      )}
      onDragOver={(e) => onDragOver(e, column.stage.id)}
      onDragLeave={onDragLeave}
      onDrop={() => onDrop(column.stage.id)}
    >
      <div className="flex items-center justify-between p-3">
        <div className="flex items-center gap-2 min-w-0">
          <span
            className="size-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: column.stage.color ?? '#6b7280' }}
            aria-hidden="true"
          />
          <h3 className="truncate text-sm font-semibold text-foreground">{column.stage.name}</h3>
          <span className="shrink-0 rounded-full bg-surface-3 px-2 py-0.5 text-xs font-medium text-muted">
            {column.conversations.length}
          </span>
        </div>
        <Button variant="ghost" size="sm" aria-label={`Adicionar etapa ${column.stage.name}`}>
          <Plus className="size-4" />
        </Button>
      </div>

      <div className="flex flex-col gap-2 p-3 pt-0">
        {column.conversations.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted">Sem conversas</p>
        ) : (
          column.conversations.map((conversation) => (
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
        {isMoving && (
          <div className="flex items-center justify-center gap-2 py-3 text-sm text-muted">
            <span className="size-3 animate-spin rounded-full border-2 border-primary/30 border-t-primary" />
            Movendo...
          </div>
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
              {conversation.is_unread && (
                <span className="size-2 shrink-0 rounded-full bg-primary" aria-label="Não lida" />
              )}
            </div>
            {conversation.last_message_preview && (
              <p className="mt-0.5 truncate text-xs text-muted">
                {conversation.last_message_preview.length > 50
                  ? `${conversation.last_message_preview.slice(0, 50)}...`
                  : conversation.last_message_preview}
              </p>
            )}
          </div>
        </div>

        {(conversation.current_assignment || conversation.tags.length > 0) && (
          <div className="flex flex-wrap items-center gap-1">
            {conversation.current_assignment?.user && (
              <Badge variant="primary" className="text-[11px]">
                {conversation.current_assignment.user.name}
              </Badge>
            )}
            {conversation.tags.map((tag) => (
              <span
                key={tag.id}
                className="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap"
                style={{
                  backgroundColor: tag.color ? `${tag.color}20` : undefined,
                  color: tag.color ?? undefined,
                }}
              >
                {tag.name}
              </span>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

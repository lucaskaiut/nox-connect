import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { MessageCircle } from 'lucide-react'
import {
  Avatar,
  Badge,
  Button,
  ButtonLink,
  Card,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  Skeleton,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useSessionStore } from '@/shared/stores/session.store'
import { formatRelative } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import type { WhatsAppConversation } from '@/shared/types/models'
import { useConversationsQuery, useConversationStatsQuery } from '../hooks/useWhatsApp'
import type { ConversationFilters } from '../services/whatsapp.service'

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

export default function WhatsAppInboxPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const [activeTab, setActiveTab] = useState<FilterTab>(
    (searchParams.get('tab') as FilterTab) ?? 'all',
  )
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

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

  const query = useConversationsQuery(filters)
  const stats = useConversationStatsQuery()

  const updateParams = (next: { page?: number; search?: string; tab?: FilterTab }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.tab !== undefined) {
          next.tab && next.tab !== 'all' ? params.set('tab', next.tab) : params.delete('tab')
          params.delete('page')
        }
        if (next.page !== undefined) {
          next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
        }
        return params
      },
      { replace: true },
    )
  }

  const handleSearch = (value: string) => {
    setSearch(value)
    updateParams({ search: value })
  }

  const handleTabChange = (tab: FilterTab) => {
    setActiveTab(tab)
    updateParams({ tab })
  }

  const tabs: Array<{ key: FilterTab; label: string; count?: number }> = [
    { key: 'all', label: 'Todas' },
    { key: 'unassigned', label: 'Sem responsável', count: stats.data?.unassigned },
    { key: 'mine', label: 'Minhas' },
    { key: 'closed', label: 'Finalizadas', count: stats.data?.closed },
  ]

  const conversations = query.data?.data ?? []
  const showEmpty = !query.isPending && conversations.length === 0
  const selectedCount =
    activeTab === 'closed'
      ? stats.data?.closed
      : activeTab === 'unassigned'
        ? stats.data?.unassigned
        : query.data?.meta.total

  return (
    <Page>
      <PageHeader
        title="Caixa de Entrada"
        description="Gerencie as conversas de WhatsApp da sua organização."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'WhatsApp' }, { label: 'Caixa de Entrada' }]}
        actions={
          <Can permission={Permission.WHATSAPP_KANBAN_READ}>
            <ButtonLink to="/whatsapp/kanban" variant="secondary" size="sm">
              Kanban
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <div className="flex flex-col gap-4">
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
                  {tab.count !== undefined && (
                    <span
                      className={cn(
                        'ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-medium',
                        activeTab === tab.key
                          ? 'bg-surface-3 text-foreground'
                          : 'bg-surface-2 text-muted',
                      )}
                    >
                      {tab.count}
                    </span>
                  )}
                </Button>
              ))}
            </div>

            <SearchInput
              placeholder="Buscar por nome ou telefone..."
              aria-label="Buscar conversas"
              value={search}
              onChange={(event) => handleSearch(event.target.value)}
            />
          </FilterBar>

          {selectedCount !== undefined && !query.isPending && (
            <p className="text-[13px] text-muted">
              {selectedCount} {selectedCount === 1 ? 'conversa encontrada' : 'conversas encontradas'}
            </p>
          )}
        </div>

        <Card className="mt-5 overflow-hidden">
          {query.isPending ? (
            <div>
              {Array.from({ length: 8 }).map((_, index) => (
                <ConversationSkeleton key={index} />
              ))}
            </div>
          ) : showEmpty ? (
            <EmptyState
              icon={MessageCircle}
              title="Nenhuma conversa encontrada"
              description={
                debouncedSearch || activeTab !== 'all'
                  ? 'Tente ajustar os filtros ou termos da busca.'
                  : 'As conversas de WhatsApp aparecerão aqui quando houver interações.'
              }
            />
          ) : (
            <div>
              {conversations.map((conversation) => (
                <ConversationRow key={conversation.id} conversation={conversation} />
              ))}
            </div>
          )}
        </Card>

        {query.data && (
          <Pagination
            className="mt-4"
            meta={query.data.meta}
            onPageChange={(next) => updateParams({ page: next })}
          />
        )}
      </PageContent>
    </Page>
  )
}

function ConversationRow({ conversation }: { conversation: WhatsAppConversation }) {
  const contactName =
    conversation.contact.display_name ||
    conversation.contact.profile_name ||
    conversation.contact.wa_id

  const assignment = conversation.current_assignment
  const stage = conversation.current_stage

  return (
    <Link
      to={`/whatsapp/conversations/${conversation.id}`}
      className={cn(
        'flex items-center gap-3 px-5 py-3.5 shadow-[inset_0_1px_0_var(--app-surface-2)] transition-colors hover:bg-surface-2/40',
        conversation.is_unread && 'bg-primary-soft/30',
      )}
    >
      <div className="relative shrink-0">
        <Avatar name={contactName} />
        {conversation.is_unread && (
          <span className="absolute top-0 right-0 size-3 rounded-full border-2 border-surface-1 bg-primary" />
        )}
      </div>

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <p
            className={cn(
              'truncate text-sm',
              conversation.is_unread ? 'font-semibold text-foreground' : 'font-medium text-foreground',
            )}
          >
            {contactName}
          </p>
        </div>
        <div className="mt-0.5 flex items-center gap-2">
          {conversation.last_message_preview && (
            <p
              className={cn(
                'truncate text-[13px]',
                conversation.is_unread ? 'font-medium text-foreground' : 'text-muted',
              )}
            >
              {conversation.last_message_preview}
            </p>
          )}
          <span className="text-[13px] text-muted">&middot;</span>
          <span className="shrink-0 text-[13px] text-muted">
            {formatRelative(conversation.last_message_at)}
          </span>
        </div>
      </div>

      <div className="hidden shrink-0 items-center gap-2 sm:flex">
        {assignment?.user ? (
          <Badge variant="neutral">{assignment.user.name}</Badge>
        ) : (
          <Badge>Sem responsável</Badge>
        )}

        {stage ? <Badge variant="primary">{stage.name}</Badge> : null}

        {conversation.tags?.length ? (
          <Badge variant="neutral">
            {conversation.tags[0].name}
            {conversation.tags.length > 1 && ` +${conversation.tags.length - 1}`}
          </Badge>
        ) : null}
      </div>
    </Link>
  )
}

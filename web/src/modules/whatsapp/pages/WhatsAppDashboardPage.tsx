import { Link } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import {
  BarChart3,
  Clock,
  KanbanSquare,
  MessageSquare,
  MessagesSquare,
  Settings,
  Tags,
  UserX,
} from 'lucide-react'
import { Card, CardContent, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { useConversationStatsQuery } from '../hooks/useWhatsApp'

export default function WhatsAppDashboardPage() {
  const statsQuery = useConversationStatsQuery()
  const stats = statsQuery.data

  return (
    <Page>
      <PageHeader
        title="WhatsApp"
        description="Visão geral do atendimento via WhatsApp."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'WhatsApp' }]}
      />

      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label="Conversas abertas"
            icon={MessagesSquare}
            value={stats?.open}
            loading={statsQuery.isPending}
            accent="bg-primary-soft text-primary"
          />
          <StatCard
            label="Conversas encerradas"
            icon={MessageSquare}
            value={stats?.closed}
            loading={statsQuery.isPending}
            accent="bg-success-soft text-success"
          />
          <StatCard
            label="Sem responsável"
            icon={UserX}
            value={stats?.unassigned}
            loading={statsQuery.isPending}
            accent="bg-warning-soft text-warning"
          />
          <StatCard
            label="Tempo médio de resposta"
            icon={Clock}
            value="Em breve"
            loading={false}
            accent="bg-surface-2 text-muted"
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <LinkCard
            icon={MessageSquare}
            title="Caixa de Entrada"
            description="Gerencie as conversas recebidas."
            to="/whatsapp/inbox"
            accent="bg-primary-soft text-primary"
          />
          <LinkCard
            icon={KanbanSquare}
            title="Kanban"
            description="Organize conversas por etapa."
            to="/whatsapp/kanban"
            accent="bg-info-soft text-info"
          />
          <LinkCard
            icon={Tags}
            title="Tags"
            description="Crie e gerencie tags de categorização."
            to="/whatsapp/tags"
            accent="bg-success-soft text-success"
          />
          <LinkCard
            icon={Settings}
            title="Configurações"
            description="Gerencie conexões com o WhatsApp."
            to="/whatsapp/configs"
            accent="bg-surface-2 text-muted"
          />
        </div>
      </PageContent>
    </Page>
  )
}

function StatCard({
  label,
  icon: Icon,
  value,
  loading,
  accent,
}: {
  label: string
  icon: LucideIcon
  value: number | string | undefined
  loading: boolean
  accent: string
}) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4">
        <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl', accent)}>
          <Icon className="size-5.5" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <p className="text-[13px] text-muted">{label}</p>
          {loading ? (
            <Skeleton className="mt-1 h-7 w-14" />
          ) : (
            <p className="text-2xl font-semibold tracking-tight text-foreground">
              {value ?? '—'}
            </p>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function LinkCard({
  icon: Icon,
  title,
  description,
  to,
  accent,
}: {
  icon: LucideIcon
  title: string
  description: string
  to: string
  accent: string
}) {
  return (
    <Card className="transition-shadow hover:shadow-raised">
      <Link to={to} className="block rounded-xl">
        <CardContent className="flex flex-col gap-3 p-5">
          <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', accent)}>
            <Icon className="size-5" aria-hidden="true" />
          </span>
          <div>
            <h3 className="font-semibold text-foreground">{title}</h3>
            <p className="mt-1 text-sm text-muted">{description}</p>
          </div>
        </CardContent>
      </Link>
    </Card>
  )
}

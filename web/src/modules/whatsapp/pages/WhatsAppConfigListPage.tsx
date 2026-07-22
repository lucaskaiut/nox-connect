import { useState } from 'react'
import { MessageCircle, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDate } from '@/shared/utils/format'
import type { WhatsAppConfig } from '@/shared/types/models'
import { useDeleteWhatsAppConfig, useWhatsAppConfigsQuery } from '../hooks/useWhatsApp'

export default function WhatsAppConfigListPage() {
  const query = useWhatsAppConfigsQuery()
  const deleteConfig = useDeleteWhatsAppConfig()
  const { can } = usePermissions()

  const [configToDelete, setConfigToDelete] = useState<WhatsAppConfig | null>(null)

  const confirmDelete = () => {
    if (!configToDelete) return
    deleteConfig.mutate(configToDelete.id, { onSettled: () => setConfigToDelete(null) })
  }

  const columns: Array<Column<WhatsAppConfig>> = [
    {
      key: 'name',
      header: 'Nome',
      render: (config) => (
        <div className="flex items-center gap-3">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted">
            <MessageCircle className="size-4" aria-hidden="true" />
          </span>
          <span className="font-medium text-foreground">{config.name}</span>
        </div>
      ),
    },
    {
      key: 'waba_id',
      header: 'WABA ID',
      render: (config) => (
        <span className="max-w-40 truncate text-muted" title={config.waba_id}>
          {config.waba_id}
        </span>
      ),
    },
    {
      key: 'phone_number_id',
      header: 'Phone Number ID',
      render: (config) => (
        <span className="max-w-40 truncate text-muted" title={config.phone_number_id}>
          {config.phone_number_id}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (config) =>
        config.is_active ? (
          <Badge variant="success">Ativo</Badge>
        ) : (
          <Badge>Inativo</Badge>
        ),
    },
    {
      key: 'last_connected_at',
      header: 'Última conexão',
      render: (config) => (
        <span className="text-muted">
          {config.last_connected_at ? formatDate(config.last_connected_at) : '-'}
        </span>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-20 text-right',
      render: (config: WhatsAppConfig) => (
        <div className="flex items-center justify-end gap-1">
          {can(Permission.WHATSAPP_CONFIG_UPDATE) && (
            <ButtonLink to={`/whatsapp/configs/${config.id}/edit`} variant="ghost" size="sm">
              <Pencil className="size-4" />
            </ButtonLink>
          )}
          {can(Permission.WHATSAPP_CONFIG_DELETE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setConfigToDelete(config)}
              aria-label={`Remover configuração ${config.name}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Configurações do WhatsApp"
        description="Gerencie as conexões com a API do WhatsApp Business."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Configurações' },
        ]}
        actions={
          <Can permission={Permission.WHATSAPP_CONFIG_CREATE}>
            <ButtonLink to="/whatsapp/configs/create">
              <Plus className="size-4" />
              Nova configuração
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          caption="Lista de configurações do WhatsApp"
          columns={columns}
          rows={query.data ?? []}
          rowKey={(config) => config.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={MessageCircle}
              title="Nenhuma configuração de WhatsApp"
              description="Conecte uma conta do WhatsApp Business para começar a gerenciar conversas."
              action={
                <Can permission={Permission.WHATSAPP_CONFIG_CREATE}>
                  <ButtonLink to="/whatsapp/configs/create">
                    <Plus className="size-4" />
                    Nova configuração
                  </ButtonLink>
                </Can>
              }
            />
          }
        />
      </PageContent>

      <ConfirmDialog
        open={configToDelete !== null}
        onClose={() => setConfigToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteConfig.isPending}
        title="Remover configuração"
        description={
          <>
            Tem certeza que deseja remover a configuração{' '}
            <strong>{configToDelete?.name}</strong>? A integração com o WhatsApp será desativada
            para esta conexão.
          </>
        }
        confirmLabel="Remover"
      />
    </Page>
  )
}

import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useParams } from 'react-router'
import { CheckCircle2, Clock, RefreshCw, Wifi, XCircle, AlertTriangle } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  Skeleton,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { formatDate, formatRelative } from '@/shared/utils/format'
import {
  useTestConnection,
  useToggleConfig,
  useUpdateWhatsAppConfig,
  useWhatsAppConfigQuery,
  useWebhookLogsQuery,
} from '../hooks/useWhatsApp'
import {
  whatsappConfigSchema,
  type WhatsAppConfigFormValues,
} from '../schemas/whatsapp.schema'

function generateVerifyToken(): string {
  const chars = 'abcdef0123456789'

  return Array.from(crypto.getRandomValues(new Uint8Array(32)))
    .map((b) => chars[b % chars.length])
    .join('')
}

type Tab = 'config' | 'logs'

export default function WhatsAppConfigEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const configId = Number(id)
  const updateConfig = useUpdateWhatsAppConfig()
  const { data: config, isPending } = useWhatsAppConfigQuery(configId)
  const testConnection = useTestConnection()
  const toggleConfig = useToggleConfig()
  const { data: logs, isPending: logsPending } = useWebhookLogsQuery(configId)

  const [activeTab, setActiveTab] = useState<Tab>('config')
  const [connectionStatus, setConnectionStatus] = useState<'idle' | 'success' | 'error'>('idle')
  const [connectionMessage, setConnectionMessage] = useState('')
  const [expandedLog, setExpandedLog] = useState<number | null>(null)

  const form = useForm<WhatsAppConfigFormValues>({
    resolver: zodResolver(whatsappConfigSchema),
    defaultValues: {
      name: '',
      waba_id: '',
      phone_number_id: '',
      access_token: '',
      verify_token: '',
    },
  })

  useEffect(() => {
    if (!config) return
    form.reset({
      name: config.name,
      waba_id: config.waba_id,
      phone_number_id: config.phone_number_id,
      access_token: '',
      verify_token: '',
    })
  }, [config, form])

  const onSubmit = async (values: WhatsAppConfigFormValues) => {
    try {
      await updateConfig.mutateAsync({ id: configId, ...values })
      navigate('/whatsapp/configs')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const handleTestConnection = async () => {
    setConnectionStatus('idle')
    setConnectionMessage('')
    try {
      const result = await testConnection.mutateAsync(configId)
      setConnectionStatus('success')
      setConnectionMessage(result.message ?? 'Conexão estabelecida com sucesso.')
    } catch {
      setConnectionStatus('error')
      setConnectionMessage('Falha ao testar a conexão. Verifique as credenciais.')
    }
  }

  const handleToggle = async () => {
    await toggleConfig.mutateAsync(configId)
  }

  if (isPending) return <Loading />

  return (
    <Page>
      <PageHeader
        title="Editar configuração do WhatsApp"
        description={`Editando ${config?.name ?? '...'}`}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Configurações', to: '/whatsapp/configs' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        <div className="mb-6 flex gap-1 rounded-lg bg-surface-2 p-1">
          {(['config', 'logs'] as const).map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                activeTab === tab
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted hover:text-foreground'
              }`}
            >
              {tab === 'config' ? 'Configuração' : 'Chamadas do Webhook'}
            </button>
          ))}
        </div>

        {activeTab === 'config' ? (
          <>
            <Card>
              <CardContent>
                <Form form={form} onSubmit={onSubmit} className="space-y-8">
                  <Section title="Identificação">
                    <div className="grid gap-4 sm:grid-cols-2">
                      <TextField
                        name="name"
                        label="Nome"
                        required
                        className="sm:col-span-2"
                      />
                      <TextField
                        name="waba_id"
                        label="WhatsApp Business Account ID"
                        required
                      />
                      <TextField
                        name="phone_number_id"
                        label="Phone Number ID"
                        required
                      />
                    </div>
                  </Section>

                  <Section
                    title="Credenciais da API"
                    description="Deixe os campos em branco para manter as credenciais atuais."
                  >
                    <div className="grid gap-4 sm:grid-cols-2">
                      <TextField
                        name="access_token"
                        label="Access Token"
                        placeholder="Deixe em branco para manter o atual"
                        className="sm:col-span-2"
                      />
                      <div className="flex items-start gap-2 sm:col-span-2">
                        <div className="flex-1">
                          <TextField
                            name="verify_token"
                            label="Verify Token"
                            placeholder="Deixe em branco para manter o atual ou gere um novo"
                            className="w-full"
                          />
                        </div>
                        <Button
                          type="button"
                          variant="secondary"
                          size="sm"
                          className="mt-[26px] shrink-0"
                          onClick={() => form.setValue('verify_token', generateVerifyToken())}
                        >
                          <RefreshCw className="size-4" />
                          Gerar
                        </Button>
                      </div>
                    </div>
                  </Section>

                  <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <ButtonLink to="/whatsapp/configs" variant="secondary">
                      Cancelar
                    </ButtonLink>
                    <Button type="submit" loading={updateConfig.isPending}>
                      Salvar
                    </Button>
                  </div>
                </Form>
              </CardContent>
            </Card>

            <Card className="mt-6">
              <CardContent>
                <Section title="Ações" description="Teste a conexão ou alterne o status da configuração.">
                  <div className="flex flex-wrap items-center gap-3">
                    <Button
                      variant="secondary"
                      onClick={handleTestConnection}
                      loading={testConnection.isPending}
                    >
                      <Wifi className="size-4" />
                      Testar conexão
                    </Button>
                    <Button
                      variant={config?.is_active ? 'secondary' : 'primary'}
                      onClick={handleToggle}
                      loading={toggleConfig.isPending}
                    >
                      {config?.is_active ? 'Desativar' : 'Ativar'}
                    </Button>
                    <Badge variant={config?.is_active ? 'success' : undefined}>
                      {config?.is_active ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </div>

                  {connectionStatus !== 'idle' && (
                    <div className="mt-3 flex items-center gap-2">
                      {connectionStatus === 'success' ? (
                        <CheckCircle2 className="size-4 shrink-0 text-success" />
                      ) : (
                        <XCircle className="size-4 shrink-0 text-danger" />
                      )}
                      <span className="text-sm text-muted">{connectionMessage}</span>
                    </div>
                  )}
                </Section>
              </CardContent>
            </Card>
          </>
        ) : (
          <Card>
            <CardHeader
              title="Histórico de chamadas do webhook"
              description="Últimas 100 requisições recebidas da Meta para esta configuração."
            />
            <CardContent className="p-0">
              {logsPending ? (
                <div className="space-y-3 p-5">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-16 w-full" />
                  ))}
                </div>
              ) : !logs || logs.length === 0 ? (
                <p className="p-5 text-sm text-muted">Nenhuma chamada registrada ainda.</p>
              ) : (
                <div className="divide-y divide-surface-2">
                  {logs.map((log) => (
                    <LogEntry
                      key={log.id}
                      log={log}
                      expanded={expandedLog === log.id}
                      onToggle={() => setExpandedLog(expandedLog === log.id ? null : log.id)}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}

function LogEntry({
  log,
  expanded,
  onToggle,
}: {
  log: {
    id: number
    method: string
    response_status: number | null
    error_message: string | null
    duration_ms: number | null
    request_payload: Record<string, unknown> | null
    response_body: string | null
    created_at: string | null
  }
  expanded: boolean
  onToggle: () => void
}) {
  const isSuccess = log.response_status !== null && log.response_status >= 200 && log.response_status < 300
  const isError = log.error_message !== null || (log.response_status !== null && log.response_status >= 400)

  return (
    <div>
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center gap-3 px-5 py-3 text-left transition-colors hover:bg-surface-2/40"
      >
        {isSuccess ? (
          <CheckCircle2 className="size-4 shrink-0 text-success" />
        ) : isError ? (
          <XCircle className="size-4 shrink-0 text-danger" />
        ) : (
          <AlertTriangle className="size-4 shrink-0 text-warning" />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <Badge variant={log.method === 'GET' ? 'primary' : 'neutral'} className="text-[11px]">
              {log.method}
            </Badge>
            <Badge variant={isSuccess ? 'success' : isError ? 'danger' : 'warning'} className="text-[11px]">
              {log.response_status ?? 'ERRO'}
            </Badge>
            <span className="text-xs text-muted">
              <Clock className="mr-1 inline size-3" />
              {log.duration_ms !== null ? `${log.duration_ms}ms` : '-'}
            </span>
          </div>
          <p className="mt-0.5 truncate text-xs text-muted">
            {log.error_message ?? formatRelative(log.created_at!)}
          </p>
        </div>
      </button>
      {expanded && (
        <div className="space-y-3 bg-surface-2/30 px-5 py-4">
          {log.error_message && (
            <div>
              <p className="mb-1 text-xs font-medium text-danger">Erro</p>
              <pre className="whitespace-pre-wrap rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {log.error_message}
              </pre>
            </div>
          )}
          {log.request_payload && (
            <div>
              <p className="mb-1 text-xs font-medium text-muted">Payload recebido</p>
              <pre className="max-h-48 overflow-auto rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {JSON.stringify(log.request_payload, null, 2)}
              </pre>
            </div>
          )}
          {log.response_body && (
            <div>
              <p className="mb-1 text-xs font-medium text-muted">Resposta</p>
              <pre className="max-h-48 overflow-auto rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {log.response_body}
              </pre>
            </div>
          )}
          <p className="text-xs text-muted">
            Recebido em {log.created_at ? formatDate(log.created_at) : '-'}
          </p>
        </div>
      )}
    </div>
  )
}

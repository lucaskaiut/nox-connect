import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useParams } from 'react-router'
import { CheckCircle2, Wifi, XCircle } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import {
  useTestConnection,
  useToggleConfig,
  useUpdateWhatsAppConfig,
  useWhatsAppConfigQuery,
} from '../hooks/useWhatsApp'
import {
  whatsappConfigSchema,
  type WhatsAppConfigFormValues,
} from '../schemas/whatsapp.schema'

export default function WhatsAppConfigEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const updateConfig = useUpdateWhatsAppConfig()
  const { data: config, isPending } = useWhatsAppConfigQuery(Number(id))
  const testConnection = useTestConnection()
  const toggleConfig = useToggleConfig()

  const [connectionStatus, setConnectionStatus] = useState<'idle' | 'success' | 'error'>('idle')
  const [connectionMessage, setConnectionMessage] = useState('')

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
      await updateConfig.mutateAsync({ id: Number(id), ...values })
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
      const result = await testConnection.mutateAsync(Number(id))
      setConnectionStatus('success')
      setConnectionMessage(result.message ?? 'Conexão estabelecida com sucesso.')
    } catch {
      setConnectionStatus('error')
      setConnectionMessage('Falha ao testar a conexão. Verifique as credenciais.')
    }
  }

  const handleToggle = async () => {
    await toggleConfig.mutateAsync(Number(id))
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
                  <TextField
                    name="verify_token"
                    label="Verify Token"
                    placeholder="Deixe em branco para manter o atual"
                    className="sm:col-span-2"
                  />
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
      </PageContent>
    </Page>
  )
}

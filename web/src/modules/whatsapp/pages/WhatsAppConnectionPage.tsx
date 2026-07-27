import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { RefreshCw, Wifi } from 'lucide-react'
import {
  Badge,
  Button,
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
  useConnectWhatsApp,
  useDisconnectWhatsApp,
  useTestWhatsAppConnection,
  useWhatsAppConnectionQuery,
  useWebhookLogsQuery,
} from '../hooks/useWhatsApp'
import {
  whatsappDApiConnectSchema,
  whatsappMetaConnectSchema,
  type WhatsAppDApiConnectFormValues,
  type WhatsAppMetaConnectFormValues,
} from '../schemas/whatsapp.schema'

function generateVerifyToken(): string {
  const chars = 'abcdef0123456789'
  return Array.from(crypto.getRandomValues(new Uint8Array(32)))
    .map((b) => chars[b % chars.length])
    .join('')
}

export default function WhatsAppConnectionPage() {
  const { data: connection, isPending } = useWhatsAppConnectionQuery()
  const connect = useConnectWhatsApp()
  const disconnect = useDisconnectWhatsApp()
  const test = useTestWhatsAppConnection()
  const { data: logs } = useWebhookLogsQuery()

  const [message, setMessage] = useState('')
  const isDApi = (connection?.provider ?? '') === 'd-api'

  const metaForm = useForm<WhatsAppMetaConnectFormValues>({
    resolver: zodResolver(whatsappMetaConnectSchema),
    defaultValues: {
      account_id: '',
      channel_id: '',
      webhook_verify_token: '',
    },
  })

  const dapiForm = useForm<WhatsAppDApiConnectFormValues>({
    resolver: zodResolver(whatsappDApiConnectSchema),
    defaultValues: {
      session_id: '',
      connection_id: '',
      webhook_verify_token: '',
    },
  })

  useEffect(() => {
    if (!connection?.settings) return

    if (isDApi) {
      dapiForm.reset({
        session_id: String(connection.settings.session_id ?? ''),
        connection_id: String(connection.settings.connection_id ?? ''),
        webhook_verify_token: '',
      })
      return
    }

    metaForm.reset({
      account_id: String(connection.settings.account_id ?? ''),
      channel_id: String(connection.settings.channel_id ?? ''),
      webhook_verify_token: '',
    })
  }, [connection, isDApi, metaForm, dapiForm])

  const onSubmitMeta = async (values: WhatsAppMetaConnectFormValues) => {
    try {
      await connect.mutateAsync(values)
      setMessage('Conexão estabelecida.')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(metaForm, error)
      }
    }
  }

  const onSubmitDApi = async (values: WhatsAppDApiConnectFormValues) => {
    try {
      await connect.mutateAsync({
        session_id: values.session_id || undefined,
        connection_id: values.connection_id || undefined,
        webhook_verify_token: values.webhook_verify_token || undefined,
      })
      setMessage('Conexão estabelecida.')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(dapiForm, error)
      }
    }
  }

  if (isPending) return <Loading />

  const form = isDApi ? dapiForm : metaForm
  const onSubmit = isDApi ? onSubmitDApi : onSubmitMeta

  return (
    <Page>
      <PageHeader
        title="Conexão WhatsApp"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Conexão' },
        ]}
      />

      <PageContent className="space-y-6">
        <Card>
          <CardContent>
            <Form form={form as never} onSubmit={onSubmit as never} className="space-y-8">
              <div className="flex flex-wrap gap-2">
                <Button type="submit" loading={connect.isPending}>
                  {connection?.connected ? 'Atualizar conexão' : 'Conectar'}
                </Button>
                {connection?.connected && (
                  <Button
                    type="button"
                    variant="secondary"
                    loading={disconnect.isPending}
                    onClick={() => disconnect.mutate()}
                  >
                    Desconectar
                  </Button>
                )}
              </div>
              {message && <p className="text-sm text-muted">{message}</p>}
            </Form>
          </CardContent>
        </Card>

        {logs && logs.length > 0 && (
          <Card>
            <CardContent>
              <Section title="Últimas chamadas do webhook">
                <ul className="space-y-2 text-sm">
                  {logs.slice(0, 10).map((log) => (
                    <li key={log.id} className="flex justify-between gap-4 text-muted">
                      <span>
                        {log.method} · {log.response_status}
                      </span>
                      <span>{log.created_at}</span>
                    </li>
                  ))}
                </ul>
              </Section>
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router'
import { RefreshCw } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Page,
  PageContent,
  PageHeader,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCreateWhatsAppConfig } from '../hooks/useWhatsApp'
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

export default function WhatsAppConfigCreatePage() {
  const navigate = useNavigate()
  const createConfig = useCreateWhatsAppConfig()

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

  const onSubmit = async (values: WhatsAppConfigFormValues) => {
    try {
      await createConfig.mutateAsync(values)
      navigate('/whatsapp/configs')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Nova configuração do WhatsApp"
        description="Conecte uma conta do WhatsApp Business para gerenciar conversas pelo painel."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Configurações', to: '/whatsapp/configs' },
          { label: 'Nova configuração' },
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
                    placeholder="Ex.: WhatsApp Principal"
                    required
                    className="sm:col-span-2"
                  />
                  <TextField
                    name="waba_id"
                    label="WhatsApp Business Account ID"
                    placeholder="ID da conta de negócios do WhatsApp"
                    required
                  />
                  <TextField
                    name="phone_number_id"
                    label="Phone Number ID"
                    placeholder="ID do número de telefone"
                    required
                  />
                </div>
              </Section>

              <Section
                title="Credenciais da API"
                description="Tokens gerados no painel do Meta for Developers para autenticar as chamadas à API."
              >
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="access_token"
                    label="Access Token"
                    placeholder="Token de acesso permanente da API"
                    required
                    className="sm:col-span-2"
                  />
                  <div className="flex items-start gap-2 sm:col-span-2">
                    <div className="flex-1">
                      <TextField
                        name="verify_token"
                        label="Verify Token"
                        placeholder="Token gerado automaticamente para o webhook"
                        hint="Este token será usado para verificar a URL do webhook na Meta. Salve-o antes de configurar no Meta Developer."
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
                <Button type="submit" loading={createConfig.isPending}>
                  Criar configuração
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}

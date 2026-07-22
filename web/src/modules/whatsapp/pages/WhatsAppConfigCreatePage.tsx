import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router'
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
                  <TextField
                    name="verify_token"
                    label="Verify Token"
                    placeholder="Token de verificação do webhook"
                    required
                    className="sm:col-span-2"
                  />
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

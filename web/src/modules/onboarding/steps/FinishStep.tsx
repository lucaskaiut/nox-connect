import { CheckCircle2, Circle } from 'lucide-react'
import { Button, Section } from '@/shared/design-system'
import { useFinishOnboarding } from '../hooks/useOnboarding'
import type { OnboardingStatus } from '../whatsapp/types'

export function FinishStep({
  status,
  onFinished,
}: {
  status: OnboardingStatus
  onFinished: () => void
}) {
  const finish = useFinishOnboarding()
  const whatsappReady = status.whatsapp.connected || status.whatsapp_completed

  return (
    <div className="space-y-6">
      <Section
        title={whatsappReady ? 'Tudo pronto' : 'Quase lá'}
        description={
          whatsappReady
            ? 'Revise o resumo e entre na aplicação.'
            : 'Você já pode entrar na aplicação e conectar o WhatsApp depois.'
        }
      >
        <ul className="space-y-2 text-sm text-muted">
          <li className="flex items-center gap-2">
            <CheckCircle2 className="size-4 text-success" />
            Empresa: {status.company.name}
          </li>
          <li className="flex items-center gap-2">
            {whatsappReady ? (
              <CheckCircle2 className="size-4 text-success" />
            ) : (
              <Circle className="size-4 text-muted" />
            )}
            WhatsApp:{' '}
            {whatsappReady
              ? (status.whatsapp.phone_number ?? 'conectado')
              : 'pendente — conecte depois em Conexão'}
          </li>
        </ul>
      </Section>

      <Button
        type="button"
        loading={finish.isPending}
        onClick={async () => {
          await finish.mutateAsync()
          onFinished()
        }}
      >
        Ir para a aplicação
      </Button>
    </div>
  )
}

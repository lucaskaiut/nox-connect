import { CheckCircle2 } from 'lucide-react'
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

  return (
    <div className="space-y-6">
      <Section
        title="Tudo pronto"
        description="Revise o resumo e entre na aplicação."
      >
        <ul className="space-y-2 text-sm text-muted">
          <li className="flex items-center gap-2">
            <CheckCircle2 className="size-4 text-success" />
            Empresa: {status.company.name}
          </li>
          <li className="flex items-center gap-2">
            <CheckCircle2 className="size-4 text-success" />
            WhatsApp: {status.whatsapp.phone_number ?? status.whatsapp.connection_id ?? 'conectado'}
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

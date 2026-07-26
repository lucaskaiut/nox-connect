import { useMemo } from 'react'
import { Navigate, useNavigate } from 'react-router'
import {
  Card,
  CardContent,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { useOnboardingQuery } from '../hooks/useOnboarding'
import { CompanyStep } from '../steps/CompanyStep'
import { WhatsAppConnectionStep } from '../steps/WhatsAppConnectionStep'
import { FinishStep } from '../steps/FinishStep'

const STEPS = [
  { id: 'company', label: 'Empresa' },
  { id: 'whatsapp', label: 'WhatsApp' },
  { id: 'finish', label: 'Concluir' },
] as const

export default function OnboardingPage() {
  const navigate = useNavigate()
  const { data: status, isPending, refetch } = useOnboardingQuery()

  const step = status?.current_step ?? 'company'
  const activeIndex = useMemo(() => {
    const idx = STEPS.findIndex((s) => s.id === step)
    return idx >= 0 ? idx : 0
  }, [step])

  if (isPending) return <Loading />

  if (!status) {
    return <Navigate to="/dashboard" replace />
  }

  if (status.completed) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <Page>
      <PageHeader
        title="Bem-vindo"
        description="Configure sua empresa e conecte o WhatsApp para começar."
      />

      <PageContent className="mx-auto max-w-2xl space-y-6">
        <nav aria-label="Progresso do onboarding">
          <ol className="flex gap-2">
            {STEPS.map((item, index) => {
              const done = index < activeIndex
              const current = index === activeIndex

              return (
                <li
                  key={item.id}
                  className={[
                    'flex-1 rounded-lg px-3 py-2 text-center text-sm',
                    current ? 'bg-primary text-primary-foreground' : '',
                    done ? 'bg-success-soft text-success' : '',
                    !current && !done ? 'bg-surface-2 text-muted' : '',
                  ].join(' ')}
                >
                  {item.label}
                </li>
              )
            })}
          </ol>
        </nav>

        <Card>
          <CardContent className="p-6">
            {step === 'company' && (
              <CompanyStep status={status} onCompleted={() => refetch()} />
            )}
            {step === 'whatsapp' && (
              <WhatsAppConnectionStep status={status} onCompleted={() => refetch()} />
            )}
            {step === 'finish' && (
              <FinishStep
                status={status}
                onFinished={() => navigate('/dashboard', { replace: true })}
              />
            )}
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}

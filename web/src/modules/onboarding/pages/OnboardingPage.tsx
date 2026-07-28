import { useEffect, useMemo } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { Check } from 'lucide-react'
import {
  Card,
  CardContent,
  Loading,
  Page,
  PageContent,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
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

  useEffect(() => {
    if (status?.completed) {
      navigate('/dashboard', { replace: true })
    }
  }, [status?.completed, navigate])

  if (isPending) return <Loading />

  if (!status) {
    return <Navigate to="/dashboard" replace />
  }

  if (status.completed) {
    return <Loading />
  }

  return (
    <Page>
      <PageContent className="mx-auto w-full max-w-3xl space-y-6 mt-4">
        <nav aria-label="Progresso do onboarding">
          <ol className="flex gap-2">
            {STEPS.map((item, index) => {
              const done = index < activeIndex
              const current = index === activeIndex

              return (
                <li
                  key={item.id}
                  className={cn(
                    'flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                    current && 'bg-primary text-primary-foreground shadow-card',
                    done && 'bg-success-soft text-success',
                    !current && !done && 'bg-surface-2 text-muted',
                  )}
                >
                  <span
                    className={cn(
                      'flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                      current && 'bg-primary-foreground/20 text-primary-foreground',
                      done && 'bg-success text-white',
                      !current && !done && 'bg-surface-3 text-muted',
                    )}
                    aria-hidden="true"
                  >
                    {done ? <Check className="size-3" /> : index + 1}
                  </span>
                  {item.label}
                </li>
              )
            })}
          </ol>
        </nav>

        <Card>
          <CardContent className="p-6 sm:p-8">
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

import { useNavigate } from 'react-router'
import {
  Bell,
  CheckCircle2,
  MessageCircle,
  MessagesSquare,
  Sparkles,
  Wifi,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Alert, Badge, Button, Form, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { WhatsAppConnectionManager } from '../whatsapp/WhatsAppConnectionManager'
import {
  useCompleteWhatsApp,
  useFinishOnboarding,
  useInitializeWhatsApp,
} from '../hooks/useOnboarding'
import type { ConnectionBootstrap, OnboardingStatus } from '../whatsapp/types'

const BENEFITS = [
  {
    icon: MessageCircle,
    title: 'Receber mensagens e notificações',
    description: 'Acompanhe conversas e alertas direto no WhatsApp da empresa.',
  },
  {
    icon: Bell,
    title: 'Automatizar respostas e confirmações',
    description: 'Reduza o trabalho manual com confirmações e respostas rápidas.',
  },
  {
    icon: MessagesSquare,
    title: 'Centralizar a comunicação',
    description: 'Reúna o atendimento da empresa em um só lugar na plataforma.',
  },
] as const

export function WhatsAppConnectionStep({
  status,
  onCompleted,
}: {
  status: OnboardingStatus
  onCompleted: () => void
}) {
  const initialize = useInitializeWhatsApp()
  const complete = useCompleteWhatsApp()
  const finish = useFinishOnboarding()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [bootstrap, setBootstrap] = useState<ConnectionBootstrap | null>(null)

  const form = useForm<Record<string, string>>({
    defaultValues: {},
  })

  useEffect(() => {
    let cancelled = false

    initialize
      .mutateAsync()
      .then((init) => {
        if (!cancelled) setBootstrap(init)
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Falha ao inicializar conexão.')
        }
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const startConnection = async (formValues?: Record<string, string>) => {
    setError(null)

    try {
      const init = bootstrap ?? (await initialize.mutateAsync())
      setBootstrap(init)

      const manager = WhatsAppConnectionManager.create({
        ...init,
        configuration: {
          ...init.configuration,
          ...(formValues ? { __formValues: formValues } : {}),
        },
      })

      const result = await manager.start()

      const connectionNonce =
        typeof init.configuration.connection_nonce === 'string'
          ? init.configuration.connection_nonce
          : undefined

      await complete.mutateAsync({
        connection_id: result.connectionId,
        phone_number: result.phoneNumber,
        status: result.status ?? 'connected',
        ...(result.payload ?? {}),
        ...(connectionNonce ? { connection_nonce: connectionNonce } : {}),
      })

      onCompleted()
    } catch (err) {
      if (isApiError(err)) {
        setError(err.message)
        return
      }

      const message = err instanceof Error ? err.message : 'Falha ao conectar WhatsApp.'
      setError(message)
    }
  }

  const skipForLater = async () => {
    setError(null)

    try {
      await finish.mutateAsync()
      navigate('/dashboard', { replace: true })
    } catch (err) {
      if (isApiError(err)) {
        setError(err.message)
        return
      }

      setError(err instanceof Error ? err.message : 'Não foi possível continuar sem WhatsApp.')
    }
  }

  if (status.whatsapp.connected) {
    return (
      <div className="space-y-6">
        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-success-soft text-success">
            <CheckCircle2 className="size-7" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-lg font-semibold tracking-tight text-foreground">
                WhatsApp conectado
              </h2>
              <Badge variant="success">Ativo</Badge>
            </div>
            <p className="mt-1 text-sm text-muted">
              Número: {status.whatsapp.phone_number ?? '—'}
            </p>
          </div>
        </div>

        <Button type="button" onClick={onCompleted}>
          Continuar
        </Button>
      </div>
    )
  }

  const isFormStrategy = bootstrap?.type === 'form'
  const fields = Array.isArray(bootstrap?.configuration?.fields)
    ? (bootstrap.configuration.fields as Array<{ name: string; label: string; required?: boolean }>)
    : []
  const busy = initialize.isPending || complete.isPending || finish.isPending
  const connecting = initialize.isPending || complete.isPending || !bootstrap

  return (
    <div className="space-y-8">
      <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
        <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary shadow-card">
          <MessageCircle className="size-7" aria-hidden="true" />
        </span>

        <div className="min-w-0 flex-1 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="primary">
              <Sparkles className="size-3" aria-hidden="true" />
              Próximo passo
            </Badge>
            <Badge variant="neutral">Opcional</Badge>
          </div>

          <div>
            <h2 className="text-lg font-semibold tracking-tight text-foreground">
              Conectar WhatsApp
            </h2>
            <p className="mt-1.5 max-w-xl text-sm leading-relaxed text-muted">
              Conectar seu WhatsApp agora acelera a ativação da empresa, mas você também pode
              concluir isso depois. Seu acesso já está liberado.
            </p>
          </div>
        </div>
      </div>

      <ul className="grid gap-3 sm:grid-cols-3">
        {BENEFITS.map(({ icon: Icon, title, description }) => (
          <li
            key={title}
            className="rounded-xl bg-surface-2/70 px-4 py-3.5"
          >
            <span className="flex size-8 items-center justify-center rounded-lg bg-surface text-primary shadow-card">
              <Icon className="size-4" aria-hidden="true" />
            </span>
            <p className="mt-3 text-sm font-medium text-foreground">{title}</p>
            <p className="mt-1 text-[13px] leading-snug text-muted">{description}</p>
          </li>
        ))}
      </ul>

      {error && (
        <Alert variant="warning" title="Não foi possível conectar agora">
          <p>{error}</p>
          <p className="mt-1">
            Você pode tentar novamente ou seguir sem conectar e concluir essa etapa depois.
          </p>
        </Alert>
      )}

      {isFormStrategy ? (
        <Form
          form={form}
          onSubmit={(values) => startConnection(values)}
          className="space-y-5"
        >
          <div className="grid gap-4 sm:grid-cols-2">
            {fields.map((field) => (
              <TextField
                key={field.name}
                name={field.name}
                label={field.label}
                required={field.required}
              />
            ))}
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Button type="submit" loading={busy} className="min-w-44">
              <Wifi className="size-4" />
              Conectar WhatsApp
            </Button>
            <Button
              type="button"
              variant="ghost"
              loading={finish.isPending}
              onClick={skipForLater}
            >
              Fazer isso depois
            </Button>
          </div>
        </Form>
      ) : (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <Button
            type="button"
            loading={connecting}
            disabled={!bootstrap || finish.isPending}
            onClick={() => startConnection()}
            className="min-w-44"
          >
            <Wifi className="size-4" />
            Conectar WhatsApp
          </Button>
          <Button
            type="button"
            variant="ghost"
            loading={finish.isPending}
            onClick={skipForLater}
          >
            Fazer isso depois
          </Button>
        </div>
      )}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Wifi } from 'lucide-react'
import { Button, Form, Section, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { WhatsAppConnectionManager } from '../whatsapp/WhatsAppConnectionManager'
import {
  useCompleteWhatsApp,
  useInitializeWhatsApp,
} from '../hooks/useOnboarding'
import type { ConnectionBootstrap, OnboardingStatus } from '../whatsapp/types'

export function WhatsAppConnectionStep({
  status,
  onCompleted,
}: {
  status: OnboardingStatus
  onCompleted: () => void
}) {
  const initialize = useInitializeWhatsApp()
  const complete = useCompleteWhatsApp()
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

      console.info('[WhatsApp:Onboarding] SDK ok, persistindo no backend', result)

      await complete.mutateAsync({
        connection_id: result.connectionId,
        phone_number: result.phoneNumber,
        status: result.status ?? 'connected',
        ...(result.payload ?? {}),
      })

      onCompleted()
    } catch (err) {
      console.error('[WhatsApp:Onboarding] falha no fluxo de conexão', {
        err,
        bootstrap,
        isApiError: isApiError(err),
      })

      if (isApiError(err)) {
        setError(err.message)
        return
      }

      const message = err instanceof Error ? err.message : 'Falha ao conectar WhatsApp.'
      setError(message)
    }
  }

  if (status.whatsapp.connected) {
    return (
      <div className="space-y-4">
        <Section
          title="WhatsApp conectado"
          description={`Número: ${status.whatsapp.phone_number ?? '—'} · ID: ${status.whatsapp.connection_id ?? '—'}`}
        />
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

  return (
    <div className="space-y-6">
      <Section
        title="Conectar WhatsApp"
        description="O provedor ativo é definido pela aplicação. Este passo usa a estratégia retornada pelo backend (SDK, formulário, etc.)."
      >
        <p className="text-sm text-muted">
          Provider: <span className="font-medium text-foreground">{status.provider}</span>
        </p>
        {bootstrap?.configuration?.pending_webhook_url != null && (
          <p className="mt-2 break-all text-xs text-muted">
            Webhook (após conexão): {String(bootstrap.configuration.pending_webhook_url)}
          </p>
        )}
      </Section>

      {isFormStrategy ? (
        <Form
          form={form}
          onSubmit={(values) => startConnection(values)}
          className="space-y-4"
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
          <Button type="submit" loading={initialize.isPending || complete.isPending}>
            Conectar
          </Button>
        </Form>
      ) : (
        <Button
          type="button"
          loading={initialize.isPending || complete.isPending || !bootstrap}
          disabled={!bootstrap}
          onClick={() => startConnection()}
        >
          <Wifi className="size-4" />
          Conectar WhatsApp
        </Button>
      )}

      {error && <p className="text-sm text-danger">{error}</p>}
    </div>
  )
}

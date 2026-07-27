import { MessageCircle } from 'lucide-react'
import { Alert, ButtonLink } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'

/**
 * Aviso para retomar a configuração quando o WhatsApp ainda não foi conectado.
 */
export function SetupContinuationBanner() {
  const isUmbrella = useIsUmbrellaTenant()
  const { can } = usePermissions()
  const onboarding = useSessionStore((state) => state.onboarding)

  if (isUmbrella) return null
  if (!onboarding?.completed) return null
  if (onboarding.whatsapp_completed || onboarding.whatsapp?.connected) return null
  if (!can(Permission.WHATSAPP_CONFIG_READ)) return null

  return (
    <Alert variant="warning" title="Configuração pendente" className="mb-4">
      <p>
        Sua conta já está ativa, mas o WhatsApp ainda não foi conectado. Conclua essa etapa para
        usar a caixa de entrada e o kanban.
      </p>
      <div className="mt-3">
        <ButtonLink to="/whatsapp/connection" variant="secondary" size="sm">
          <MessageCircle className="size-3.5" />
          Continuar configuração
        </ButtonLink>
      </div>
    </Alert>
  )
}

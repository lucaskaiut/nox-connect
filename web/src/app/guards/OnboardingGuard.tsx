import { Navigate, Outlet } from 'react-router'
import { useSessionStore } from '@/shared/stores/session.store'
import { FullScreenLoading } from '@/shared/design-system'

/**
 * Redireciona tenants incompletos para /onboarding.
 * Deve ficar dentro de AuthGuard e envolver o AppLayout.
 */
export function OnboardingGuard() {
  const status = useSessionStore((state) => state.status)
  const onboarding = useSessionStore((state) => state.onboarding)
  const tenant = useSessionStore((state) => state.tenant)

  if (status === 'loading') return <FullScreenLoading />

  const needsOnboarding =
    onboarding?.required === true || tenant?.needs_onboarding === true

  if (needsOnboarding) {
    return <Navigate to="/onboarding" replace />
  }

  return <Outlet />
}

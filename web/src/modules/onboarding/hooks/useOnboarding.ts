import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { sessionQueryOptions } from '@/modules/auth/services/auth.service'
import { useSessionStore } from '@/shared/stores/session.store'
import { onboardingService, type CompleteCompanyPayload } from '../services/onboarding.service'

export function useOnboardingQuery() {
  return useQuery({
    queryKey: queryKeys.onboarding.status(),
    queryFn: onboardingService.getStatus,
  })
}

export function useCompleteCompany() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CompleteCompanyPayload) => onboardingService.completeCompany(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.onboarding.all })
      toast.success('Empresa atualizada.')
    },
  })
}

export function useInitializeWhatsApp() {
  return useMutation({
    mutationFn: () => onboardingService.initializeWhatsApp(),
  })
}

export function useCompleteWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => onboardingService.completeWhatsApp(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.onboarding.all })
      toast.success('WhatsApp conectado.')
    },
  })
}

export function useFinishOnboarding() {
  const queryClient = useQueryClient()
  const setSession = useSessionStore((state) => state.setSession)

  return useMutation({
    mutationFn: () => onboardingService.finish(),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.onboarding.all })
      const session = await queryClient.fetchQuery(sessionQueryOptions)
      setSession(session)
      toast.success('Bem-vindo!', 'Você já pode usar a aplicação.')
    },
  })
}

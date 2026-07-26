import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { ConnectionBootstrap, OnboardingStatus } from '../whatsapp/types'

export interface CompleteCompanyPayload {
  name: string
  document: string
  email: string
  phone?: string | null
  domain?: string | null
}

export const onboardingService = {
  async getStatus(): Promise<OnboardingStatus> {
    const response = await http.get<ApiResponse<OnboardingStatus>>('/onboarding')
    return response.data.data
  },

  async completeCompany(payload: CompleteCompanyPayload): Promise<OnboardingStatus> {
    const response = await http.post<ApiResponse<OnboardingStatus>>('/onboarding/company', payload)
    return response.data.data
  },

  async initializeWhatsApp(): Promise<ConnectionBootstrap> {
    const response = await http.get<ApiResponse<ConnectionBootstrap>>('/onboarding/whatsapp/initialize')
    return response.data.data
  },

  async completeWhatsApp(payload: Record<string, unknown>): Promise<OnboardingStatus> {
    const response = await http.post<ApiResponse<OnboardingStatus>>('/onboarding/whatsapp/complete', payload)
    return response.data.data
  },

  async finish(): Promise<OnboardingStatus> {
    const response = await http.post<ApiResponse<OnboardingStatus>>('/onboarding/finish')
    return response.data.data
  },
}

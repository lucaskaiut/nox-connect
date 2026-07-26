import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { Plan } from '@/shared/types/models'

export type CheckoutStep = 0 | 1 | 2 | 3

export interface CompanyData {
  name: string
  document: string
  phone: string
}

export interface UserData {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface PaymentMethodOption {
  id: string
  name: string
  payment_method: string
}

export interface CreditCardPaymentData {
  number: string
  holder_name: string
  exp_month: string
  exp_year: string
  cvv: string
  installments: number
}

interface RegisterCheckoutState {
  step: CheckoutStep
  company: CompanyData
  user: UserData
  selectedPlan: Plan | null
  acceptedTerms: boolean

  setCompany: (data: CompanyData) => void
  setUser: (data: UserData) => void
  setPlan: (plan: Plan) => void
  setAcceptedTerms: (value: boolean) => void
  nextStep: () => void
  previousStep: () => void
  goToStep: (step: CheckoutStep) => void
  reset: () => void
}

const initialCompany: CompanyData = { name: '', document: '', phone: '' }
const initialUser: UserData = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
}

/** Pagamento é escolhido depois, na plataforma — não faz parte do cadastro. */
export const CHECKOUT_STEPS = [
  { id: 0, label: 'Empresa' },
  { id: 1, label: 'Usuário' },
  { id: 2, label: 'Plano' },
  { id: 3, label: 'Confirmar' },
] as const

export const useRegisterCheckoutStore = create<RegisterCheckoutState>()(
  persist(
    (set, get) => ({
      step: 0,
      company: initialCompany,
      user: initialUser,
      selectedPlan: null,
      acceptedTerms: false,

      setCompany: (data) => set({ company: data }),
      setUser: (data) => set({ user: data }),
      setPlan: (plan) => set({ selectedPlan: plan }),
      setAcceptedTerms: (value) => set({ acceptedTerms: value }),

      nextStep: () => {
        const current = get().step
        if (current < 3) set({ step: (current + 1) as CheckoutStep })
      },

      previousStep: () => {
        const current = get().step
        if (current > 0) set({ step: (current - 1) as CheckoutStep })
      },

      goToStep: (step) => set({ step }),

      reset: () =>
        set({
          step: 0,
          company: initialCompany,
          user: initialUser,
          selectedPlan: null,
          acceptedTerms: false,
        }),
    }),
    {
      name: 'nox:register-checkout',
      partialize: (state) => ({
        step: Math.min(state.step, 3) as CheckoutStep,
        company: { ...state.company },
        user: {
          name: state.user.name,
          email: state.user.email,
          password: '',
          password_confirmation: '',
        },
        selectedPlan: state.selectedPlan,
        acceptedTerms: state.acceptedTerms,
      }),
      merge: (persisted, current) => {
        const stored = (persisted ?? {}) as Partial<RegisterCheckoutState>
        // Migra checkout antigo (5 etapas com pagamento) para o novo (4 etapas).
        let step = (stored.step ?? 0) as number
        if (step === 4) step = 3
        if (step === 3 && !('acceptedTerms' in stored)) step = 3

        return {
          ...current,
          ...stored,
          step: Math.min(Math.max(step, 0), 3) as CheckoutStep,
        }
      },
    },
  ),
)

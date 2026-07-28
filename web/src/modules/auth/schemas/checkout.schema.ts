import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

export const companyStepSchema = z.object({
  name: z.string().min(1, 'Informe o nome da empresa'),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  phone: z.string().min(10, 'Informe um telefone válido'),
})

export type CompanyStepValues = z.infer<typeof companyStepSchema>

export const userStepSchema = z
  .object({
    name: z.string().min(1, 'Informe seu nome'),
    email: z.string().min(1, 'Informe seu e-mail').email('Informe um e-mail válido'),
    password: z
      .string()
      .min(8, 'Mínimo de 8 caracteres')
      .regex(/[A-Za-z]/, 'Inclua ao menos uma letra')
      .regex(/[0-9]/, 'Inclua ao menos um número'),
    password_confirmation: z.string().min(1, 'Confirme sua senha'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não coincidem',
  })

export type UserStepValues = z.infer<typeof userStepSchema>

export const creditCardTokenSchema = z.object({
  credit_card_token: z.string().min(1, 'Informe o token do cartão'),
  installments: z.coerce.number().int().min(1).max(12).optional(),
})

export type CreditCardTokenValues = z.infer<typeof creditCardTokenSchema>

export function toCreditCardPaymentData(values: CreditCardTokenValues): Record<string, unknown> {
  return {
    credit_card_token: values.credit_card_token,
    ...(values.installments ? { installments: values.installments } : {}),
  }
}

export const PASSWORD_REQUIREMENTS = [
  { id: 'length', label: 'Pelo menos 8 caracteres', test: (v: string) => v.length >= 8 },
  { id: 'letter', label: 'Pelo menos uma letra', test: (v: string) => /[A-Za-z]/.test(v) },
  { id: 'number', label: 'Pelo menos um número', test: (v: string) => /[0-9]/.test(v) },
] as const

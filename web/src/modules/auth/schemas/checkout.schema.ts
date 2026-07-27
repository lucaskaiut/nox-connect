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

export const creditCardSchema = z.object({
  number: z
    .string()
    .transform((value) => value.replace(/\D/g, ''))
    .pipe(z.string().min(13, 'Informe o número do cartão').max(19, 'Número de cartão inválido')),
  holder_name: z.string().min(1, 'Informe o nome impresso no cartão'),
  exp_month: z.string().regex(/^(0[1-9]|1[0-2])$/, 'Mês inválido'),
  exp_year: z.string().regex(/^\d{2}$/, 'Ano inválido (AA)'),
  cvv: z.string().regex(/^\d{3,4}$/, 'CVV inválido'),
  installments: z.coerce.number().int().min(1).max(12),
  email: z.string().min(1, 'Informe o e-mail').email('Informe um e-mail válido'),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  phone: z
    .string()
    .transform((value) => value.replace(/\D/g, ''))
    .pipe(z.string().min(10, 'Informe um telefone válido').max(11, 'Telefone inválido')),
  postal_code: z
    .string()
    .transform((value) => value.replace(/\D/g, ''))
    .pipe(z.string().length(8, 'Informe um CEP válido')),
  address_number: z.string().min(1, 'Informe o número do endereço'),
  address_complement: z.string().optional(),
})

export type CreditCardValues = z.infer<typeof creditCardSchema>

export function toCreditCardPaymentData(values: CreditCardValues): Record<string, unknown> {
  return {
    number: values.number,
    holder_name: values.holder_name,
    exp_month: values.exp_month,
    exp_year: values.exp_year,
    cvv: values.cvv,
    installments: values.installments,
    credit_card_holder_info: {
      name: values.holder_name,
      email: values.email,
      cpf_cnpj: values.document,
      postal_code: values.postal_code,
      address_number: values.address_number,
      phone: values.phone,
      ...(values.address_complement
        ? { address_complement: values.address_complement }
        : {}),
    },
  }
}

export const PASSWORD_REQUIREMENTS = [
  { id: 'length', label: 'Pelo menos 8 caracteres', test: (v: string) => v.length >= 8 },
  { id: 'letter', label: 'Pelo menos uma letra', test: (v: string) => /[A-Za-z]/.test(v) },
  { id: 'number', label: 'Pelo menos um número', test: (v: string) => /[0-9]/.test(v) },
] as const

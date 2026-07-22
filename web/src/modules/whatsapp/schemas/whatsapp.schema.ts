import { z } from 'zod'

export const whatsappConfigSchema = z.object({
  name: z.string().min(1, 'Informe um nome para identificar a conexão'),
  waba_id: z.string().min(1, 'Informe o WhatsApp Business Account ID'),
  phone_number_id: z.string().min(1, 'Informe o Phone Number ID'),
  access_token: z.string().min(1, 'Informe o Access Token'),
  verify_token: z.string().optional(),
})

export type WhatsAppConfigFormValues = z.infer<typeof whatsappConfigSchema>

export const whatsappTagSchema = z.object({
  name: z.string().min(1, 'Informe o nome da tag'),
  color: z.string().optional(),
  sort_order: z.number().int().min(0).optional(),
})

export type WhatsAppTagFormValues = z.infer<typeof whatsappTagSchema>

import { z } from 'zod'

/** Onboarding Meta: account_id + channel_id */
export const whatsappMetaConnectSchema = z.object({
  account_id: z.string().min(1, 'Informe o ID da conta comercial'),
  channel_id: z.string().min(1, 'Informe o ID do canal'),
  webhook_verify_token: z.string().optional(),
})

/** Onboarding D-API: session_id opcional (vazio = cria sessão nova) */
export const whatsappDApiConnectSchema = z.object({
  session_id: z.string().optional(),
  connection_id: z.string().optional(),
  webhook_verify_token: z.string().optional(),
})

/** @deprecated use whatsappMetaConnectSchema / whatsappDApiConnectSchema */
export const whatsappConnectSchema = whatsappMetaConnectSchema

export type WhatsAppMetaConnectFormValues = z.infer<typeof whatsappMetaConnectSchema>
export type WhatsAppDApiConnectFormValues = z.infer<typeof whatsappDApiConnectSchema>
export type WhatsAppConnectFormValues = WhatsAppMetaConnectFormValues | WhatsAppDApiConnectFormValues

export const whatsappTagSchema = z.object({
  name: z.string().min(1, 'Informe o nome da tag'),
  color: z.string().optional(),
  sort_order: z.number().int().min(0).optional(),
})

export type WhatsAppTagFormValues = z.infer<typeof whatsappTagSchema>

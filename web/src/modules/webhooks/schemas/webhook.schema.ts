import { z } from 'zod'

export const WEBHOOK_EVENTS = [
  { value: 'post.created', label: 'Post criado' },
  { value: 'post.updated', label: 'Post atualizado' },
  { value: 'post.deleted', label: 'Post removido' },
] as const

export const WEBHOOK_METHODS = [
  { value: 'POST', label: 'POST' },
  { value: 'GET', label: 'GET' },
  { value: 'PUT', label: 'PUT' },
  { value: 'PATCH', label: 'PATCH' },
  { value: 'DELETE', label: 'DELETE' },
] as const

const eventValues = WEBHOOK_EVENTS.map((e) => e.value) as [string, ...string[]]
const methodValues = WEBHOOK_METHODS.map((m) => m.value) as [string, ...string[]]

const headerEntrySchema = z.object({
  key: z.string().min(1, 'Informe o nome do header'),
  value: z.string().min(1, 'Informe o valor do header'),
})

const queryParamEntrySchema = z.object({
  key: z.string().min(1, 'Informe o nome do parâmetro'),
  value: z.string().min(1, 'Informe o valor do parâmetro'),
})

export const webhookSchema = z.object({
  name: z.string().min(1, 'Informe um nome para identificar o webhook'),
  url: z.string().url('Informe uma URL válida'),
  method: z.enum(methodValues),
  event: z.enum(eventValues),
  headers: z.array(headerEntrySchema).optional(),
  query_params: z.array(queryParamEntrySchema).optional(),
  body_template: z.string().optional(),
  is_active: z.boolean(),
  secret: z.string().optional(),
  description: z.string().optional(),
})

export type WebhookFormValues = z.infer<typeof webhookSchema>

export interface HeaderEntry {
  key: string
  value: string
}

export interface QueryParamEntry {
  key: string
  value: string
}

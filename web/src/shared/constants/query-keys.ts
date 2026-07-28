import type { ListParams } from '@/shared/types/api'

export const queryKeys = {
  session: ['session'] as const,

  onboarding: {
    all: ['onboarding'] as const,
    status: () => ['onboarding', 'status'] as const,
  },

  users: {
    all: ['users'] as const,
    list: (params: ListParams) => ['users', 'list', params] as const,
    detail: (id: string) => ['users', 'detail', id] as const,
  },

  roles: {
    all: ['roles'] as const,
    list: (params: ListParams) => ['roles', 'list', params] as const,
    detail: (id: number) => ['roles', 'detail', id] as const,
  },

  apiTokens: {
    all: ['api-tokens'] as const,
    list: () => ['api-tokens', 'list'] as const,
  },

  webhooks: {
    all: ['webhooks'] as const,
    list: () => ['webhooks', 'list'] as const,
    detail: (id: number) => ['webhooks', 'detail', id] as const,
    logs: (id: number) => ['webhooks', 'logs', id] as const,
    events: () => ['webhooks', 'events'] as const,
  },

  whatsapp: {
    all: ['whatsapp'] as const,
    connection: {
      all: ['whatsapp', 'connection'] as const,
      detail: () => ['whatsapp', 'connection', 'detail'] as const,
      webhookLogs: () => ['whatsapp', 'connection', 'webhook-logs'] as const,
    },
    conversations: {
      all: ['whatsapp', 'conversations'] as const,
      list: (params: Record<string, unknown>) => ['whatsapp', 'conversations', 'list', params] as const,
      detail: (id: number) => ['whatsapp', 'conversations', 'detail', id] as const,
      messages: (id: number) => ['whatsapp', 'conversations', 'messages', id] as const,
      stats: () => ['whatsapp', 'conversations', 'stats'] as const,
    },
    tags: {
      all: ['whatsapp', 'tags'] as const,
      list: () => ['whatsapp', 'tags', 'list'] as const,
    },
    kanban: {
      all: ['whatsapp', 'kanban'] as const,
      board: () => ['whatsapp', 'kanban', 'board'] as const,
      stages: () => ['whatsapp', 'kanban', 'stages'] as const,
      stageConversations: (stageId: number) =>
        ['whatsapp', 'kanban', 'stages', stageId, 'conversations'] as const,
    },
    templates: {
      all: ['whatsapp', 'templates'] as const,
      list: (params?: Record<string, unknown>) => ['whatsapp', 'templates', 'list', params] as const,
      detail: (templateId: string) => ['whatsapp', 'templates', 'detail', templateId] as const,
    },
  },

  billing: {
    all: ['billing'] as const,
    plans: {
      all: ['billing', 'plans'] as const,
      list: () => ['billing', 'plans', 'list'] as const,
      detail: (id: string) => ['billing', 'plans', 'detail', id] as const,
      catalog: () => ['billing', 'plans', 'catalog'] as const,
    },
    subscription: {
      current: () => ['billing', 'subscription'] as const,
    },
    gateways: {
      list: () => ['billing', 'gateways'] as const,
    },
    invoices: {
      list: () => ['billing', 'invoices', 'list'] as const,
      detail: (id: string) => ['billing', 'invoices', 'detail', id] as const,
    },
  },
} as const

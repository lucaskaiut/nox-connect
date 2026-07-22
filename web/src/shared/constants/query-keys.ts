import type { ListParams } from '@/shared/types/api'

export const queryKeys = {
  session: ['session'] as const,

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
    configs: {
      all: ['whatsapp', 'configs'] as const,
      list: () => ['whatsapp', 'configs', 'list'] as const,
      detail: (id: number) => ['whatsapp', 'configs', 'detail', id] as const,
      webhookLogs: (id: number) => ['whatsapp', 'configs', 'webhook-logs', id] as const,
    },
    conversations: {
      all: ['whatsapp', 'conversations'] as const,
      list: (params: Record<string, unknown>) => ['whatsapp', 'conversations', 'list', params] as const,
      detail: (id: number) => ['whatsapp', 'conversations', 'detail', id] as const,
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
    },
  },
} as const

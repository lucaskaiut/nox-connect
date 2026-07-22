import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { echo } from './echo'
import {
  setupConversationChannelListeners,
  setupTenantChannelListeners,
} from './listeners'

export function useTenantChannel(tenantId: number | string | undefined) {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (tenantId == null) return

    const channel = echo.private(`tenant.${tenantId}`)

    setupTenantChannelListeners(channel, queryClient)

    return () => {
      echo.leave(`tenant.${tenantId}`)
    }
  }, [tenantId, queryClient])
}

export function useConversationChannel(conversationId: number | undefined) {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (conversationId == null) return

    const channel = echo.private(`conversation.${conversationId}`)

    setupConversationChannelListeners(channel, queryClient)

    return () => {
      echo.leave(`conversation.${conversationId}`)
    }
  }, [conversationId, queryClient])
}

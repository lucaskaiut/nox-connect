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
    if (tenantId == null || tenantId === '') return

    const name = `tenant.${tenantId}`
    const channel = echo.private(name)

    setupTenantChannelListeners(channel, queryClient)

    channel.error((error: unknown) => {
      console.error('[echo] tenant channel error', name, error)
    })

    return () => {
      echo.leave(name)
    }
  }, [tenantId, queryClient])
}

export function useConversationChannel(conversationId: number | undefined) {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (conversationId == null) return

    const name = `conversation.${conversationId}`
    const channel = echo.private(name)

    setupConversationChannelListeners(channel, queryClient)

    channel.error((error: unknown) => {
      console.error('[echo] conversation channel error', name, error)
    })

    return () => {
      echo.leave(name)
    }
  }, [conversationId, queryClient])
}

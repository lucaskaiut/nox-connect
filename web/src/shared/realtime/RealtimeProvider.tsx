import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import type Pusher from 'pusher-js'
import { useSessionStore } from '@/shared/stores/session.store'
import { echo } from './echo'
import { useTenantChannel } from './useRealtime'

interface RealtimeContextValue {
  isConnected: boolean
}

const RealtimeContext = createContext<RealtimeContextValue | null>(null)

interface PusherConnection {
  state: string
  bind: (event: string, callback: (states: { current: string; previous: string }) => void) => void
  unbind: (event: string, callback: (states: { current: string; previous: string }) => void) => void
}

function getPusherConnection(): PusherConnection | undefined {
  const connector = (
    echo as unknown as { connector: { pusher: Pusher } }
  ).connector

  return connector.pusher.connection as unknown as PusherConnection
}

export function RealtimeProvider({ children }: { children: ReactNode }) {
  const tenantId = useSessionStore((s) => s.tenant?.id)
  const [isConnected, setIsConnected] = useState(false)

  useTenantChannel(tenantId)

  useEffect(() => {
    const connection = getPusherConnection()
    if (!connection) return

    const handleStateChange = (states: { current: string }) => {
      setIsConnected(states.current === 'connected')
    }

    connection.bind('state_change', handleStateChange)
    setIsConnected(connection.state === 'connected')

    return () => {
      connection.unbind('state_change', handleStateChange)
    }
  }, [])

  return (
    <RealtimeContext.Provider value={{ isConnected }}>
      {children}
    </RealtimeContext.Provider>
  )
}

export function useRealtime(): RealtimeContextValue {
  const context = useContext(RealtimeContext)

  if (!context) {
    throw new Error('useRealtime must be used within a <RealtimeProvider>')
  }

  return context
}

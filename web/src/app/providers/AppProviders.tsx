import { useState, type ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { isApiError } from '@/shared/api/errors'
import { Toaster } from '@/shared/design-system'
import { RealtimeProvider } from '@/shared/realtime/RealtimeProvider'
import { ThemeProvider } from './ThemeProvider'
import { SessionProvider } from './SessionProvider'

function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          if (isApiError(error) && error.status > 0 && error.status < 500) return false

          return failureCount < 2
        },
      },
    },
  })
}

export function AppProviders({ children }: { children: ReactNode }) {
  const [queryClient] = useState(createQueryClient)

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <SessionProvider>
          <RealtimeProvider>
            {children}
          </RealtimeProvider>
          <Toaster />
        </SessionProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}

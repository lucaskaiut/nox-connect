import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { http } from '@/shared/api/http'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

const wsPort = Number(import.meta.env.VITE_REVERB_PORT) || 8080

export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY ?? '',
  wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
  wsPort,
  wssPort: wsPort,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
  enabledTransports: ['ws', 'wss'],
  // Broadcast::routes() lives at /broadcasting/auth (outside /api).
  // In dev this path must be proxied by Vite — see vite.config.ts.
  authorizer: (channel) => ({
    authorize: (socketId, callback) => {
      http
        .post(
          '/broadcasting/auth',
          { socket_id: socketId, channel_name: channel.name },
          {
            // http baseURL is `${API_BASE_URL}/api` — override to hit /broadcasting/auth
            baseURL: import.meta.env.VITE_API_BASE_URL || '',
          },
        )
        .then((response) => {
          if (!response.data?.auth) {
            callback(new Error('Channel authorization missing auth signature'), { auth: '' })
            return
          }
          callback(null, response.data)
        })
        .catch((error) => {
          console.error('[echo] channel auth failed', channel.name, error)
          callback(new Error('Channel authorization failed'), { auth: '' })
        })
    },
  }),
})

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from 'axios'
import { API_BASE_URL } from '@/shared/api/base-url'

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
  authorizer: (channel) => ({
    authorize: (socketId, callback) => {
      axios
        .post(
          `${API_BASE_URL}/broadcasting/auth`,
          { socket_id: socketId, channel_name: channel.name },
          {
            withCredentials: true,
            withXSRFToken: true,
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          },
        )
        .then((response) => callback(null, response.data))
        .catch(() => callback(new Error('Channel authorization failed'), { auth: '' }))
    },
  }),
})

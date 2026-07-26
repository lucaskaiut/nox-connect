import type { ConnectionAdapter, ConnectionBootstrap, ConnectionResult } from '../types'
import { DApiConnect } from 'd-api-sdk/connect'

type ConnectMode = 'standard' | 'coexistence'

type ConnectStartOptions = {
  mode: ConnectMode
}

type ConnectResultPayload = {
  connectionId?: string
  phoneNumber?: string
  status?: string
}

/**
 * Isola o SDK D-API. Nenhum outro módulo deve importar d-api-sdk/connect.
 *
 * Importante: não enviamos webhookUrl no popup — a D-API costuma validar a URL
 * durante o Embedded Signup e falha com "Failed to fetch" se o endpoint não
 * responder como esperado. O webhook é registrado no backend após o complete.
 *
 * Com keep_popup_on_error=true, usamos o handshake manual (igual ao SDK) mas
 * NÃO fechamos o popup em caso de falha — para inspecionar Network/Console.
 */
export class DApiConnectionAdapter implements ConnectionAdapter {
  readonly type = 'sdk' as const

  async start(bootstrap: ConnectionBootstrap): Promise<ConnectionResult> {
    const publishableKey = String(bootstrap.configuration.publishable_key ?? '')
    const connectBaseUrl = bootstrap.configuration.connect_base_url
      ? String(bootstrap.configuration.connect_base_url)
      : undefined
    const pendingWebhookUrl = bootstrap.configuration.pending_webhook_url
      ? String(bootstrap.configuration.pending_webhook_url)
      : null
    const keepPopupOnError = Boolean(bootstrap.configuration.keep_popup_on_error)

    if (!publishableKey) {
      throw new Error('Publishable key ausente na configuração do provider.')
    }

    const configuredMode = String(bootstrap.configuration.mode ?? 'standard')
    const mode: ConnectMode = configuredMode === 'coexistence' ? 'coexistence' : 'standard'
    const startOptions: ConnectStartOptions = { mode }

    console.info('[WhatsApp:D-API] abrindo Embedded Signup', {
      provider: bootstrap.provider,
      type: bootstrap.type,
      connectBaseUrl: connectBaseUrl ?? 'https://connect.d-api.cloud',
      publishableKeyPrefix: `${publishableKey.slice(0, 12)}…`,
      webhookInSdk: false,
      pendingWebhookUrl,
      keepPopupOnError,
      startOptions,
    })

    try {
      const result = keepPopupOnError
        ? await this.startWithHandshake({
            publishableKey,
            connectBaseUrl: connectBaseUrl ?? 'https://connect.d-api.cloud',
            options: startOptions,
            keepPopupOnError: true,
          })
        : await this.startWithSdk({
            publishableKey,
            connectBaseUrl,
            options: startOptions,
          })

      console.info('[WhatsApp:D-API] Embedded Signup concluído', {
        connectionId: result.connectionId,
        phoneNumber: result.phoneNumber,
        status: result.status,
      })

      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error)

      console.error('[WhatsApp:D-API] Embedded Signup falhou', {
        message,
        error,
        connectBaseUrl: connectBaseUrl ?? 'https://connect.d-api.cloud',
        publishableKeyPrefix: `${publishableKey.slice(0, 12)}…`,
        pendingWebhookUrl,
        webhookInSdk: false,
        keepPopupOnError,
        mode: startOptions.mode,
        hint: keepPopupOnError
          ? 'Popup mantido aberto — inspecione Network/Console na janela connect.d-api.cloud'
          : 'Ative WHATSAPP_DAPI_KEEP_POPUP_ON_ERROR=true para manter o popup aberto na falha',
      })

      throw new Error(
        `[D-API SDK] ${message} | mode=${startOptions.mode} | webhookInSdk=false | pendingWebhook=${pendingWebhookUrl ?? 'null'}`,
      )
    }
  }

  private async startWithSdk(params: {
    publishableKey: string
    connectBaseUrl?: string
    options: ConnectStartOptions
  }): Promise<ConnectionResult> {
    const connect = new DApiConnect({
      publishableKey: params.publishableKey,
      connectBaseUrl: params.connectBaseUrl,
    })

    const result = await connect.start(params.options)

    return {
      connectionId: result.connectionId,
      phoneNumber: result.phoneNumber,
      status: result.status,
    }
  }

  /**
   * Mesmo handshake do d-api-sdk/connect, com controle sobre fechar o popup.
   * @see https://github.com/d-api/exemplo-api-oficial-saas
   */
  private startWithHandshake(params: {
    publishableKey: string
    connectBaseUrl: string
    options: ConnectStartOptions
    keepPopupOnError: boolean
  }): Promise<ConnectionResult> {
    const connectOrigin = params.connectBaseUrl.replace(/\/$/, '')
    const popup = window.open(`${connectOrigin}/connect`, 'dapi-connect', 'width=600,height=760')

    if (!popup) {
      return Promise.reject(new Error('Popup bloqueado — permita popups para conectar.'))
    }

    return new Promise((resolve, reject) => {
      let settled = false

      const cleanup = (closePopup: boolean) => {
        window.removeEventListener('message', onMessage)
        window.clearInterval(poll)

        if (closePopup) {
          try {
            popup.close()
          } catch {
            // ignore
          }
        }
      }

      const finishOk = (data: ConnectResultPayload) => {
        if (settled) return
        settled = true
        cleanup(true)
        resolve({
          connectionId: data.connectionId,
          phoneNumber: data.phoneNumber,
          status: data.status,
        })
      }

      const finishErr = (message: string) => {
        if (settled) return
        settled = true
        cleanup(!params.keepPopupOnError)

        if (params.keepPopupOnError) {
          console.warn(
            '[WhatsApp:D-API] popup mantido aberto para debug. Feche manualmente após inspecionar Network/Console.',
            { message, connectOrigin },
          )
        }

        reject(new Error(message))
      }

      const onMessage = (event: MessageEvent) => {
        if (event.origin !== connectOrigin || event.source !== popup) return

        const msg = event.data as {
          type?: string
          ok?: boolean
          data?: ConnectResultPayload
          error?: string
        }

        if (msg?.type === 'dapi-connect-ready') {
          popup.postMessage(
            {
              type: 'dapi-connect-init',
              pk: params.publishableKey,
              mode: params.options.mode,
            },
            connectOrigin,
          )
          return
        }

        if (msg?.type === 'dapi-connect-result') {
          if (msg.ok && msg.data) {
            finishOk(msg.data)
            return
          }

          finishErr(msg.error || 'Onboarding falhou')
        }
      }

      const poll = window.setInterval(() => {
        if (popup.closed && !settled) {
          finishErr('Conexão cancelada (popup fechado).')
        }
      }, 500)

      window.addEventListener('message', onMessage)
    })
  }
}

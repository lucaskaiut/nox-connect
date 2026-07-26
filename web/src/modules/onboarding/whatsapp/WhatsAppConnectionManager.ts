import { DApiConnectionAdapter } from './adapters/DApiConnectionAdapter'
import { FormConnectionAdapter } from './adapters/FormConnectionAdapter'
import type { ConnectionAdapter, ConnectionBootstrap, ConnectionResult } from './types'

/**
 * Fachada genérica de conexão WhatsApp.
 * Seleciona o adapter pela estratégia retornada pelo backend (type + provider).
 */
export class WhatsAppConnectionManager {
  private constructor(
    private readonly bootstrap: ConnectionBootstrap,
    private readonly adapter: ConnectionAdapter,
  ) {}

  static create(bootstrap: ConnectionBootstrap): WhatsAppConnectionManager {
    return new WhatsAppConnectionManager(bootstrap, this.resolveAdapter(bootstrap))
  }

  async start(): Promise<ConnectionResult> {
    return this.adapter.start(this.bootstrap)
  }

  private static resolveAdapter(bootstrap: ConnectionBootstrap): ConnectionAdapter {
    switch (bootstrap.type) {
      case 'sdk':
        if (bootstrap.provider === 'd-api') {
          return new DApiConnectionAdapter()
        }
        throw new Error(`Adapter SDK não registrado para o provider [${bootstrap.provider}].`)
      case 'form':
        return new FormConnectionAdapter()
      case 'oauth':
      case 'redirect':
        throw new Error(`Estratégia [${bootstrap.type}] ainda não implementada.`)
      default:
        throw new Error(`Estratégia de conexão desconhecida: [${bootstrap.type}].`)
    }
  }
}

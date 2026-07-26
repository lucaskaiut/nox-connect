import type { ConnectionAdapter, ConnectionBootstrap, ConnectionResult } from '../types'

/**
 * Estratégia de formulário manual (ex.: Meta Cloud API).
 * O step coleta os campos e chama start() com payload já preenchido via configuration.__formValues.
 */
export class FormConnectionAdapter implements ConnectionAdapter {
  readonly type = 'form' as const

  async start(bootstrap: ConnectionBootstrap): Promise<ConnectionResult> {
    const values = (bootstrap.configuration.__formValues ?? {}) as Record<string, unknown>

    if (Object.keys(values).length === 0) {
      throw new Error('Preencha os identificadores do WhatsApp para continuar.')
    }

    return {
      status: 'connected',
      payload: values,
    }
  }
}

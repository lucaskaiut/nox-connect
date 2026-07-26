export type ConnectionStrategyType = 'sdk' | 'oauth' | 'redirect' | 'form'

export interface ConnectionBootstrap {
  type: ConnectionStrategyType
  provider: string
  configuration: Record<string, unknown>
  webhook_url?: string | null
}

export interface ConnectionResult {
  connectionId?: string
  phoneNumber?: string | null
  status?: string
  /** Campos extras para estratégias de formulário/oauth */
  payload?: Record<string, unknown>
}

export interface ConnectionAdapter {
  readonly type: ConnectionStrategyType
  start(bootstrap: ConnectionBootstrap): Promise<ConnectionResult>
}

export interface OnboardingStatus {
  required: boolean
  completed: boolean
  company_completed: boolean
  whatsapp_completed: boolean
  current_step: 'company' | 'whatsapp' | 'finish' | 'done' | string
  completed_at: string | null
  provider: string
  company: {
    name: string
    document: string
    email: string
    phone: string | null
    domain: string
  }
  whatsapp: {
    connected: boolean
    phone_number: string | null
    connection_id: string | null
  }
  connection_message?: string
}

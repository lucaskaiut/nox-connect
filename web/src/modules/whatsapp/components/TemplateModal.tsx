import { useState, useMemo } from 'react'
import { Send, FileText } from 'lucide-react'
import {
  Badge,
  Button,
  Input,
  Modal,
  Select,
  Skeleton,
  EmptyState,
  type SelectOption,
} from '@/shared/design-system'
import { useTemplatesQuery, useSendTemplate } from '../hooks/useWhatsApp'

const CATEGORY_LABELS: Record<string, string> = {
  authentication: 'Autenticação',
  marketing: 'Marketing',
  utility: 'Utilidade',
  AUTHENTICATION: 'Autenticação',
  MARKETING: 'Marketing',
  UTILITY: 'Utilidade',
}

function getBodyText(components: Record<string, unknown>[] | null | undefined): string {
  if (!components) return ''
  const body = components.find((c) => c.type === 'BODY' || c.type === 'body')
  return (body?.text as string) ?? ''
}

function extractParams(text: string): string[] {
  return Array.from(new Set(Array.from(text.matchAll(/\{\{(\w+)\}\}/g), (m) => m[1])))
}

interface TemplateModalProps {
  open: boolean
  onClose: () => void
  conversationId: number
}

export function TemplateModal({ open, onClose, conversationId }: TemplateModalProps) {
  const { data: templatesData, isPending: loading } = useTemplatesQuery({})
  const sendTemplate = useSendTemplate()
  const templates = templatesData?.data ?? []

  const [selectedId, setSelectedId] = useState('')
  const [variables, setVariables] = useState<Record<string, string>>({})

  const selected = useMemo(
    () => templates.find((t) => t.id === selectedId) ?? null,
    [templates, selectedId],
  )

  const templateOptions: SelectOption[] = templates.map((t) => ({
    value: t.id,
    label: `${t.name ?? '—'} (${t.language ?? '—'} · ${t.category ? CATEGORY_LABELS[t.category.toLowerCase()] ?? t.category : '—'})`,
  }))

  const bodyText = selected ? getBodyText(selected.components) : ''
  const paramNames = extractParams(bodyText)

  const preview = bodyText
    ? bodyText.replace(/\{\{(\w+)\}\}/g, (_, name) => variables[name] || `{{${name}}}`)
    : ''

  const canSend = selected && paramNames.every((n) => (variables[n] ?? '').trim())

  const handleSend = async () => {
    if (!selected || !canSend) return
    await sendTemplate.mutateAsync({
      conversationId,
      template_name: selected.name ?? '',
      language: selected.language ?? 'pt_BR',
      variables: paramNames.map((n) => variables[n] ?? ''),
    })
    setSelectedId('')
    setVariables({})
    onClose()
  }

  const handleClose = () => {
    if (!sendTemplate.isPending) {
      setSelectedId('')
      setVariables({})
      onClose()
    }
  }

  const handleSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setSelectedId(e.target.value)
    setVariables({})
  }

  return (
    <Modal open={open} onClose={handleClose} title="Enviar Template">
      <div className="space-y-4">
        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">
            Selecionar template
          </label>
          {loading ? (
            <Skeleton className="h-10 w-full" />
          ) : templates.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="Nenhum template disponível"
              description="Cadastre templates na página de Templates antes de enviar."
            />
          ) : (
            <Select
              options={templateOptions}
              value={selectedId}
              onChange={handleSelect}
              placeholder="Escolha um template..."
            />
          )}
        </div>

        {selected && (
          <>
            <div className="rounded-lg bg-surface-2 p-3">
              <div className="mb-2 flex items-center gap-2">
                <Badge variant="neutral" className="text-[11px]">
                  {selected.language ?? '—'}
                </Badge>
                <Badge variant="neutral" className="text-[11px]">
                  {CATEGORY_LABELS[(selected.category ?? '').toLowerCase()] ?? selected.category}
                </Badge>
              </div>
              <p className="text-sm text-foreground whitespace-pre-wrap">
                {bodyText}
              </p>
            </div>

            {paramNames.length > 0 && (
              <div>
                <label className="mb-2 block text-[13px] font-medium text-foreground">
                  Variáveis
                </label>
                <div className="space-y-2">
                  {paramNames.map((name) => (
                    <div key={name}>
                      <label className="mb-1 block text-[13px] font-medium text-foreground">
                        {'{{'}{name}{'}}'}
                      </label>
                      <Input
                        placeholder={`Valor para ${name}`}
                        value={variables[name] ?? ''}
                        onChange={(e) => setVariables((prev) => ({ ...prev, [name]: e.target.value }))}
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {preview && paramNames.every((n) => (variables[n] ?? '').trim()) && (
              <div className="rounded-lg border border-surface-2 bg-surface-1 p-3">
                <p className="mb-1 text-[11px] font-medium uppercase tracking-wide text-muted">
                  Preview
                </p>
                <p className="text-sm text-foreground whitespace-pre-wrap">
                  {preview.split(/(\{\{[^}]+\}\})/g).map((part, i) =>
                    part.startsWith('{{') && part.endsWith('}}') ? (
                      <strong key={i} className="text-primary">{variables[part.slice(2, -2)] || part}</strong>
                    ) : (
                      <span key={i}>{part}</span>
                    ),
                  )}
                </p>
              </div>
            )}
          </>
        )}
      </div>

      <div className="mt-6 flex justify-end gap-2">
        <Button variant="ghost" onClick={handleClose} disabled={sendTemplate.isPending}>
          Cancelar
        </Button>
        <Button onClick={handleSend} loading={sendTemplate.isPending} disabled={!canSend}>
          <Send className="size-4" />
          Enviar
        </Button>
      </div>
    </Modal>
  )
}

import { useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FileText, Plus, Trash2, X } from 'lucide-react'
import { z } from 'zod'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  EmptyState,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  SelectField,
  TextField,
  TextareaField,
  Skeleton,
  ButtonLink,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useTemplatesQuery, useCreateTemplate, useDeleteTemplate, useWhatsAppConnectionQuery } from '../hooks/useWhatsApp'

const CATEGORY_OPTIONS: SelectOption[] = [
  { value: 'authentication', label: 'Autenticação' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'utility', label: 'Utilidade' },
]

const STATUS_MAP: Record<string, string> = {
  APPROVED: 'Aprovado',
  ARCHIVED: 'Arquivado',
  DELETED: 'Excluído',
  DISABLED: 'Desabilitado',
  IN_APPEAL: 'Em recurso',
  LIMIT_EXCEEDED: 'Limite excedido',
  PAUSED: 'Pausado',
  PENDING: 'Pendente',
  PENDING_DELETION: 'Exclusão pendente',
  REJECTED: 'Rejeitado',
}

function getCategoryLabel(cat: string): string {
  const lower = cat.toLowerCase()
  return CATEGORY_OPTIONS.find((o) => o.value === lower)?.label ?? cat
}

function getStatusVariant(status: string): 'success' | 'warning' | 'danger' | 'neutral' {
  switch (status) {
    case 'APPROVED': return 'success'
    case 'PENDING': return 'warning'
    case 'REJECTED': case 'DISABLED': return 'danger'
    default: return 'neutral'
  }
}

function extractParameters(components: Record<string, unknown>[] | null | undefined): Array<{ label: string; name: string }> {
  if (!components) return []
  const body = components.find((c) => c.type === 'BODY' || c.type === 'body')
  if (!body) return []
  const example = body.example as Record<string, unknown> | undefined
  const named = example?.body_text_named_params as Array<{ param_name: string; example: string }> | undefined
  if (!named) return []
  return named.map((p) => ({ label: p.example, name: p.param_name }))
}

function getBodyText(components: Record<string, unknown>[] | null | undefined): string {
  if (!components) return ''
  const body = components.find((c) => c.type === 'BODY' || c.type === 'body')
  return (body?.text as string) ?? ''
}

function renderPreview(text: string, params: { label: string; name: string }[]): string {
  let preview = text
  for (const p of params) {
    preview = preview.replace(new RegExp(`\\{\\{${p.name}\\}\\}`, 'g'), `**${p.label}**`)
  }
  return preview
}

const paramSchema = z.object({
  label: z.string().min(1, 'Exemplo é obrigatório'),
  name: z.string().min(1, 'Nome é obrigatório').regex(/^[a-z_][a-z0-9_]*$/, 'Apenas letras minúsculas, números e underline'),
})

const createTemplateSchema = z.object({
  name: z.string().min(1, 'Nome é obrigatório').regex(/^[a-z0-9_]+$/, 'Apenas letras minúsculas, números e underline'),
  language: z.string().min(1, 'Idioma é obrigatório'),
  category: z.string().min(1, 'Categoria é obrigatória'),
  bodyText: z.string().min(1, 'Texto do corpo é obrigatório'),
  parameters: z.array(paramSchema),
})

type CreateTemplateForm = z.infer<typeof createTemplateSchema>

function buildComponents(values: CreateTemplateForm) {
  return [
    {
      type: 'body',
      text: values.bodyText,
      example: {
        body_text_named_params: values.parameters.map((p) => ({
          param_name: p.name,
          example: p.label,
        })),
      },
    },
  ]
}

function extractParamNames(text: string): string[] {
  return Array.from(new Set(
    Array.from(text.matchAll(/\{\{(\w+)\}\}/g), (m) => m[1]),
  ))
}

function CreateTemplateFormSection({
  onCreated,
}: {
  onCreated: () => void
}) {
  const createTemplate = useCreateTemplate()

  const form = useForm<CreateTemplateForm>({
    resolver: zodResolver(createTemplateSchema),
    defaultValues: { name: '', language: 'pt_BR', category: 'utility', bodyText: '', parameters: [] },
  })

  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'parameters' })
  const bodyText = form.watch('bodyText')
  const params = form.watch('parameters')

  useEffect(() => {
    const found = extractParamNames(bodyText)
    const currentNames = params.map((p) => p.name)

    if (found.join(',') === currentNames.join(',')) return

    const removedIndices = params
      .map((p, i) => (found.includes(p.name) ? -1 : i))
      .filter((i) => i !== -1)
      .sort((a, b) => b - a)

    for (const i of removedIndices) {
      remove(i)
    }

    for (const name of found) {
      if (!currentNames.includes(name)) {
        append({ label: name, name })
      }
    }
  }, [bodyText])

  const onSubmit = async (values: CreateTemplateForm) => {
    const payload = {
      name: values.name,
      language: values.language,
      category: values.category,
      parameter_format: 'named',
      components: buildComponents(values),
    }
    await createTemplate.mutateAsync(payload)
    form.reset({ name: '', language: 'pt_BR', category: 'utility', bodyText: '', parameters: [] })
    onCreated()
  }

  return (
    <Card className="mb-6">
      <CardContent>
        <Form form={form} onSubmit={onSubmit}>
          <div className="flex items-end gap-3 mb-4">
            <div className="flex-1">
              <TextField name="name" label="Nome do template" placeholder="Ex.: pedido_confirmado" required />
            </div>
            <div className="w-36">
              <TextField name="language" label="Idioma" placeholder="pt_BR" required />
            </div>
            <div className="w-44">
              <SelectField
                name="category"
                label="Categoria"
                options={CATEGORY_OPTIONS}
                placeholder="Selecione"
              />
            </div>
          </div>

          <div className="mb-4">
            <TextareaField
              name="bodyText"
              label="Corpo da mensagem"
              placeholder='Ex.: Olá, {{customer_name}}! Seu pedido {{order_number}} foi confirmado.'
              rows={3}
              required
            />
            <p className="mt-1 text-xs text-muted">
              Use <code className="rounded bg-surface-2 px-1 text-[11px]">{'{{nome_do_parametro}}'}</code> para inserir parâmetros nomeados.
            </p>
          </div>

          <div className="mb-4">
            <label className="mb-2 block text-[13px] font-medium text-foreground">
              Parâmetros ({fields.length})
            </label>

            {fields.length === 0 ? (
              <p className="text-xs text-muted">
                Nenhum parâmetro detectado. Use {'{{nome}}'} no corpo da mensagem para criar parâmetros automaticamente.
              </p>
            ) : (
              <div className="space-y-2">
                {fields.map((field, index) => (
                  <div key={field.id} className="flex items-start gap-2 rounded-lg bg-surface-2 p-3">
                    <div className="flex-1">
                      <TextField
                        name={`parameters.${index}.label`}
                        label="Exemplo (valor de preview)"
                        placeholder='Ex.: "Fulano"'
                      />
                    </div>
                    <div className="flex-1">
                      <TextField
                        name={`parameters.${index}.name`}
                        label={`Nome (use {{\u200b${form.watch(`parameters.${index}.name`)}}})`}
                        placeholder='Ex.: customer_name'
                        disabled
                        className="text-muted opacity-70"
                      />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {bodyText && params.length > 0 && (
            <div className="mb-4 rounded-lg border border-surface-2 bg-surface-1 p-3">
              <p className="mb-1 text-[11px] font-medium uppercase tracking-wide text-muted">
                Preview
              </p>
              <p className="text-sm text-foreground whitespace-pre-wrap">
                {renderPreview(bodyText, params).split(/(\*\*.*?\*\*)/g).map((part, i) =>
                  part.startsWith('**') && part.endsWith('**') ? (
                    <strong key={i} className="text-primary">{part.slice(2, -2)}</strong>
                  ) : (
                    <span key={i}>{part}</span>
                  ),
                )}
              </p>
            </div>
          )}

          <Button type="submit" loading={createTemplate.isPending}>
            <Plus className="size-4" />
            Criar template
          </Button>
        </Form>
      </CardContent>
    </Card>
  )
}

function DeleteButton({
  template,
  onDeleted,
}: {
  template: { name: string | null }
  onDeleted: () => void
}) {
  const [confirming, setConfirming] = useState(false)

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={() => setConfirming(true)}
        aria-label={`Remover ${template.name}`}
        className="ml-auto shrink-0 text-danger hover:bg-danger-soft hover:text-danger -my-1"
      >
        <Trash2 className="size-3.5" />
      </Button>

      <ConfirmDialog
        open={confirming}
        onClose={() => setConfirming(false)}
        onConfirm={onDeleted}
        title="Remover template"
        description={
          <>
            Tem certeza que deseja remover o template <strong>{template.name}</strong> (todos os idiomas)?
          </>
        }
        confirmLabel="Remover"
      />
    </>
  )
}

function TemplateRow({
  components,
  template,
  onDeleted,
}: {
  components: Record<string, unknown>[] | null | undefined
  template: { id: string; name: string | null; language: string | null; category: string | null; status: string | null }
  onDeleted: () => void
}) {
  const bodyText = getBodyText(components)
  const params = extractParameters(components)

  return (
    <div className="px-5 py-4">
      <div className="flex items-start gap-3">
        <FileText className="size-4 shrink-0 text-muted mt-0.5" />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className="font-medium text-sm text-foreground truncate">
              {template.name ?? '—'}
            </span>
            {template.status && (
              <Badge variant={getStatusVariant(template.status)} className="text-[11px] shrink-0">
                {STATUS_MAP[template.status] ?? template.status}
              </Badge>
            )}
            <Can permission={Permission.WHATSAPP_TEMPLATE_DELETE}>
              <DeleteButton template={template} onDeleted={onDeleted} />
            </Can>
          </div>
          <div className="text-xs text-muted mb-1">
            {template.language ?? '—'} · {template.category ? getCategoryLabel(template.category) : '—'} · <span className="font-mono text-[11px]">{template.id}</span>
          </div>
          {bodyText && (
            <p className="text-sm text-foreground/80 line-clamp-2 mb-2">
              {bodyText}
            </p>
          )}
          {params.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {params.map((p) => (
                <span key={p.name} className="inline-flex items-center gap-1 rounded-md bg-surface-2 px-2 py-0.5 text-[11px] text-muted">
                  <span className="font-medium text-foreground">{p.label}</span>
                  <span className="opacity-50">{'{{'}{p.name}{'}}'}</span>
                </span>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default function WhatsAppTemplatesListPage() {
  const { data: connection, isPending: connectionLoading } = useWhatsAppConnectionQuery()
  const { data: templatesData, isPending: templatesLoading, refetch } = useTemplatesQuery({})
  const deleteTemplate = useDeleteTemplate()

  const templates = templatesData?.data ?? []

  if (connectionLoading) return <Loading />

  if (!connection?.connected) {
    return (
      <Page>
        <PageHeader
          title="Templates"
          description="Gerencie os templates de mensagem do WhatsApp."
          breadcrumb={[
            { label: 'Dashboard', to: '/dashboard' },
            { label: 'WhatsApp', to: '/whatsapp' },
            { label: 'Templates' },
          ]}
        />
        <PageContent>
          <EmptyState
            icon={FileText}
            title="WhatsApp não conectado"
            description="Conecte o WhatsApp antes de gerenciar templates."
            action={<ButtonLink to="/whatsapp/connection">Ir para conexão</ButtonLink>}
          />
        </PageContent>
      </Page>
    )
  }

  return (
    <Page>
      <PageHeader
        title="Templates"
        description="Gerencie os templates de mensagem do WhatsApp."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Templates' },
        ]}
      />

      <PageContent>
        <Can permission={Permission.WHATSAPP_TEMPLATE_CREATE}>
          <CreateTemplateFormSection onCreated={() => refetch()} />
        </Can>

        <Card>
          <CardHeader
            title={`${templates.length} template(s)`}
            description="Templates são aprovados pelo WhatsApp antes de serem usados."
          />
          <CardContent className="p-0">
            {templatesLoading ? (
              <div className="space-y-2 p-5">
                <Skeleton className="h-20 w-full" />
                <Skeleton className="h-20 w-full" />
                <Skeleton className="h-20 w-full" />
              </div>
            ) : templates.length === 0 ? (
              <div className="p-5">
                <EmptyState
                  icon={FileText}
                  title="Nenhum template encontrado"
                  description="Crie um novo template para começar."
                />
              </div>
            ) : (
              <div className="divide-y divide-surface-2">
                {templates.map((tpl) => (
                  <TemplateRow
                    key={tpl.id}
                    components={tpl.components}
                    template={tpl}
                    onDeleted={async () => {
                      await deleteTemplate.mutateAsync({
                        name: tpl.name ?? undefined,
                      })
                      refetch()
                    }}
                  />
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Pencil, Plus, Tag, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  ConfirmDialog,
  Form,
  Page,
  PageContent,
  PageHeader,
  Section,
  TextField,
  Loading,
  EmptyState,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { whatsappTagSchema, type WhatsAppTagFormValues } from '../schemas/whatsapp.schema'
import { useTagsQuery, useCreateTag, useUpdateTag, useDeleteTag } from '../hooks/useWhatsApp'
import type { WhatsAppTag } from '@/shared/types/models'

const PRESET_COLORS = [
  '#6B7280', '#3B82F6', '#F59E0B', '#8B5CF6', '#10B981', '#EF4444',
  '#EC4899', '#14B8A6', '#F97316', '#6366F1',
]

export default function WhatsAppTagsListPage() {
  const { data: tags, isPending } = useTagsQuery()
  const createTag = useCreateTag()
  const updateTag = useUpdateTag()
  const deleteTag = useDeleteTag()

  const [editing, setEditing] = useState<WhatsAppTag | null>(null)
  const [deleting, setDeleting] = useState<WhatsAppTag | null>(null)

  const form = useForm<WhatsAppTagFormValues>({
    resolver: zodResolver(whatsappTagSchema),
    defaultValues: { name: '', color: PRESET_COLORS[0] },
  })

  const onSubmit = async (values: WhatsAppTagFormValues) => {
    if (editing) {
      await updateTag.mutateAsync({ id: editing.id, ...values })
      setEditing(null)
    } else {
      await createTag.mutateAsync(values)
    }
    form.reset({ name: '', color: PRESET_COLORS[0] })
  }

  const startEdit = (tag: WhatsAppTag) => {
    setEditing(tag)
    form.reset({ name: tag.name, color: tag.color ?? PRESET_COLORS[0] })
  }

  const cancelEdit = () => {
    setEditing(null)
    form.reset({ name: '', color: PRESET_COLORS[0] })
  }

  const confirmDelete = async () => {
    if (!deleting) return
    await deleteTag.mutateAsync(deleting.id)
    setDeleting(null)
  }

  if (isPending) return <Loading />

  return (
    <Page>
      <PageHeader
        title="Tags"
        description="Gerencie as tags para classificação de conversas."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Tags' },
        ]}
      />

      <PageContent>
        <Card className="mb-6">
          <CardContent>
            <Section
              title={editing ? 'Editar tag' : 'Nova tag'}
              description={editing ? `Editando "${editing.name}"` : 'Adicione uma nova tag para classificar conversas.'}
            >
              <Form form={form} onSubmit={onSubmit}>
                <div className="flex items-end gap-3">
                  <div className="flex-1">
                    <TextField name="name" label="Nome" placeholder="Ex.: Urgente" required />
                  </div>
                  <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-foreground">
                      Cor
                    </label>
                    <div className="flex gap-1.5">
                      {PRESET_COLORS.map((color) => (
                        <button
                          key={color}
                          type="button"
                          onClick={() => form.setValue('color', color)}
                          className={`size-7 rounded-md border-2 transition-all ${
                            form.watch('color') === color
                              ? 'border-foreground scale-110'
                              : 'border-transparent hover:scale-105'
                          }`}
                          style={{ backgroundColor: color }}
                          title={color}
                        />
                      ))}
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button type="submit" loading={createTag.isPending || updateTag.isPending}>
                      <Plus className="size-4" />
                      {editing ? 'Salvar' : 'Adicionar'}
                    </Button>
                    {editing && (
                      <Button type="button" variant="ghost" onClick={cancelEdit}>
                        Cancelar
                      </Button>
                    )}
                  </div>
                </div>
              </Form>
            </Section>
          </CardContent>
        </Card>

        <Card>
          <CardHeader
            title={`${tags?.length ?? 0} tag(ns)`}
            description="Use as tags para organizar e filtrar conversas por categoria."
          />
          <CardContent className="p-0">
            {(!tags || tags.length === 0) ? (
              <div className="p-5">
                <EmptyState
                  icon={Tag}
                  title="Nenhuma tag configurada"
                  description="Crie tags para classificar as conversas do WhatsApp."
                />
              </div>
            ) : (
              <div className="divide-y divide-surface-2">
                {tags.map((tag) => (
                  <div
                    key={tag.id}
                    className="flex items-center gap-3 px-5 py-3"
                  >
                    <span
                      className="h-3 w-3 shrink-0 rounded-full"
                      style={{ backgroundColor: tag.color ?? PRESET_COLORS[0] }}
                    />
                    <span className="flex-1 text-sm font-medium text-foreground">
                      {tag.name}
                    </span>
                    <Badge variant="neutral" className="text-[11px]">
                      Ordem {tag.sort_order}
                    </Badge>
                    <Can permission={Permission.WHATSAPP_TAG_UPDATE}>
                      <div className="flex gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => startEdit(tag)}
                          aria-label={`Editar ${tag.name}`}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        <Can permission={Permission.WHATSAPP_TAG_DELETE}>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleting(tag)}
                            aria-label={`Remover ${tag.name}`}
                            className="text-danger hover:bg-danger-soft hover:text-danger"
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </Can>
                      </div>
                    </Can>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </PageContent>

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        loading={deleteTag.isPending}
        title="Remover tag"
        description={
          <>
            Tem certeza que deseja remover a tag <strong>{deleting?.name}</strong>?
          </>
        }
        confirmLabel="Remover"
      />
    </Page>
  )
}

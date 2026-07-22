import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Pencil, Plus, Trash2, Zap } from 'lucide-react'
import { z } from 'zod'
import {
  Badge,
  Button,
  ButtonLink,
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
import {
  useKanbanStagesQuery,
  useCreateKanbanStage,
  useUpdateKanbanStage,
  useDeleteKanbanStage,
  useSeedDefaultStages,
} from '../hooks/useWhatsApp'
import type { KanbanStage } from '@/shared/types/models'

const stageSchema = z.object({
  name: z.string().min(1, 'Informe o nome da etapa'),
  color: z.string().optional(),
})

type StageFormValues = z.infer<typeof stageSchema>

const PRESET_COLORS = [
  '#6B7280', '#3B82F6', '#F59E0B', '#8B5CF6', '#10B981', '#EF4444',
  '#EC4899', '#14B8A6', '#F97316', '#6366F1',
]

export default function WhatsAppKanbanStagesPage() {
  const { data: stages, isPending } = useKanbanStagesQuery()
  const createStage = useCreateKanbanStage()
  const updateStage = useUpdateKanbanStage()
  const deleteStage = useDeleteKanbanStage()
  const seedDefaults = useSeedDefaultStages()

  const [editing, setEditing] = useState<KanbanStage | null>(null)
  const [deleting, setDeleting] = useState<KanbanStage | null>(null)

  const form = useForm<StageFormValues>({
    resolver: zodResolver(stageSchema),
    defaultValues: { name: '', color: PRESET_COLORS[0] },
  })

  const onSubmit = async (values: StageFormValues) => {
    if (editing) {
      await updateStage.mutateAsync({ id: editing.id, ...values })
      setEditing(null)
    } else {
      await createStage.mutateAsync(values)
    }
    form.reset({ name: '', color: PRESET_COLORS[0] })
  }

  const startEdit = (stage: KanbanStage) => {
    setEditing(stage)
    form.reset({ name: stage.name, color: stage.color ?? PRESET_COLORS[0] })
  }

  const cancelEdit = () => {
    setEditing(null)
    form.reset({ name: '', color: PRESET_COLORS[0] })
  }

  const confirmDelete = async () => {
    if (!deleting) return
    await deleteStage.mutateAsync(deleting.id)
    setDeleting(null)
  }

  if (isPending) return <Loading />

  return (
    <Page>
      <PageHeader
        title="Etapas do Kanban"
        description="Gerencie as etapas do funil de qualificação."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'WhatsApp', to: '/whatsapp' },
          { label: 'Kanban', to: '/whatsapp/kanban' },
          { label: 'Etapas' },
        ]}
        actions={
          <Can permission={Permission.WHATSAPP_KANBAN_UPDATE}>
            <Button
              variant="secondary"
              size="sm"
              loading={seedDefaults.isPending}
              onClick={() => seedDefaults.mutate()}
              disabled={!!editing}
            >
              <Zap className="size-4" />
              Criar padrões
            </Button>
          </Can>
        }
      />

      <PageContent>
        <Card className="mb-6">
          <CardContent>
            <Section
              title={editing ? 'Editar etapa' : 'Nova etapa'}
              description={editing ? `Editando "${editing.name}"` : 'Adicione uma nova etapa ao funil.'}
            >
              <Form form={form} onSubmit={onSubmit}>
                <div className="flex items-end gap-3">
                  <div className="flex-1">
                    <TextField name="name" label="Nome" placeholder="Ex.: Proposta Enviada" required />
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
                    <Button type="submit" loading={createStage.isPending || updateStage.isPending}>
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
            title={`${stages?.length ?? 0} etapa(s)`}
            description="Arraste no Kanban para reordenar. A ordem aqui é usada no quadro."
          />
          <CardContent className="p-0">
            {(!stages || stages.length === 0) ? (
              <div className="p-5">
                <EmptyState
                  icon={Zap}
                  title="Nenhuma etapa configurada"
                  description="Crie etapas manualmente ou use o botão 'Criar padrões' para gerar as 6 etapas iniciais."
                />
              </div>
            ) : (
              <div className="divide-y divide-surface-2">
                {stages.map((stage) => (
                  <div
                    key={stage.id}
                    className="flex items-center gap-3 px-5 py-3"
                  >
                    <span
                      className="h-3 w-3 shrink-0 rounded-full"
                      style={{ backgroundColor: stage.color ?? PRESET_COLORS[0] }}
                    />
                    <span className="flex-1 text-sm font-medium text-foreground">
                      {stage.name}
                    </span>
                    <Badge variant="neutral" className="text-[11px]">
                      Ordem {stage.sort_order}
                    </Badge>
                    <Can permission={Permission.WHATSAPP_KANBAN_UPDATE}>
                      <div className="flex gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => startEdit(stage)}
                          aria-label={`Editar ${stage.name}`}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setDeleting(stage)}
                          aria-label={`Remover ${stage.name}`}
                          className="text-danger hover:bg-danger-soft hover:text-danger"
                        >
                          <Trash2 className="size-4" />
                        </Button>
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
        loading={deleteStage.isPending}
        title="Remover etapa"
        description={
          <>
            Tem certeza que deseja remover a etapa <strong>{deleting?.name}</strong>?
          </>
        }
        confirmLabel="Remover"
      />
    </Page>
  )
}

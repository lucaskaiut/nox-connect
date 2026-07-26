import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button, Form, Section, TextField } from '@/shared/design-system'
import { isValidCpfOrCnpj } from '@/shared/utils/document'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCompleteCompany } from '../hooks/useOnboarding'
import type { OnboardingStatus } from '../whatsapp/types'

const schema = z.object({
  name: z.string().min(1, 'Informe o nome da empresa'),
  document: z
    .string()
    .min(1, 'Informe o CPF ou CNPJ')
    .refine(isValidCpfOrCnpj, 'Informe um CPF ou CNPJ válido'),
  email: z.string().min(1, 'Informe o e-mail').email('E-mail inválido'),
  phone: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

export function CompanyStep({
  status,
  onCompleted,
}: {
  status: OnboardingStatus
  onCompleted: () => void
}) {
  const completeCompany = useCompleteCompany()

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: status.company.name ?? '',
      document: status.company.document ?? '',
      email: status.company.email ?? '',
      phone: status.company.phone ?? '',
    },
  })

  useEffect(() => {
    form.reset({
      name: status.company.name ?? '',
      document: status.company.document ?? '',
      email: status.company.email ?? '',
      phone: status.company.phone ?? '',
    })
  }, [status.company, form])

  const onSubmit = async (values: FormValues) => {
    try {
      await completeCompany.mutateAsync({
        name: values.name,
        document: values.document,
        email: values.email,
        phone: values.phone || null,
      })
      onCompleted()
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Form form={form} onSubmit={onSubmit} className="space-y-6">
      <Section
        title="Dados da empresa"
        description="Confirme ou ajuste as informações do seu tenant."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="name" label="Nome da empresa" required />
          <TextField name="document" label="CPF/CNPJ" required />
          <TextField name="phone" label="Telefone" />
          <TextField name="email" label="E-mail" required />
        </div>
      </Section>

      <Button type="submit" loading={completeCompany.isPending}>
        Continuar
      </Button>
    </Form>
  )
}

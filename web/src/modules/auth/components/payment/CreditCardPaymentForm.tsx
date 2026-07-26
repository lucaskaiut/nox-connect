import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Form, SelectField, TextField } from '@/shared/design-system'
import { creditCardSchema, type CreditCardValues } from '../../schemas/checkout.schema'
import { useRegisterCheckoutStore } from '../../store/register-checkout.store'

export function CreditCardPaymentForm() {
  const paymentData = useRegisterCheckoutStore((state) => state.paymentData)
  const setPaymentData = useRegisterCheckoutStore((state) => state.setPaymentData)

  const form = useForm<CreditCardValues>({
    resolver: zodResolver(creditCardSchema),
    defaultValues: {
      number: String(paymentData.number ?? ''),
      holder_name: String(paymentData.holder_name ?? ''),
      exp_month: String(paymentData.exp_month ?? ''),
      exp_year: String(paymentData.exp_year ?? ''),
      cvv: String(paymentData.cvv ?? ''),
      installments: Number(paymentData.installments ?? 1),
    },
    mode: 'onChange',
  })

  useEffect(() => {
    const subscription = form.watch((values) => {
      setPaymentData(values as Record<string, unknown>)
    })

    return () => subscription.unsubscribe()
  }, [form, setPaymentData])

  return (
    <Form form={form} onSubmit={() => undefined} className="space-y-4">
      <TextField name="number" label="Número do cartão" placeholder="ACCT-000003" required />
      <TextField name="holder_name" label="Nome impresso" required />
      <div className="grid gap-4 sm:grid-cols-3">
        <TextField name="exp_month" label="Mês" placeholder="MM" required />
        <TextField name="exp_year" label="Ano" placeholder="AA" required />
        <TextField name="cvv" label="CVV" placeholder="123" required />
      </div>
      <SelectField
        name="installments"
        label="Parcelamento"
        options={Array.from({ length: 12 }, (_, i) => ({
          value: String(i + 1),
          label: `${i + 1}x`,
        }))}
      />
    </Form>
  )
}

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form, SelectField, TextField } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import {
  creditCardTokenSchema,
  toCreditCardPaymentData,
  type CreditCardTokenValues,
} from '../../schemas/checkout.schema'

interface CreditCardPaymentFormProps {
  amount: string
  loading?: boolean
  onSubmit: (paymentData: Record<string, unknown>) => void | Promise<void>
}

export function CreditCardPaymentForm({
  amount,
  loading = false,
  onSubmit,
}: CreditCardPaymentFormProps) {
  const form = useForm<CreditCardTokenValues>({
    resolver: zodResolver(creditCardTokenSchema),
    defaultValues: {
      credit_card_token: '',
      installments: 1,
    },
    mode: 'onBlur',
  })

  const installments = form.watch('installments') || 1
  const installmentValue = Number(amount) / installments

  return (
    <Form
      form={form}
      onSubmit={(values) => void onSubmit(toCreditCardPaymentData(values))}
      className="space-y-5"
    >
      <div className="space-y-4">
        <p className="text-sm text-muted">
          Informe o token gerado pelo checkout seguro do gateway. Dados de cartão (PAN/CVV) não
          são aceitos nesta interface.
        </p>
        <TextField
          name="credit_card_token"
          label="Token do cartão"
          placeholder="tok_..."
          autoComplete="off"
          required
        />
        <SelectField
          name="installments"
          label="Parcelamento"
          hint={
            installments > 1
              ? `${installments}x de ${formatCurrency(installmentValue.toFixed(2))}`
              : 'Pagamento à vista'
          }
          options={Array.from({ length: 12 }, (_, i) => {
            const count = i + 1
            const value = Number(amount) / count
            return {
              value: String(count),
              label:
                count === 1
                  ? `1x de ${formatCurrency(amount)}`
                  : `${count}x de ${formatCurrency(value.toFixed(2))}`,
            }
          })}
          required
        />
      </div>

      <Button type="submit" loading={loading} className="w-full sm:w-auto sm:min-w-64">
        Pagar {formatCurrency(amount)}
      </Button>
    </Form>
  )
}

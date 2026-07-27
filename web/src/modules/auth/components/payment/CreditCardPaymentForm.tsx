import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form, SelectField, TextField } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import {
  creditCardSchema,
  toCreditCardPaymentData,
  type CreditCardValues,
} from '../../schemas/checkout.schema'

export interface CreditCardHolderDefaults {
  name?: string | null
  email?: string | null
  document?: string | null
  phone?: string | null
}

interface CreditCardPaymentFormProps {
  amount: string
  defaults?: CreditCardHolderDefaults
  loading?: boolean
  onSubmit: (paymentData: Record<string, unknown>) => void | Promise<void>
}

export function CreditCardPaymentForm({
  amount,
  defaults,
  loading = false,
  onSubmit,
}: CreditCardPaymentFormProps) {
  const form = useForm<CreditCardValues>({
    resolver: zodResolver(creditCardSchema),
    defaultValues: {
      number: '',
      holder_name: defaults?.name ?? '',
      exp_month: '',
      exp_year: '',
      cvv: '',
      installments: 1,
      email: defaults?.email ?? '',
      document: defaults?.document ?? '',
      phone: defaults?.phone ?? '',
      postal_code: '',
      address_number: '',
      address_complement: '',
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
        <p className="text-sm font-medium text-foreground">Dados do cartão</p>
        <TextField
          name="number"
          label="Número do cartão"
          placeholder="ACCT-000003"
          inputMode="numeric"
          autoComplete="cc-number"
          required
        />
        <TextField
          name="holder_name"
          label="Nome impresso no cartão"
          autoComplete="cc-name"
          required
        />
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField
            name="exp_month"
            label="Mês"
            placeholder="MM"
            inputMode="numeric"
            autoComplete="cc-exp-month"
            required
          />
          <TextField
            name="exp_year"
            label="Ano"
            placeholder="AA"
            inputMode="numeric"
            autoComplete="cc-exp-year"
            required
          />
          <TextField
            name="cvv"
            label="CVV"
            placeholder="123"
            inputMode="numeric"
            autoComplete="cc-csc"
            required
          />
        </div>
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

      <div className="space-y-4 border-t border-border pt-5">
        <p className="text-sm font-medium text-foreground">Dados do titular</p>
        <p className="text-[13px] text-muted">
          Devem coincidir com o cadastro no emissor do cartão. Divergências podem negar a
          transação.
        </p>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="email" label="E-mail" type="email" autoComplete="email" required />
          <TextField
            name="document"
            label="CPF ou CNPJ"
            inputMode="numeric"
            autoComplete="off"
            required
          />
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            name="phone"
            label="Telefone"
            placeholder="11999999999"
            inputMode="tel"
            autoComplete="tel"
            required
          />
          <TextField
            name="postal_code"
            label="CEP"
            placeholder="01310100"
            inputMode="numeric"
            autoComplete="postal-code"
            required
          />
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            name="address_number"
            label="Número"
            autoComplete="address-line2"
            required
          />
          <TextField
            name="address_complement"
            label="Complemento"
            autoComplete="address-line3"
          />
        </div>
      </div>

      <Button type="submit" loading={loading} className="w-full sm:w-auto sm:min-w-64">
        Pagar {formatCurrency(amount)}
      </Button>
    </Form>
  )
}

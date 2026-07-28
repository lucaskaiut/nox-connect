<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Gateways\Asaas\AsaasClient;
use App\Modules\Billing\Gateways\Asaas\AsaasException;
use App\Modules\Billing\Gateways\Asaas\AsaasPaymentMapper;
use Illuminate\Validation\ValidationException;

/**
 * Gateway Asaas + cartão de crédito (chave: asaasCreditCard).
 *
 * Fronteira PCI: a API não aceita PAN/CVV. Fluxos via payment_data:
 * - Sem token → cobrança + invoiceUrl (checkout hospedado Asaas)
 * - Com credit_card_token / creditCardToken → cobrança com token pré-gerado
 */
class AsaasCreditCardGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly AsaasClient $asaas,
    ) {}

    public function key(): string
    {
        return 'asaasCreditCard';
    }

    public function label(): string
    {
        return 'Cartão de crédito (Asaas)';
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function createCustomer(CustomerDataDTO $customer): GatewayCustomerDTO
    {
        $document = $this->digits($customer->document);

        if ($document !== '') {
            $existing = $this->asaas->listCustomers(['cpfCnpj' => $document]);
            $first = $existing['data'][0] ?? null;

            if (is_array($first) && filled($first['id'] ?? null)) {
                return $this->mapCustomer($first);
            }
        }

        $payload = array_filter([
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $document !== '' ? $document : null,
            'mobilePhone' => $this->digits($customer->phone),
            'externalReference' => $customer->externalId,
            'notificationDisabled' => true,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            return $this->mapCustomer($this->asaas->createCustomer($payload));
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'customer');
        }
    }

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void
    {
        $payload = array_filter([
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $this->digits($customer->document) ?: null,
            'mobilePhone' => $this->digits($customer->phone),
            'externalReference' => $customer->externalId,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            $this->asaas->updateCustomer($externalCustomerId, $payload);
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'customer');
        }
    }

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO
    {
        if ($payment->paymentMethod !== PaymentMethod::CREDIT_CARD) {
            throw ValidationException::withMessages([
                'payment_method' => ['AsaasCreditCardGateway aceita apenas pagamento via cartão de crédito.'],
            ]);
        }

        /** @var array<string, mixed> $paymentData */
        $paymentData = is_array($payment->metadata['payment_data'] ?? null)
            ? $payment->metadata['payment_data']
            : [];

        $remoteIp = $this->resolveRemoteIp($paymentData);

        if ($remoteIp === '') {
            throw ValidationException::withMessages([
                'payment_data.remote_ip' => ['O IP do pagador (remote_ip) é obrigatório para cobranças com cartão.'],
            ]);
        }

        $payload = [
            'customer' => $payment->customerExternalId,
            'billingType' => 'CREDIT_CARD',
            'value' => (float) $payment->amount,
            'dueDate' => $payment->dueDate->format('Y-m-d'),
            'description' => $this->truncate($payment->description ?? 'Assinatura', 500),
            'externalReference' => $payment->metadata['invoice_uuid']
                ?? $payment->metadata['subscription_uuid']
                ?? null,
            'remoteIp' => $remoteIp,
        ];

        $installments = $this->resolveInstallments($paymentData);

        if ($installments > 1) {
            $payload['installmentCount'] = $installments;
            $payload['totalValue'] = (float) $payment->amount;
        }

        if (array_key_exists('authorize_only', $paymentData) || array_key_exists('authorizeOnly', $paymentData)) {
            $payload['authorizeOnly'] = (bool) ($paymentData['authorize_only'] ?? $paymentData['authorizeOnly']);
        }

        $token = $this->stringFrom($paymentData, ['credit_card_token', 'creditCardToken']);

        if ($token !== null) {
            $payload['creditCardToken'] = $token;
        } elseif ($this->containsRawCardData($paymentData)) {
            throw ValidationException::withMessages([
                'payment_data' => [
                    'Dados de cartão brutos (PAN/CVV) não são aceitos. Use credit_card_token ou conclua o pagamento na fatura Asaas.',
                ],
            ]);
        }

        $payload = array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        try {
            $response = $this->asaas->createPayment(
                $payload,
                (int) config('asaas.credit_card_timeout', 60),
            );
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }

        return AsaasPaymentMapper::toGatewayPayment($response);
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        try {
            return AsaasPaymentMapper::toGatewayPayment(
                $this->asaas->getPayment($externalPaymentId),
            );
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        try {
            $this->asaas->deletePayment($externalPaymentId);
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapCustomer(array $payload): GatewayCustomerDTO
    {
        return new GatewayCustomerDTO(
            externalId: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            metadata: array_filter([
                'document' => $payload['cpfCnpj'] ?? null,
                'phone' => $payload['mobilePhone'] ?? $payload['phone'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function containsRawCardData(array $paymentData): bool
    {
        $blockedKeys = [
            'number', 'cvv', 'ccv', 'pan', 'holder_name', 'holderName',
            'exp_month', 'exp_year', 'credit_card', 'creditCard',
            'credit_card_holder_info', 'creditCardHolderInfo',
        ];

        foreach ($blockedKeys as $key) {
            if (array_key_exists($key, $paymentData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function resolveInstallments(array $paymentData): int
    {
        $raw = $paymentData['installments']
            ?? $paymentData['installment_count']
            ?? $paymentData['installmentCount']
            ?? 1;

        $count = (int) $raw;

        if ($count < 1) {
            return 1;
        }

        if ($count > 21) {
            throw ValidationException::withMessages([
                'payment_data.installments' => ['O número máximo de parcelas é 21 (Visa/Master) ou 12 (demais bandeiras).'],
            ]);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function resolveRemoteIp(array $paymentData): string
    {
        $ip = $this->stringFrom($paymentData, ['remote_ip', 'remoteIp']);

        if ($ip !== null && $ip !== '') {
            return $ip;
        }

        return (string) (request()?->ip() ?? '');
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private function stringFrom(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $source) || $source[$key] === null) {
                continue;
            }

            $value = trim((string) $source[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    private function toValidationException(AsaasException $e, string $field): ValidationException
    {
        if ($e->isClientError()) {
            return ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }

        throw $e;
    }
}

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
 * Fluxos suportados via payment_data (metadata.payment_data):
 * - Sem dados de cartão → cobrança + invoiceUrl (checkout Asaas)
 * - Com credit_card + credit_card_holder_info (ou campos flat) → captura imediata
 * - Com credit_card_token → reutilização do token do cliente
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
        } else {
            $creditCard = $this->resolveCreditCard($paymentData);
            $holderInfo = $this->resolveHolderInfo($paymentData);

            if ($creditCard !== null && $holderInfo !== null) {
                $payload['creditCard'] = $creditCard;
                $payload['creditCardHolderInfo'] = $holderInfo;
            } elseif ($creditCard !== null xor $holderInfo !== null) {
                throw ValidationException::withMessages([
                    'payment_data' => [
                        'Para captura imediata envie credit_card e credit_card_holder_info completos, ou omita ambos para pagar na fatura Asaas.',
                    ],
                ]);
            }
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
     * @return array{holderName: string, number: string, expiryMonth: string, expiryYear: string, ccv: string}|null
     */
    private function resolveCreditCard(array $paymentData): ?array
    {
        /** @var array<string, mixed> $nested */
        $nested = is_array($paymentData['credit_card'] ?? null)
            ? $paymentData['credit_card']
            : (is_array($paymentData['creditCard'] ?? null) ? $paymentData['creditCard'] : []);

        $holderName = $this->stringFrom($nested, ['holderName', 'holder_name'])
            ?? $this->stringFrom($paymentData, ['holder_name', 'holderName']);
        $number = $this->digits(
            $this->stringFrom($nested, ['number'])
                ?? $this->stringFrom($paymentData, ['number']),
        );
        $expiryMonth = $this->stringFrom($nested, ['expiryMonth', 'expiry_month', 'exp_month'])
            ?? $this->stringFrom($paymentData, ['exp_month', 'expiry_month', 'expiryMonth']);
        $expiryYear = $this->normalizeExpiryYear(
            $this->stringFrom($nested, ['expiryYear', 'expiry_year', 'exp_year'])
                ?? $this->stringFrom($paymentData, ['exp_year', 'expiry_year', 'expiryYear']),
        );
        $ccv = $this->stringFrom($nested, ['ccv', 'cvv'])
            ?? $this->stringFrom($paymentData, ['cvv', 'ccv']);

        $fields = [$holderName, $number, $expiryMonth, $expiryYear, $ccv];

        if (count(array_filter($fields, static fn (?string $v): bool => $v !== null && $v !== '')) === 0) {
            return null;
        }

        if (in_array(null, $fields, true) || in_array('', $fields, true)) {
            throw ValidationException::withMessages([
                'payment_data.credit_card' => [
                    'Dados do cartão incompletos. Informe holder_name, number, exp_month, exp_year e cvv.',
                ],
            ]);
        }

        return [
            'holderName' => (string) $holderName,
            'number' => (string) $number,
            'expiryMonth' => str_pad((string) $expiryMonth, 2, '0', STR_PAD_LEFT),
            'expiryYear' => (string) $expiryYear,
            'ccv' => (string) $ccv,
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentData
     * @return array{name: string, email: string, cpfCnpj: string, postalCode: string, addressNumber: string, phone: string, addressComplement?: string, mobilePhone?: string}|null
     */
    private function resolveHolderInfo(array $paymentData): ?array
    {
        /** @var array<string, mixed> $nested */
        $nested = is_array($paymentData['credit_card_holder_info'] ?? null)
            ? $paymentData['credit_card_holder_info']
            : (is_array($paymentData['creditCardHolderInfo'] ?? null) ? $paymentData['creditCardHolderInfo'] : []);

        $source = $nested !== [] ? $nested : $paymentData;

        $nameKeys = $nested !== []
            ? ['name', 'holder_name', 'holderName']
            : ['name'];

        $name = $this->stringFrom($source, $nameKeys);
        $email = $this->stringFrom($source, ['email']);
        $cpfCnpj = $this->digits(
            $this->stringFrom($source, ['cpfCnpj', 'cpf_cnpj', 'document']),
        );
        $postalCode = $this->digits(
            $this->stringFrom($source, ['postalCode', 'postal_code', 'zip_code', 'cep']),
        );
        $addressNumber = $this->stringFrom($source, ['addressNumber', 'address_number']);
        $phone = $this->digits(
            $this->stringFrom($source, ['phone', 'mobile_phone', 'mobilePhone']),
        );

        $required = [$name, $email, $cpfCnpj, $postalCode, $addressNumber, $phone];

        if (count(array_filter($required, static fn (?string $v): bool => $v !== null && $v !== '')) === 0) {
            // Sem bloco de titular: fluxo invoiceUrl (quando também não há cartão).
            if ($nested === [] && ! $this->hasHolderKeys($paymentData)) {
                return null;
            }
        }

        if (in_array(null, $required, true) || in_array('', $required, true)) {
            throw ValidationException::withMessages([
                'payment_data.credit_card_holder_info' => [
                    'Dados do titular incompletos. Informe name, email, cpf_cnpj (ou document), postal_code, address_number e phone.',
                ],
            ]);
        }

        $info = [
            'name' => (string) $name,
            'email' => (string) $email,
            'cpfCnpj' => (string) $cpfCnpj,
            'postalCode' => (string) $postalCode,
            'addressNumber' => (string) $addressNumber,
            'phone' => (string) $phone,
        ];

        $complement = $this->stringFrom($source, ['addressComplement', 'address_complement']);
        if ($complement !== null) {
            $info['addressComplement'] = $complement;
        }

        $mobile = $this->digits($this->stringFrom($source, ['mobilePhone', 'mobile_phone']));
        if ($mobile !== '') {
            $info['mobilePhone'] = $mobile;
        }

        return $info;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function hasHolderKeys(array $paymentData): bool
    {
        foreach (['name', 'email', 'cpf_cnpj', 'cpfCnpj', 'document', 'postal_code', 'postalCode', 'cep', 'address_number', 'addressNumber', 'phone'] as $key) {
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

    private function normalizeExpiryYear(?string $year): ?string
    {
        if ($year === null || $year === '') {
            return null;
        }

        $digits = $this->digits($year);

        if (strlen($digits) === 2) {
            return '20'.$digits;
        }

        return $digits;
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

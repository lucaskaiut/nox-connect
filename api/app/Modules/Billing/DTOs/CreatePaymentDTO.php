<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PaymentMethod;
use Carbon\CarbonInterface;

final readonly class CreatePaymentDTO
{
    public function __construct(
        public string $customerExternalId,
        public string $amount,
        public PaymentMethod $paymentMethod,
        public CarbonInterface $dueDate,
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array{customer_external_id: string, amount: string|float|int, payment_method: string, due_date: CarbonInterface|string, description?: ?string, metadata?: ?array}  $data
     */
    public static function fromArray(array $data): self
    {
        $dueDate = $data['due_date'] instanceof CarbonInterface
            ? $data['due_date']
            : \Carbon\CarbonImmutable::parse($data['due_date']);

        return new self(
            customerExternalId: $data['customer_external_id'],
            amount: number_format((float) $data['amount'], 2, '.', ''),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            dueDate: $dueDate,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}

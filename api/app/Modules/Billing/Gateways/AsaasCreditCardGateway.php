<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\PaymentMethod;
use RuntimeException;

/**
 * Placeholder para integração real Asaas + cartão (chave: asaasCreditCard).
 * Ative em config/billing.php → active quando a integração estiver pronta.
 */
class AsaasCreditCardGateway implements PaymentGatewayInterface
{
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
        throw $this->notImplemented();
    }

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void
    {
        throw $this->notImplemented();
    }

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO
    {
        throw $this->notImplemented();
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        throw $this->notImplemented();
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        throw $this->notImplemented();
    }

    private function notImplemented(): RuntimeException
    {
        return new RuntimeException('AsaasCreditCardGateway ainda não foi implementado. Remova-o de billing.active até concluir a integração.');
    }
}

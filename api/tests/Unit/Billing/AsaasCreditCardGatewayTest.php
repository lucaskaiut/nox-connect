<?php

namespace Tests\Unit\Billing;

use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Gateways\Asaas\AsaasClient;
use App\Modules\Billing\Gateways\AsaasCreditCardGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsaasCreditCardGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'asaas.api_key' => '$aact_hmlg_test_key',
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.user_agent' => 'NoxConnectTest/1.0',
            'asaas.timeout' => 30,
            'asaas.credit_card_timeout' => 60,
        ]);
    }

    public function test_create_customer_reuses_existing_by_document(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/customers*' => Http::response([
                'data' => [[
                    'id' => 'cus_existing',
                    'name' => 'Empresa Existente',
                    'email' => 'existente@teste.com',
                    'cpfCnpj' => '11222333000181',
                ]],
                'totalCount' => 1,
            ]),
        ]);

        $gateway = $this->gateway();

        $customer = $gateway->createCustomer(new CustomerDataDTO(
            name: 'Empresa Nova',
            email: 'nova@teste.com',
            document: '11.222.333/0001-81',
        ));

        $this->assertSame('cus_existing', $customer->externalId);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/customers')
                && $request['cpfCnpj'] === '11222333000181';
        });
    }

    public function test_create_payment_without_card_uses_invoice_url_flow(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'object' => 'payment',
                'id' => 'pay_invoice_flow',
                'status' => 'PENDING',
                'value' => 99.9,
                'billingType' => 'CREDIT_CARD',
                'dueDate' => '2026-07-30',
                'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_invoice_flow',
                'invoiceNumber' => '000123',
            ]),
        ]);

        $payment = $this->gateway()->createPayment(new CreatePaymentDTO(
            customerExternalId: 'cus_123',
            amount: '99.90',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            dueDate: CarbonImmutable::parse('2026-07-30'),
            description: 'Assinatura Pro',
            metadata: [
                'invoice_uuid' => 'inv-uuid',
                'payment_data' => [
                    'remote_ip' => '203.0.113.10',
                ],
            ],
        ));

        $this->assertSame('pay_invoice_flow', $payment->externalId);
        $this->assertSame(GatewayPaymentStatus::PENDING, $payment->status);
        $this->assertSame('https://sandbox.asaas.com/i/pay_invoice_flow', $payment->metadata['invoice_url']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/payments')
                && $body['billingType'] === 'CREDIT_CARD'
                && $body['customer'] === 'cus_123'
                && $body['remoteIp'] === '203.0.113.10'
                && ! isset($body['creditCard'])
                && $request->hasHeader('access_token', '$aact_hmlg_test_key')
                && $request->hasHeader('User-Agent', 'NoxConnectTest/1.0');
        });
    }

    public function test_create_payment_with_card_and_holder_processes_immediately(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'object' => 'payment',
                'id' => 'pay_card_ok',
                'status' => 'CONFIRMED',
                'value' => 149.9,
                'billingType' => 'CREDIT_CARD',
                'dueDate' => '2026-07-30',
                'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_card_ok',
                'creditCard' => [
                    'creditCardNumber' => '8829',
                    'creditCardBrand' => 'VISA',
                    'creditCardToken' => 'token_abc',
                ],
            ]),
        ]);

        $payment = $this->gateway()->createPayment(new CreatePaymentDTO(
            customerExternalId: 'cus_123',
            amount: '149.90',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            dueDate: CarbonImmutable::parse('2026-07-30'),
            metadata: [
                'payment_data' => [
                    'remote_ip' => '203.0.113.10',
                    'installments' => 3,
                    'number' => '4111111111111111',
                    'holder_name' => 'John Doe',
                    'exp_month' => '07',
                    'exp_year' => '28',
                    'cvv' => '123',
                    'credit_card_holder_info' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'cpf_cnpj' => '24971563792',
                        'postal_code' => '01310-100',
                        'address_number' => '100',
                        'phone' => '11999999999',
                    ],
                ],
            ],
        ));

        $this->assertSame(GatewayPaymentStatus::PAID, $payment->status);
        $this->assertSame('token_abc', $payment->metadata['credit_card_token']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $body['installmentCount'] === 3
                && $body['totalValue'] === 149.9
                && $body['creditCard']['expiryYear'] === '2028'
                && $body['creditCard']['number'] === '4111111111111111'
                && $body['creditCardHolderInfo']['cpfCnpj'] === '24971563792'
                && $body['creditCardHolderInfo']['postalCode'] === '01310100';
        });
    }

    public function test_create_payment_with_token_omits_card_objects(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_token',
                'status' => 'CONFIRMED',
                'value' => 50,
                'billingType' => 'CREDIT_CARD',
                'dueDate' => '2026-07-30',
            ]),
        ]);

        $this->gateway()->createPayment(new CreatePaymentDTO(
            customerExternalId: 'cus_123',
            amount: '50.00',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            dueDate: CarbonImmutable::parse('2026-07-30'),
            metadata: [
                'payment_data' => [
                    'remote_ip' => '203.0.113.10',
                    'credit_card_token' => 'tok_reuse',
                ],
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['creditCardToken'] ?? null) === 'tok_reuse'
                && ! isset($body['creditCard'])
                && ! isset($body['creditCardHolderInfo']);
        });
    }

    public function test_asaas_400_becomes_validation_exception(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'errors' => [[
                    'code' => 'invalid_creditCard',
                    'description' => 'Transação não autorizada',
                ]],
            ], 400),
        ]);

        $this->expectException(ValidationException::class);

        $this->gateway()->createPayment(new CreatePaymentDTO(
            customerExternalId: 'cus_123',
            amount: '50.00',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            dueDate: CarbonImmutable::parse('2026-07-30'),
            metadata: [
                'payment_data' => [
                    'remote_ip' => '203.0.113.10',
                    'credit_card_token' => 'tok_bad',
                ],
            ],
        ));
    }

    public function test_get_payment_maps_overdue_to_expired(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/pay_1' => Http::response([
                'id' => 'pay_1',
                'status' => 'OVERDUE',
                'value' => 10,
                'dueDate' => '2026-07-01',
            ]),
        ]);

        $payment = $this->gateway()->getPayment('pay_1');

        $this->assertSame(GatewayPaymentStatus::EXPIRED, $payment->status);
    }

    public function test_cancel_payment_calls_delete(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/payments/pay_1' => Http::response([
                'deleted' => true,
                'id' => 'pay_1',
            ]),
        ]);

        $this->gateway()->cancelPayment('pay_1');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/payments/pay_1'));
    }

    private function gateway(): AsaasCreditCardGateway
    {
        return new AsaasCreditCardGateway(app(AsaasClient::class));
    }
}

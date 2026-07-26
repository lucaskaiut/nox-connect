<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Gateways\MockPixGateway;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Support\SubscriptionSuspensionRules;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_generate_invoice_creates_local_charge_without_gateway(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'name' => 'Empresa A',
            'email' => 'a@empresa.com',
            'document' => '11222333000181',
        ]);

        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '149.90',
        ]);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill(['next_billing_at' => now()->subMinute()])->save();

        $invoice = app(BillingService::class)->generateInvoice($subscription->fresh(['plan', 'tenant']));

        $this->assertSame('149.90', (string) $invoice->amount);
        $this->assertSame(InvoiceStatus::PENDING, $invoice->status);
        $this->assertNull($invoice->gateway);
        $this->assertNull($invoice->external_id);
        $this->assertNull($invoice->pix_code);
        $this->assertTrue($invoice->awaitsPaymentMethod());
    }

    public function test_initiate_payment_creates_gateway_charge(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '49.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $paid = $billing->initiatePayment($invoice, 'mockPix');

        $this->assertSame('mockPix', $paid->gateway);
        $this->assertNotEmpty($paid->external_id);
        $this->assertNotEmpty($paid->pix_code);
        $this->assertFalse($paid->awaitsPaymentMethod());
        $this->assertSame('mockPix', $subscription->fresh()->payment_gateway);
    }

    public function test_payment_confirmation_updates_subscription_and_invoice(): void
    {
        Carbon::setTestNow('2026-03-01 09:00:00');

        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
            'recurrence_value' => 30,
        ]);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);
        $invoice = $billing->initiatePayment(
            $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant'])),
            'mockPix',
        );

        /** @var MockPixGateway $gateway */
        $gateway = app(MockPixGateway::class);
        $gateway->markAsPaid($invoice->external_id);

        $synced = $billing->syncInvoiceStatus($invoice->fresh());

        $this->assertSame(InvoiceStatus::PAID, $synced->status);
        $this->assertNotNull($synced->paid_at);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertSame('2026-03-31 09:00:00', $subscription->next_billing_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->getKey(),
            'event' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_expired_payment_marks_subscription_past_due(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);

        $billing = app(BillingService::class);
        $invoice = $billing->initiatePayment(
            $billing->createLocalInvoice($subscription->fresh(['plan', 'tenant'])),
            'mockPix',
        );

        app(MockPixGateway::class)->markAsExpired($invoice->external_id);
        $billing->syncInvoiceStatus($invoice->fresh());

        $this->assertSame(InvoiceStatus::EXPIRED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
    }

    public function test_local_invoice_expires_and_marks_past_due(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create();
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $billing = app(BillingService::class);

        $invoice = $billing->createLocalInvoice(
            $subscription->fresh(['plan', 'tenant']),
            dueDate: now()->subDay()->toImmutable(),
            expiresAt: now()->subHour()->toImmutable(),
        );

        $billing->expireOverdueLocalInvoices();

        $this->assertSame(InvoiceStatus::EXPIRED, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
    }

    public function test_pay_endpoint_initiates_gateway_payment(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '79.90']);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $invoice = app(BillingService::class)->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $admin = $this->createAdmin($child);
        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->postJson("/api/billing/invoices/{$invoice->uuid}/pay", [
            'payment_gateway' => 'mockPix',
        ])
            ->assertOk()
            ->assertJsonPath('data.gateway', 'mockPix')
            ->assertJsonPath('data.awaiting_payment_method', false);

        $this->assertNotEmpty($invoice->fresh()->pix_code);
    }

    public function test_generate_invoices_command_creates_due_charges(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create(['price' => '59.90']);

        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $subscription->forceFill([
            'status' => SubscriptionStatus::ACTIVE,
            'next_billing_at' => now()->subHour(),
        ])->save();

        Artisan::call('billing:generate-invoices');

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->getKey(),
            'amount' => '59.90',
            'status' => InvoiceStatus::PENDING->value,
            'gateway' => null,
        ]);
    }

    public function test_suspend_command_blocks_after_max_expired_invoices(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->create();

        $subscription = Subscription::factory()
            ->forTenant($child)
            ->forPlan($plan)
            ->pastDue()
            ->create();

        Invoice::factory()
            ->count(SubscriptionSuspensionRules::MAX_EXPIRED_INVOICES)
            ->forSubscription($subscription)
            ->expired()
            ->create();

        Artisan::call('billing:suspend-expired-subscriptions');

        $this->assertSame(SubscriptionStatus::SUSPENDED, $subscription->fresh()->status);
    }

    public function test_middleware_blocks_child_without_active_subscription(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        TenantContext::set($child);

        $middleware = app(\App\Modules\Billing\Http\Middleware\EnsureActiveSubscription::class);
        $result = $middleware->handle(
            request(),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(402, $result->getStatusCode());
        $this->assertStringContainsString('Assinatura', $result->getContent());
    }

    public function test_list_invoices_for_current_tenant(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $plan = Plan::factory()->forTenant($umbrella)->withoutTrial()->create([
            'price' => '49.90',
        ]);
        $subscription = app(SubscriptionService::class)->createForTenant($child, $plan);
        $invoice = app(BillingService::class)->createLocalInvoice($subscription->fresh(['plan', 'tenant']));

        $admin = $this->createAdmin($child);
        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->getJson('/api/billing/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->uuid)
            ->assertJsonPath('data.0.amount', '49.90')
            ->assertJsonPath('data.0.awaiting_payment_method', true);
    }
}

<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Gera cobranças para assinaturas com next_billing_at vencido';

    public function handle(SubscriptionService $subscriptions, BillingService $billing): int
    {
        $due = $subscriptions->dueForBilling();
        $generated = 0;

        foreach ($due as $subscription) {
            $invoice = $billing->generateInvoice($subscription);
            $generated++;
            $this->line("Invoice {$invoice->uuid} gerada para subscription {$subscription->uuid}");
        }

        $this->info("Cobranças geradas: {$generated}");

        return self::SUCCESS;
    }
}

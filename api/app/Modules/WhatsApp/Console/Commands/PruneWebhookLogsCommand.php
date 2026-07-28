<?php

namespace App\Modules\WhatsApp\Console\Commands;

use App\Modules\WhatsApp\Models\WhatsAppWebhookLog;
use Illuminate\Console\Command;

class PruneWebhookLogsCommand extends Command
{
    protected $signature = 'whatsapp:prune-webhook-logs {--days=30 : Retention period in days}';

    protected $description = 'Remove WhatsApp webhook logs older than the retention period (default 30 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = WhatsAppWebhookLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Webhook logs removidos: {$deleted} (anteriores a {$cutoff->toDateString()})");

        return self::SUCCESS;
    }
}

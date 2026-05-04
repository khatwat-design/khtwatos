<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientAutomatedReportDeliveryService;
use Illuminate\Console\Command;

class SendClientWeeklyReportsCommand extends Command
{
    protected $signature = 'portal:send-weekly-client-reports {--limit=400}';

    protected $description = 'إرسال تقرير أسبوعي مبسّط للعملاء (واتساب و/أو بوابة)';

    public function handle(ClientAutomatedReportDeliveryService $delivery): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $count = 0;
        Client::query()
            ->whereNotNull('portal_username')
            ->where('portal_username', '!=', '')
            ->orderBy('id')
            ->limit($limit)
            ->each(function (Client $client) use ($delivery, &$count): void {
                $delivery->deliverWeekly($client);
                $count++;
            });

        $this->info("Processed {$count} clients for weekly reports.");

        return self::SUCCESS;
    }
}

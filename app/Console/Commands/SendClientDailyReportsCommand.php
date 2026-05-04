<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientAutomatedReportDeliveryService;
use Illuminate\Console\Command;

class SendClientDailyReportsCommand extends Command
{
    protected $signature = 'portal:send-daily-client-reports {--limit=400}';

    protected $description = 'إرسال تقرير يومي مبسّط للعملاء (واتساب و/أو بوابة)';

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
                $delivery->deliverDaily($client);
                $count++;
            });

        $this->info("Processed {$count} clients for daily reports.");

        return self::SUCCESS;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAutomatedReportLog;
use App\Models\ClientPortalNote;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClientAutomatedReportDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_creates_portal_note_when_delivery_portal_only(): void
    {
        Config::set('services.client_reports.enabled_daily', true);
        Config::set('services.client_reports.delivery', 'portal');

        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل تقرير',
            'portal_username' => 'rep_user',
            'portal_password' => bcrypt('x'),
            'current_pipeline_stage_id' => $stage->id,
        ]);

        Artisan::call('portal:send-daily-client-reports', ['--limit' => 20]);

        $this->assertGreaterThan(0, ClientPortalNote::query()->where('client_id', $client->id)->count());
        $this->assertDatabaseHas('client_automated_report_logs', [
            'client_id' => $client->id,
            'report_type' => 'daily',
            'status' => 'sent',
        ]);
    }

    public function test_second_daily_run_does_not_duplicate_sent(): void
    {
        Config::set('services.client_reports.enabled_daily', true);
        Config::set('services.client_reports.delivery', 'portal');

        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل تكرار تقرير',
            'portal_username' => 'rep_user2',
            'portal_password' => bcrypt('x'),
            'current_pipeline_stage_id' => $stage->id,
        ]);

        Artisan::call('portal:send-daily-client-reports', ['--limit' => 20]);
        Artisan::call('portal:send-daily-client-reports', ['--limit' => 20]);

        $this->assertSame(1, ClientPortalNote::query()->where('client_id', $client->id)->count());
        $this->assertSame(1, ClientAutomatedReportLog::query()->where('client_id', $client->id)->where('report_type', 'daily')->where('status', 'sent')->count());
    }
}

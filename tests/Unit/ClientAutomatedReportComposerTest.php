<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use App\Models\PipelineStage;
use App\Services\ClientAutomatedReportComposer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAutomatedReportComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_includes_delta_language(): void
    {
        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);
        $client = Client::query()->create([
            'name' => 'فاطمة العميل',
            'current_pipeline_stage_id' => $stage->id,
        ]);

        $today = Carbon::parse('2026-05-10');
        $yesterday = $today->copy()->subDay();

        ClientDailySale::query()->create([
            'client_id' => $client->id,
            'sales_date' => $yesterday,
            'orders_count' => 2,
            'revenue' => 100,
            'source' => 'portal',
        ]);
        ClientDailySale::query()->create([
            'client_id' => $client->id,
            'sales_date' => $today,
            'orders_count' => 4,
            'revenue' => 200,
            'source' => 'portal',
        ]);

        $composer = new ClientAutomatedReportComposer;
        $out = $composer->composeDaily($client->fresh(), $today);

        $this->assertStringContainsString('فاطمة', $out['text']);
        $this->assertStringContainsString('مقارنة بأمس', $out['text']);
        $this->assertSame('2026-05-10', $out['period_key']);
    }

    public function test_weekly_report_has_insights(): void
    {
        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);
        $client = Client::query()->create([
            'name' => 'عميل أسبوعي',
            'current_pipeline_stage_id' => $stage->id,
        ]);

        $end = Carbon::parse('2026-05-10');
        for ($i = 0; $i < 7; $i++) {
            $d = $end->copy()->subDays($i);
            ClientDailySale::query()->create([
                'client_id' => $client->id,
                'sales_date' => $d,
                'orders_count' => 1,
                'revenue' => 50,
                'source' => 'portal',
            ]);
            ClientCampaignUpdate::query()->create([
                'client_id' => $client->id,
                'report_date' => $d,
                'ad_spend' => 20,
                'messages_count' => 10,
                'leads_count' => 0,
                'purchases_count' => 0,
            ]);
        }

        $composer = new ClientAutomatedReportComposer;
        $out = $composer->composeWeekly($client->fresh(), $end);

        $this->assertStringContainsString('ملخص أسبوعك', $out['text']);
        $this->assertStringContainsString('لمحة سريعة', $out['text']);
    }
}

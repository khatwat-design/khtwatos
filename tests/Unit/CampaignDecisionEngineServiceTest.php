<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\PipelineStage;
use App\Services\CampaignDecisionEngineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignDecisionEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_low_roas_day_as_bad_and_surfaces_recommended_actions(): void
    {
        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل جديد',
            'sort_order' => 1,
        ]);
        $client = Client::query()->create([
            'name' => 'Test Client',
            'current_pipeline_stage_id' => $stage->id,
        ]);

        $today = Carbon::parse('2026-05-01');
        $yesterday = $today->copy()->subDay();

        ClientCampaignUpdate::query()->create([
            'client_id' => $client->id,
            'report_date' => $yesterday,
            'ad_spend' => 100,
            'messages_count' => 50,
            'clicks_count' => 120,
            'leads_count' => 10,
            'purchases_count' => 5,
            'campaign_revenue' => 400,
            'roas' => 4.0,
            'cpa' => 20,
            'cvr' => 10,
        ]);

        ClientCampaignUpdate::query()->create([
            'client_id' => $client->id,
            'report_date' => $today,
            'ad_spend' => 100,
            'messages_count' => 50,
            'clicks_count' => 40,
            'leads_count' => 8,
            'purchases_count' => 2,
            'campaign_revenue' => 120,
            'roas' => 1.2,
            'cpa' => 50,
            'cvr' => 4,
        ]);

        $service = new CampaignDecisionEngineService;
        $payload = $service->buildPayload(
            $client->fresh(),
            $client->campaignUpdates()->get(),
            collect(),
        );

        $this->assertTrue($payload['enabled']);
        $latest = collect($payload['campaigns'])->firstWhere('report_date', $today->toDateString());
        $this->assertNotNull($latest);
        $this->assertSame('bad', $latest['health']);
        $this->assertSame('high', $latest['priority']);
        $this->assertGreaterThanOrEqual(1, count($latest['actions']));
        $this->assertNotEmpty($payload['recommended_actions']);
        $this->assertNotNull($payload['daily_insight']['key_issue'] ?? null);
    }
}

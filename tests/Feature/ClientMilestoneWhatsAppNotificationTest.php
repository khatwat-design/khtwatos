<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\ClientWhatsappMilestoneLog;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientMilestoneWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_whatsapp_when_analysis_delivered_stage_history_created(): void
    {
        Config::set('services.whatsapp.token', 'test-token');
        Config::set('services.whatsapp.phone_number_id', '999888777');
        Config::set('services.whatsapp.milestone_notifications_enabled', true);
        Config::set('services.whatsapp.milestone_min_hours_between', 0);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test.message']],
            ], 200),
        ]);

        $stageLead = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 10,
        ]);
        $stageDelivered = PipelineStage::query()->create([
            'key' => 'analysis_delivered',
            'label' => 'تم تسليم التحليل',
            'sort_order' => 50,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل إشعار',
            'phone' => '966501112233',
            'current_pipeline_stage_id' => $stageDelivered->id,
        ]);

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $stageDelivered->id,
            'user_id' => null,
            'note' => 'test',
        ]);

        Http::assertSentCount(1);

        $this->assertDatabaseHas('client_whatsapp_milestone_logs', [
            'client_id' => $client->id,
            'milestone_key' => 'analysis_completed',
            'pipeline_stage_key' => 'analysis_delivered',
            'status' => 'sent',
        ]);

        $this->assertSame(1, ClientWhatsappMilestoneLog::query()->where('status', 'sent')->count());
    }

    public function test_skips_duplicate_milestone(): void
    {
        Config::set('services.whatsapp.token', 'test-token');
        Config::set('services.whatsapp.phone_number_id', '999888777');
        Config::set('services.whatsapp.milestone_notifications_enabled', true);
        Config::set('services.whatsapp.milestone_min_hours_between', 0);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.one']],
            ], 200),
        ]);

        $stage = PipelineStage::query()->create([
            'key' => 'strategy_delivered',
            'label' => 'تم تسليم الاستراتيجية',
            'sort_order' => 70,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل تكرار',
            'phone' => '966501112233',
            'current_pipeline_stage_id' => $stage->id,
        ]);

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $stage->id,
            'user_id' => null,
            'note' => 'a',
        ]);

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $stage->id,
            'user_id' => null,
            'note' => 'b',
        ]);

        Http::assertSentCount(1);

        $this->assertSame(1, ClientWhatsappMilestoneLog::query()->where('status', 'sent')->count());
        $this->assertGreaterThanOrEqual(1, ClientWhatsappMilestoneLog::query()->where('status', 'skipped')->where('skip_reason', 'duplicate_milestone')->count());
    }
}

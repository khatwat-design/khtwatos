<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\ClientOperationalTimelineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientOperationalTimelineBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_merges_stage_history_into_timeline(): void
    {
        $user = User::factory()->create();
        $stage = PipelineStage::query()->create([
            'key' => 'test_stage',
            'label' => 'مرحلة اختبار',
            'sort_order' => 1,
        ]);
        $client = Client::query()->create([
            'name' => 'Timeline Client',
            'current_pipeline_stage_id' => $stage->id,
        ]);

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $stage->id,
            'user_id' => $user->id,
            'note' => 'انتقال تجريبي',
        ]);

        $builder = app(ClientOperationalTimelineBuilder::class);
        $rows = $builder->buildForClient($client->fresh());

        $this->assertNotEmpty($rows);
        $match = collect($rows)->firstWhere('verb', 'client.pipeline_stage_changed');
        $this->assertNotNull($match);
        $this->assertSame($client->id, $match['client_id']);
        $this->assertSame('crm', $match['category']);
        $this->assertSame('user', $match['actor_kind']);
    }
}

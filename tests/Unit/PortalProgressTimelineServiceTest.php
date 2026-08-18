<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\PipelineStage;
use App\Services\PortalProgressTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalProgressTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_marks_current_analysis_and_completed_lead(): void
    {
        $lead = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 10,
        ]);
        $analysis = PipelineStage::query()->create([
            'key' => 'analysis',
            'label' => 'تحليل',
            'sort_order' => 40,
        ]);

        $client = Client::query()->create([
            'name' => 'TL Client',
            'current_pipeline_stage_id' => $analysis->id,
        ]);

        $t0 = Carbon::parse('2026-05-01 10:00:00');
        $t1 = Carbon::parse('2026-05-03 14:00:00');

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $lead->id,
            'user_id' => null,
            'note' => 'start',
            'created_at' => $t0,
            'updated_at' => $t0,
        ]);
        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $analysis->id,
            'user_id' => null,
            'note' => 'move',
            'created_at' => $t1,
            'updated_at' => $t1,
        ]);

        $histories = ClientStageHistory::query()
            ->with('stage:id,key')
            ->where('client_id', $client->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $payload = (new PortalProgressTimelineService)->build($client->fresh(['currentStage']), $histories);

        $this->assertSame(1, $payload['current_bucket_index']);
        $steps = $payload['steps'];
        $this->assertSame('completed', $steps[0]['status']);
        $this->assertNotNull($steps[0]['completed_at']);
        $this->assertSame('current', $steps[1]['status']);
        $this->assertSame('upcoming', $steps[2]['status']);
    }
}

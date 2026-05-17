<?php

namespace Tests\Feature;

use App\Models\GoodsMetaLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GoodsMetaLeadSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.goods.meta_leads_webhook_secret', 'test-secret');
    }

    public function test_webhook_upserts_meta_lead_from_sheet_row(): void
    {
        $payload = [
            'secret' => 'test-secret',
            'rows' => [
                [
                    'id' => 'l:4235976496655120',
                    'created_time' => '2026-05-05T05:31:22-05:00',
                    'campaign_name' => 'Abdullah Leads',
                    'platform' => 'ig',
                    'full_name' => 'حيدر المخزومي',
                    'phone_number' => 'p:+9647824462427',
                    'lead_status' => 'CREATED',
                    'الملاحظات ' => 'تم الاتصال',
                    'إحتمالية العميل' => 'عميل محتمل',
                    'السبب' => 'تاجر زيوت',
                    'النتيجة' => 'قيد المتابعة',
                ],
            ],
        ];

        $response = $this->postJson(route('goods.meta-leads.sync'), $payload, [
            'X-Goods-Meta-Leads-Secret' => 'test-secret',
        ]);

        $response->assertOk()->assertJsonPath('stats.created', 1);

        $this->assertDatabaseHas('goods_meta_leads', [
            'meta_lead_id' => 'l:4235976496655120',
            'full_name' => 'حيدر المخزومي',
            'workflow_status' => 'following',
        ]);

        $lead = GoodsMetaLead::query()->where('meta_lead_id', 'l:4235976496655120')->first();
        $this->assertNotNull($lead);
        $this->assertSame('9647824462427', $lead->phone_normalized);
        $this->assertTrue($lead->has_whatsapp);
    }

    public function test_sheet_sync_does_not_overwrite_status_set_in_app(): void
    {
        $lead = GoodsMetaLead::query()->create([
            'meta_lead_id' => 'l:preserve-status',
            'full_name' => 'Laith Hamoode',
            'workflow_status' => GoodsMetaLead::WORKFLOW_FOLLOWING,
            'workflow_status_managed_at' => now(),
        ]);

        $this->postJson(route('goods.meta-leads.sync'), [
            'rows' => [
                [
                    'id' => 'l:preserve-status',
                    'full_name' => 'Laith Hamoode',
                    'إحتمالية العميل' => '',
                    'النتيجة' => '',
                ],
            ],
        ], [
            'X-Goods-Meta-Leads-Secret' => 'test-secret',
        ])->assertOk();

        $lead->refresh();
        $this->assertSame(GoodsMetaLead::WORKFLOW_FOLLOWING, $lead->workflow_status);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->postJson(route('goods.meta-leads.sync'), [
            'rows' => [['id' => 'l:1', 'full_name' => 'x']],
        ], [
            'X-Goods-Meta-Leads-Secret' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_whatsapp_contact_sets_following_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $lead = GoodsMetaLead::query()->create([
            'meta_lead_id' => 'l:wa-test',
            'full_name' => 'عميل واتساب',
            'phone' => '07824462427',
            'has_whatsapp' => true,
            'workflow_status' => GoodsMetaLead::WORKFLOW_NEW,
        ]);

        $this->actingAs($user)
            ->post(route('goods.meta-leads.whatsapp', $lead))
            ->assertRedirect(route('goods.index', ['tab' => 'meta_leads']));

        $lead->refresh();
        $this->assertSame(GoodsMetaLead::WORKFLOW_FOLLOWING, $lead->workflow_status);
        $this->assertNotNull($lead->workflow_status_managed_at);
    }
}

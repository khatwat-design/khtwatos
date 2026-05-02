<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMetaIntegration;
use App\Models\GoodsCustomer;
use App\Models\OutsideContact;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutsideWebhookInboundTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function samplePayload(string $messageId, bool $withContacts = true, string $body = 'مرحبا'): array
    {
        $contacts = [];
        if ($withContacts) {
            $contacts[] = [
                'profile' => ['name' => 'عميل تجريبي'],
                'wa_id' => '966501234567',
            ];
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'phone_number_id' => (string) config('services.whatsapp.phone_number_id') ?: '123456',
                                ],
                                'contacts' => $contacts,
                                'messages' => [
                                    [
                                        'from' => '966501234567',
                                        'id' => $messageId,
                                        'timestamp' => '1234567890',
                                        'type' => 'text',
                                        'text' => ['body' => $body],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_whatsapp_inbound_sets_contact_name_and_creates_goods_customer(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->postJson(route('outside.webhook.receive'), $this->samplePayload('wamid.HBG.TEST_1'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('outside_contacts', [
            'phone' => '966501234567',
            'name' => 'عميل تجريبي',
        ]);

        $this->assertSame(1, GoodsCustomer::query()->count());
        $this->assertDatabaseHas('goods_customers', [
            'name' => 'عميل تجريبي',
            'status' => 'new',
            'phone' => '966501234567',
        ]);
        $this->assertDatabaseHas('outside_conversations', [
            'status' => 'new',
        ]);
    }

    public function test_second_inbound_message_does_not_duplicate_goods_customer(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->postJson(route('outside.webhook.receive'), $this->samplePayload('wamid.HBG.TEST_A'))
            ->assertOk();
        $this->postJson(route('outside.webhook.receive'), $this->samplePayload('wamid.HBG.TEST_B'))
            ->assertOk();

        $this->assertSame(1, GoodsCustomer::query()->count());
        $this->assertDatabaseCount('outside_messages', 2);
    }

    public function test_duplicate_webhook_delivery_is_idempotent_for_goods(): void
    {
        User::factory()->create(['role' => 'admin']);

        $payload = $this->samplePayload('wamid.HBG.DUP');
        $this->postJson(route('outside.webhook.receive'), $payload)->assertOk();
        $this->postJson(route('outside.webhook.receive'), $payload)->assertOk();

        $this->assertSame(1, GoodsCustomer::query()->count());
        $this->assertDatabaseCount('outside_messages', 1);
    }

    public function test_inbound_without_whatsapp_profile_uses_first_message_line_for_goods_name(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->postJson(route('outside.webhook.receive'), $this->samplePayload('wamid.NO_PROFILE', false, 'اسم من الرسالة'))
            ->assertOk();

        $this->assertDatabaseHas('goods_customers', [
            'name' => 'اسم من الرسالة',
        ]);
    }

    public function test_inbound_skips_goods_customer_for_employee_outside_contact(): void
    {
        $employee = User::factory()->create(['role' => 'member']);

        OutsideContact::query()->create([
            'phone' => '966509998877',
            'channel' => 'whatsapp',
            'name' => 'موظف',
            'meta' => [
                'employee_user_id' => $employee->id,
                'source' => 'employee_provision',
            ],
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'phone_number_id' => (string) config('services.whatsapp.phone_number_id') ?: '123456',
                                ],
                                'contacts' => [],
                                'messages' => [
                                    [
                                        'from' => '966509998877',
                                        'id' => 'wamid.EMPLOYEE_FIRST',
                                        'timestamp' => '1234567890',
                                        'type' => 'text',
                                        'text' => ['body' => 'مرحبا'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('outside.webhook.receive'), $payload)->assertOk();

        $this->assertSame(0, GoodsCustomer::query()->count());
    }

    public function test_instagram_inbound_creates_contact_message_and_goods_customer(): void
    {
        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '178400000000000',
                    'time' => 1_234_567_890,
                    'messaging' => [
                        [
                            'sender' => ['id' => '9876543210'],
                            'recipient' => ['id' => '178400000000000'],
                            'timestamp' => 1_234_567_890,
                            'message' => [
                                'mid' => 'mid.ig.test_1',
                                'text' => 'مرحبا من انستغرام',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('outside.webhook.receive'), $payload)->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('outside_contacts', [
            'instagram_psid' => '9876543210',
            'channel' => 'instagram',
            'phone' => 'ig:9876543210',
        ]);
        $this->assertDatabaseHas('outside_messages', [
            'external_message_id' => 'ig:mid.ig.test_1',
            'channel' => 'instagram',
        ]);
        $this->assertSame(1, GoodsCustomer::query()->count());
        $this->assertDatabaseHas('goods_customers', [
            'status' => 'new',
            'phone' => 'ig:9876543210',
        ]);
    }

    public function test_instagram_inbound_links_client_when_meta_integration_matches(): void
    {
        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل جديد',
            'sort_order' => 1,
        ]);
        $client = Client::query()->create([
            'name' => 'عميل اختبار إنستغرام',
            'current_pipeline_stage_id' => $stage->id,
        ]);
        ClientMetaIntegration::query()->create([
            'client_id' => $client->id,
            'ad_account_id' => 'act_test_ig_'.uniqid(),
            'meta_instagram_account_id' => '17841400000000001',
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841400000000001',
                    'time' => 1_234_567_890,
                    'messaging' => [
                        [
                            'sender' => ['id' => '111222333444'],
                            'recipient' => ['id' => '17841400000000001'],
                            'timestamp' => 1_234_567_890,
                            'message' => [
                                'mid' => 'mid.ig.linked',
                                'text' => 'رسالة',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson(route('outside.webhook.receive'), $payload)->assertOk();

        $this->assertDatabaseHas('outside_contacts', [
            'instagram_psid' => '111222333444',
            'client_id' => $client->id,
        ]);
    }
}

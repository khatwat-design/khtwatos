<?php

namespace Tests\Feature;

use App\Models\GoodsCustomer;
use App\Models\OutsideContact;
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
            'status' => 'lead',
            'phone' => '966501234567',
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
}

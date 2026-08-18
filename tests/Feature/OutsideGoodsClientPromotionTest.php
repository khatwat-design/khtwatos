<?php

namespace Tests\Feature;

use App\Models\GoodsCustomer;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutsideGoodsClientPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_potential_creates_client_and_updates_outside(): void
    {
        PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 10,
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $contact = OutsideContact::query()->create([
            'phone' => '966501112233',
            'channel' => 'whatsapp',
            'name' => 'عميل ترقية',
        ]);

        OutsideConversation::query()->create([
            'outside_contact_id' => $contact->id,
            'status' => 'new',
        ]);

        $goods = GoodsCustomer::query()->create([
            'outside_contact_id' => $contact->id,
            'name' => 'عميل ترقية',
            'phone' => $contact->phone,
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->patch(route('goods.customers.status', $goods), ['status' => 'potential'])
            ->assertRedirect(route('goods.index'));

        $goods->refresh();
        $this->assertNotNull($goods->client_id);
        $this->assertSame('active', $goods->status);

        $this->assertDatabaseHas('clients', [
            'id' => $goods->client_id,
            'name' => 'عميل ترقية',
        ]);

        $this->assertDatabaseHas('outside_conversations', [
            'outside_contact_id' => $contact->id,
            'status' => 'qualified',
        ]);
    }

    public function test_outside_potential_promotes_linked_goods_customer(): void
    {
        PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 10,
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $contact = OutsideContact::query()->create([
            'phone' => '966509998877',
            'channel' => 'whatsapp',
            'name' => 'من الخارج',
        ]);

        $conversation = OutsideConversation::query()->create([
            'outside_contact_id' => $contact->id,
            'status' => 'new',
        ]);

        GoodsCustomer::query()->create([
            'outside_contact_id' => $contact->id,
            'name' => 'من الخارج',
            'phone' => $contact->phone,
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->patch(route('outside.conversations.update', $conversation), ['status' => 'potential'])
            ->assertRedirect(route('outside.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'من الخارج',
        ]);

        $conversation->refresh();
        $this->assertSame('qualified', $conversation->status);
    }
}

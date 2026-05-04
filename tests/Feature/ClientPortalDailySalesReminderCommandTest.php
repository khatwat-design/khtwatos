<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientDailySale;
use App\Models\ClientDailySalesReminderLog;
use App\Models\OutsideContact;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientPortalDailySalesReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_when_no_sales_today_and_logs(): void
    {
        Config::set('services.whatsapp.token', 't');
        Config::set('services.whatsapp.phone_number_id', '123');
        Config::set('services.portal.daily_sales_reminder_enabled', true);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.rem']]], 200),
        ]);

        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل تذكير',
            'phone' => '966501112233',
            'portal_username' => 'client_a',
            'portal_password' => bcrypt('secret'),
            'current_pipeline_stage_id' => $stage->id,
        ]);

        OutsideContact::query()->create([
            'name' => $client->name,
            'phone' => '966501112233',
            'channel' => 'whatsapp',
            'client_id' => $client->id,
        ]);

        Artisan::call('portal:send-daily-sales-reminders', ['--limit' => 50]);

        Http::assertSentCount(1);

        $this->assertDatabaseHas('client_daily_sales_reminder_logs', [
            'client_id' => $client->id,
            'status' => 'sent',
        ]);
    }

    public function test_skips_when_sales_already_recorded_today(): void
    {
        Config::set('services.whatsapp.token', 't');
        Config::set('services.whatsapp.phone_number_id', '123');
        Config::set('services.portal.daily_sales_reminder_enabled', true);

        Http::fake();

        $stage = PipelineStage::query()->create([
            'key' => 'lead',
            'label' => 'عميل محتمل',
            'sort_order' => 1,
        ]);

        $client = Client::query()->create([
            'name' => 'عميل بمبيعات',
            'phone' => '966501112233',
            'portal_username' => 'client_b',
            'portal_password' => bcrypt('secret'),
            'current_pipeline_stage_id' => $stage->id,
        ]);

        ClientDailySale::query()->create([
            'client_id' => $client->id,
            'sales_date' => now()->toDateString(),
            'orders_count' => 2,
            'revenue' => 50.00,
            'source' => 'portal',
        ]);

        Artisan::call('portal:send-daily-sales-reminders', ['--limit' => 50]);

        Http::assertSentCount(0);
        $this->assertSame(0, ClientDailySalesReminderLog::query()->count());
    }
}

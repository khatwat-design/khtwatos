<?php

namespace App\Console\Commands;

use App\Models\GoodsCustomer;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDailyGoodsSalesRemindersCommand extends Command
{
    protected $signature = 'goods:send-daily-sales-reminders {--limit=200}';

    protected $description = 'إرسال تذكير يومي عبر واتساب لعملاء البضاعة لإدخال المبيعات (مرة واحدة لكل عميل في اليوم)';

    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $today = now()->toDateString();

        $customers = GoodsCustomer::query()
            ->whereIn('status', ['new', 'potential', 'active'])
            ->whereNotNull('outside_contact_id')
            ->whereHas('contact', fn ($q) => $q->where('channel', 'whatsapp'))
            ->with('contact:id,phone,channel')
            ->whereNotExists(function ($q) use ($today): void {
                $q->select(DB::raw('1'))
                    ->from('outside_reminder_logs as orl')
                    ->whereColumn('orl.goods_customer_id', 'goods_customers.id')
                    ->where('orl.reminder_type', 'daily_sales')
                    ->whereDate('orl.sent_at', $today);
            })
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $conversation = OutsideConversation::query()->firstOrCreate([
                'outside_contact_id' => $customer->outside_contact_id,
            ]);

            $body = sprintf(
                'مرحبًا %s، تذكير ودي بإضافة مبيعات اليوم على المنصة حتى نكمل المتابعة.',
                $customer->name
            );

            $message = OutsideMessage::query()->create([
                'outside_conversation_id' => $conversation->id,
                'channel' => 'whatsapp',
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $body,
                'provider_status' => 'queued',
                'sent_at' => null,
            ]);

            try {
                $response = $this->whatsAppCloudService->sendText(
                    (string) $customer->contact?->phone,
                    $body
                );

                $message->update([
                    'external_message_id' => (string) data_get($response, 'messages.0.id', ''),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'sent_at' => now(),
                ]);
                $sent++;

                $conversation->update([
                    'latest_message_preview' => mb_substr($body, 0, 120),
                    'last_outbound_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('outside_reminder_logs')->insert([
                    'goods_customer_id' => $customer->id,
                    'outside_conversation_id' => $conversation->id,
                    'sent_by_user_id' => null,
                    'reminder_type' => 'daily_sales',
                    'body' => $body,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                $message->update([
                    'provider_status' => 'failed',
                    'provider_error' => $exception->getMessage(),
                    'retry_count' => 1,
                ]);
                $failed++;
            }
        }

        $this->info("Daily sales reminders: queued {$customers->count()}, sent {$sent}, failed {$failed}.");

        return self::SUCCESS;
    }
}

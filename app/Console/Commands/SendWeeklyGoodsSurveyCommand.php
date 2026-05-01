<?php

namespace App\Console\Commands;

use App\Models\GoodsCustomer;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendWeeklyGoodsSurveyCommand extends Command
{
    protected $signature = 'goods:send-weekly-survey {--limit=100}';

    protected $description = 'Send weekly WhatsApp survey to goods customers';

    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $customers = GoodsCustomer::query()
            ->whereIn('status', ['prospect', 'active'])
            ->whereNotNull('outside_contact_id')
            ->with('contact:id,phone')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $conversation = OutsideConversation::query()->firstOrCreate([
                'outside_contact_id' => $customer->outside_contact_id,
            ]);

            $body = sprintf(
                'مرحبًا %s، نرجو تقييم أداء الأسبوع الحالي والرد على الاستبيان المختصر: 1) رضاك العام؟ 2) أبرز ملاحظة؟',
                $customer->name
            );

            $message = OutsideMessage::query()->create([
                'outside_conversation_id' => $conversation->id,
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
            } catch (\Throwable $exception) {
                $message->update([
                    'provider_status' => 'failed',
                    'provider_error' => $exception->getMessage(),
                    'retry_count' => 1,
                ]);
                $failed++;
            }

            $conversation->update([
                'latest_message_preview' => mb_substr($body, 0, 120),
                'last_outbound_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('outside_reminder_logs')->insert([
                'goods_customer_id' => $customer->id,
                'outside_conversation_id' => $conversation->id,
                'sent_by_user_id' => null,
                'reminder_type' => 'weekly_survey',
                'body' => $body,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("Weekly survey finished. Sent: {$sent}, Failed: {$failed}");

        return self::SUCCESS;
    }
}


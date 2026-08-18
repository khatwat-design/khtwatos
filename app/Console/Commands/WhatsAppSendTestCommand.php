<?php

namespace App\Console\Commands;

use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Throwable;

class WhatsAppSendTestCommand extends Command
{
    protected $signature = 'whatsapp:send-test {phone : الرقم بصيغة دولية، مثال 9647832038642} {--body=اختبار من khtwatos}';

    protected $description = 'إرسال رسالة نصية تجريبية عبر WhatsApp Cloud API (يجب أن يكون الرقم ضمن نافذة الخدمة أو استخدام قالب معتمد)';

    public function handle(WhatsAppCloudService $whatsAppCloudService): int
    {
        $phone = preg_replace('/\D+/', '', (string) $this->argument('phone')) ?: (string) $this->argument('phone');
        $body = (string) $this->option('body');

        try {
            $whatsAppCloudService->sendText($phone, $body);
            $this->info('تم طلب الإرسال إلى '.$phone.' بنجاح (تحقق من الواتساب والـ Meta Dashboard إن لزم).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

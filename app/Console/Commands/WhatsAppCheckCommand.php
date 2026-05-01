<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppCheckCommand extends Command
{
    protected $signature = 'whatsapp:check {--webhook : تحقق من مسار subscribe على APP_URL (محليًا)}';

    protected $description = 'التحقق من تهيئة WhatsApp Cloud API وصلاحية الرمز ورقم الهاتف';

    public function handle(): int
    {
        $token = (string) config('services.whatsapp.token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $verifyToken = (string) config('services.whatsapp.webhook_verify_token');
        $wabaId = (string) config('services.whatsapp.business_account_id');
        $version = (string) config('services.whatsapp.version', 'v22.0');

        $this->line('إعداد البيئة:');
        $this->line('  WHATSAPP_TOKEN: '.($token !== '' ? 'موجود ('.strlen($token).' حرف)' : 'ناقص'));
        $this->line('  WHATSAPP_PHONE_NUMBER_ID: '.($phoneNumberId !== '' ? $phoneNumberId : 'ناقص'));
        $this->line('  WHATSAPP_WEBHOOK_VERIFY_TOKEN: '.($verifyToken !== '' ? 'موجود' : 'ناقص'));
        $this->line('  WHATSAPP_BUSINESS_ACCOUNT_ID: '.($wabaId !== '' ? $wabaId : '(اختياري) فارغ'));

        if ($token === '' || $phoneNumberId === '') {
            $this->error('غير مكتمل: أضف WHATSAPP_TOKEN و WHATSAPP_PHONE_NUMBER_ID في .env ثم php artisan config:clear');

            return self::FAILURE;
        }

        $phoneUrl = "https://graph.facebook.com/{$version}/{$phoneNumberId}";
        $phoneResp = Http::withToken($token)
            ->acceptJson()
            ->get($phoneUrl, [
                'fields' => 'verified_name,display_phone_number,code_verification_status,quality_rating',
            ]);

        if (! $phoneResp->successful()) {
            $msg = $phoneResp->json('error.message') ?? $phoneResp->body();
            $this->error('فشل التحقق من Graph API (phone_number_id): '.$msg);

            return self::FAILURE;
        }

        $this->info('Graph API: الرابط مع رقم الواتساب سليم.');
        $this->line('  الاسم المعروض: '.($phoneResp->json('verified_name') ?? '—'));
        $this->line('  الرقم: '.($phoneResp->json('display_phone_number') ?? '—'));
        $this->line('  التحقق من الرمز: '.($phoneResp->json('code_verification_status') ?? '—'));
        $qr = $phoneResp->json('quality_rating');
        $this->line('  تصنيف الجودة: '.(is_string($qr) ? $qr : '—'));

        if ($wabaId !== '') {
            $wabaResp = Http::withToken($token)->acceptJson()->get(
                "https://graph.facebook.com/{$version}/{$wabaId}",
                ['fields' => 'name,account_review_status']
            );
            if ($wabaResp->successful()) {
                $this->line('  حساب الأعمال (WABA): '.($wabaResp->json('name') ?? '—'));
            } else {
                $this->warn('تعذّر قراءة WABA: '.($wabaResp->json('error.message') ?? $wabaResp->body()));
            }
        }

        if ($this->option('webhook')) {
            return $this->checkWebhookVerify($verifyToken);
        }

        $this->comment('للتحقق من استجابة مسار الويب هوك على هذا الخادم: php artisan whatsapp:check --webhook');

        return self::SUCCESS;
    }

    private function checkWebhookVerify(string $verifyToken): int
    {
        if ($verifyToken === '') {
            $this->warn('WHATSAPP_WEBHOOK_VERIFY_TOKEN فارغ؛ لا يمكن فحص الويب هوك.');

            return self::FAILURE;
        }

        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            $this->warn('APP_URL غير مضبوط في .env؛ لا يمكن بناء رابط الويب هوك.');

            return self::FAILURE;
        }

        $hookUrl = $base.'/outside/webhook';
        $challenge = 'whatsapp-check-'.bin2hex(random_bytes(4));
        $whResp = Http::timeout(15)->get($hookUrl, [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => $verifyToken,
            'hub.challenge' => $challenge,
        ]);

        if ($whResp->successful() && trim($whResp->body()) === $challenge) {
            $this->info('الويب هوك (GET subscribe): يستجيب كما يتوقعه Meta.');

            return self::SUCCESS;
        }

        $this->error('الويب هوك: فشل. HTTP '.$whResp->status().' — '.Str::limit($whResp->body(), 500));

        return self::FAILURE;
    }
}

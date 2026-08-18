<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WhatsAppSetTokenCommand extends Command
{
    protected $signature = 'whatsapp:set-token
                            {--token= : الرمز الجديد (اختياري؛ الأفضل تشغيل الأمر بدونها ليُطلب مخفياً)}
                            {--force : بدون سؤال تأكيد}';

    protected $description = 'استبدال WHATSAPP_TOKEN في .env وتفريغ كاش الإعدادات (سريع أثناء المعارض)';

    public function handle(): int
    {
        $token = $this->option('token');
        if (! is_string($token) || trim($token) === '') {
            $this->comment('الصق التوكن الجديد — لن يظهر أثناء الكتابة:');
            $token = (string) $this->secret('WHATSAPP_TOKEN');
        } else {
            $token = trim($token);
        }

        if ($token === '') {
            $this->error('التوكن فارغ.');

            return self::FAILURE;
        }

        $path = base_path('.env');
        if (! File::exists($path)) {
            $this->error('ملف .env غير موجود في: '.$path);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('استبدال WHATSAPP_TOKEN في .env؟', true)) {
            return self::SUCCESS;
        }

        $line = 'WHATSAPP_TOKEN='.$this->escapeEnvValue($token);
        $content = File::get($path);

        if (preg_match('/^WHATSAPP_TOKEN=/m', $content)) {
            $content = preg_replace('/^WHATSAPP_TOKEN=.*$/m', $line, $content);
        } else {
            $content = rtrim($content)."\n".$line."\n";
        }

        File::put($path, $content);

        $this->info('تم تحديث WHATSAPP_TOKEN في .env');

        $this->callSilently('config:clear');
        $this->info('تم: php artisan config:clear — جاهز للإرسال.');

        return self::SUCCESS;
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (! preg_match('/[\s#"\'\\\\$`]/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmartNotificationService;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProvisionEmployeeCommand extends Command
{
    protected $signature = 'employee:provision
                            {name : الاسم الظاهر في النظام}
                            {phone : رقم الهاتف / الواتساب}
                            {--title= : المنصب الوظيفي (يُذكر في رسالة الواتساب)}
                            {--role= : أحد admin أو lead أو member؛ الافتراضي member أو lead إذا احتوى المنصب على «مدير»}
                            {--password= : كلمة مرور ثابتة؛ إن لم تُحدَّد تُولَّد عشوائيًا}
                            {--no-wa : عدم إرسال رسالة الواتساب}';

    protected $description = 'إنشاء مستخدم موظف بجدول التوفر الافتراضي وإرسال بيانات الدخول عبر WhatsApp';

    public function handle(
        WhatsAppCloudService $whatsAppCloudService,
        SmartNotificationService $smartNotifications
    ): int {
        $rawPhone = $this->toAsciiDigits((string) $this->argument('phone'));
        $phoneDigits = preg_replace('/\D+/', '', $rawPhone) ?: '';

        if ($phoneDigits === '') {
            $this->error('رقم الهاتف غير صالح.');

            return self::FAILURE;
        }

        $displayName = trim((string) $this->argument('name'));
        if ($displayName === '') {
            $this->error('الاسم مطلوب.');

            return self::FAILURE;
        }

        $title = trim((string) $this->option('title'));

        $role = $this->option('role');
        if (! is_string($role) || $role === '') {
            $role = str_contains($title, 'مدير') ? 'lead' : 'member';
        }
        if (! in_array($role, ['admin', 'lead', 'member'], true)) {
            $this->error('الدور يجب أن يكون admin أو lead أو member.');

            return self::FAILURE;
        }

        $emailHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'local';
        $emailHost = preg_replace('/^www\./', '', (string) $emailHost) ?: 'local';
        $email = $phoneDigits.'@staff.'.$emailHost;

        if (User::query()->where('email', $email)->exists()) {
            $this->error('يوجد مستخدم بنفس البريد المشتق من الرقم: '.$email);

            return self::FAILURE;
        }

        $name = $displayName;
        if (User::query()->where('name', $name)->exists()) {
            $suffix = substr($phoneDigits, -4);
            $name = "{$displayName} {$suffix}";
            $this->warn('الاسم مستخدم مسبقًا؛ تم استخدام: '.$name);
        }

        $plainPassword = $this->option('password');
        if (! is_string($plainPassword) || $plainPassword === '') {
            $plainPassword = Str::password(12, symbols: true);
        }
        if (strlen($plainPassword) < 8) {
            $this->error('كلمة المرور يجب أن لا تقل عن 8 أحرف.');

            return self::FAILURE;
        }

        $availability = $this->defaultAvailabilityPayload();

        try {
            /** @var User $user */
            $user = DB::transaction(function () use ($name, $email, $plainPassword, $role, $availability) {
                return User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $plainPassword,
                    'role' => $role,
                    'is_bookable' => true,
                    'availability_days' => $availability['availability_days'],
                    'availability_start_time' => $availability['availability_start_time'],
                    'availability_end_time' => $availability['availability_end_time'],
                    'availability_schedule' => $availability['availability_schedule'],
                    'email_verified_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $this->error('فشل إنشاء المستخدم: '.$e->getMessage());

            return self::FAILURE;
        }

        $adminIds = User::query()->where('role', 'admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $smartNotifications->notifyUsers($adminIds, [
            'title' => 'إضافة موظف جديد',
            'body' => $user->name,
            'severity' => 'info',
            'category' => 'employees',
            'link' => route('employees.index'),
            'meta' => ['employee_id' => $user->id],
        ], null);

        $loginUrl = rtrim((string) config('app.url'), '/');

        $lines = [
            "مرحبًا {$user->name}،",
            '',
            'تم إنشاء حسابك في نظام خارج المخزون.',
        ];
        if ($title !== '') {
            $lines[] = "المنصب: {$title}";
            $lines[] = '';
        }
        $lines[] = 'رابط الدخول:';
        $lines[] = $loginUrl;
        $lines[] = '';
        $lines[] = 'يمكنك تسجيل الدخول بأحد الخيارين في حقل اسم المستخدم:';
        $lines[] = '• البريد: '.$user->email;
        $lines[] = '• أو الاسم الظاهر: '.$user->name;
        $lines[] = '';
        $lines[] = 'كلمة المرور:';
        $lines[] = $plainPassword;
        $lines[] = '';
        $lines[] = 'يُنصح بتغيير كلمة المرور بعد أول دخول من الإعدادات.';
        $lines[] = 'الدور في النظام: '.match ($role) {
            'admin' => 'مدير نظام',
            'lead' => 'قائد فريق',
            default => 'موظف',
        };

        $message = implode("\n", $lines);

        $this->info('تم إنشاء المستخدم #'.$user->id.' — '.$user->email);

        if ($this->option('no-wa')) {
            $this->comment('تم تخطي الواتساب. انسخ الرسالة يدويًا:');
            $this->line($message);

            return self::SUCCESS;
        }

        try {
            $whatsAppCloudService->sendText($phoneDigits, $message);
            $this->info('تم إرسال بيانات الدخول إلى واتساب '.$phoneDigits.'.');
        } catch (Throwable $e) {
            $this->warn('تعذّر إرسال الواتساب: '.$e->getMessage());
            $this->line($message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{availability_schedule: array<string, array{enabled: bool, start: ?string, end: ?string}>, availability_days: array<int, int>, availability_start_time: string, availability_end_time: string}
     */
    private function defaultAvailabilityPayload(): array
    {
        $schedule = [];
        $enabledDays = [];
        $firstStart = null;
        $firstEnd = null;

        for ($day = 0; $day <= 6; $day++) {
            $enabled = $day <= 4;
            $start = $enabled ? '09:00' : null;
            $end = $enabled ? '17:00' : null;

            if ($enabled) {
                $enabledDays[] = $day;
                $firstStart ??= $start;
                $firstEnd ??= $end;
            }

            $schedule[(string) $day] = [
                'enabled' => $enabled,
                'start' => $start,
                'end' => $end,
            ];
        }

        return [
            'availability_schedule' => $schedule,
            'availability_days' => array_values(array_unique($enabledDays)),
            'availability_start_time' => $firstStart ?? '09:00',
            'availability_end_time' => $firstEnd ?? '17:00',
        ];
    }

    private function toAsciiDigits(string $input): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        return strtr($input, $map);
    }
}

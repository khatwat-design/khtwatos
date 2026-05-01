<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmartNotificationService;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionEmployeeRosterCommand extends Command
{
    private const FIXED_PASSWORD = '12345678';

    protected $signature = 'employee:provision-roster {--dry-run : عرض ما سيُنفَّذ دون كتابة أو واتساب}';

    protected $description = 'إنشاء/تحديث حسابات الموظفين من القائمة المعتمدة وإرسال بيانات الدخول عبر واتساب';

    public function handle(
        WhatsAppCloudService $whatsAppCloudService,
        SmartNotificationService $smartNotifications
    ): int {
        $dry = (bool) $this->option('dry-run');
        $emailHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'local';
        $emailHost = preg_replace('/^www\./', '', (string) $emailHost) ?: 'local';
        $loginUrl = rtrim((string) config('app.url'), '/');

        $roster = $this->rosterRows();
        $created = 0;
        $updated = 0;
        $waOk = 0;
        $waFail = 0;

        foreach ($roster as $row) {
            $phoneDigits = $this->normalizePhoneDigits((string) $row['phone']);
            if ($phoneDigits === '') {
                $this->error('رقم غير صالح للصف: '.($row['name'] ?? '?'));

                return self::FAILURE;
            }

            $arabicName = trim((string) $row['name']);
            $title = trim((string) ($row['title'] ?? ''));
            $role = $row['role'];
            $skipCreate = (bool) ($row['skip_create'] ?? false);

            if (! in_array($role, ['admin', 'lead', 'member'], true)) {
                $this->error('دور غير صالح لـ '.$arabicName);

                return self::FAILURE;
            }

            $findBy = array_values(array_filter(
                array_map('trim', $row['find_by'] ?? []),
                fn ($h) => is_string($h) && trim($h) !== ''
            ));

            try {
                $result = $this->provisionOne(
                    arabicName: $arabicName,
                    phoneDigits: $phoneDigits,
                    title: $title,
                    role: $role,
                    skipCreate: $skipCreate,
                    findBy: $findBy,
                    emailHost: $emailHost,
                    loginUrl: $loginUrl,
                    dryRun: $dry,
                    whatsAppCloudService: $whatsAppCloudService,
                );
            } catch (Throwable $e) {
                $this->error($arabicName.': '.$e->getMessage());

                return self::FAILURE;
            }

            if ($result['created']) {
                $created++;
            }
            if ($result['updated']) {
                $updated++;
            }
            if ($result['wa_sent']) {
                $waOk++;
            } elseif (! $dry && ! ($result['wa_skipped'] ?? false)) {
                $waFail++;
            }

            $this->line(sprintf(
                '%s — بريد الدخول: %s — %s',
                $arabicName,
                $result['email'],
                $dry ? '[dry-run]' : ($result['wa_sent'] ? 'واتساب ✓' : 'واتساب ✗')
            ));
        }

        if (! $dry) {
            $adminIds = User::query()->where('role', 'admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $smartNotifications->notifyUsers($adminIds, [
                'title' => 'تزامنة قائمة الموظفين',
                'body' => "إنشاء: {$created} | تحديث/كلمة مرور: {$updated} | واتساب ناجح: {$waOk}",
                'severity' => 'info',
                'category' => 'employees',
                'link' => route('employees.index'),
                'meta' => [],
            ], null);
        }

        $this->info("تم. إنشاء: {$created}, تحديثات: {$updated}, واتساب: {$waOk} ناجح".($waFail ? ", {$waFail} فشل" : ''));

        return $waFail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, phone: string, title: string, role: string, skip_create?: bool, find_by?: array<int, string>}>
     */
    private function rosterRows(): array
    {
        return [
            ['name' => 'عبدالعزيز جاسم', 'phone' => '9647805872642', 'title' => 'مدير نظام', 'role' => 'admin'],
            // «أني» = صاحب الحساب؛ الاسم في قاعدة البيانات 1xw (ليس «أني»)
            ['name' => 'أني', 'phone' => '9647831038642', 'title' => '', 'role' => 'member', 'skip_create' => true, 'find_by' => ['1xw']],
            ['name' => 'أحمد بشير', 'phone' => '9647868362896', 'title' => 'مدير موارد بشرية', 'role' => 'lead'],
            ['name' => 'محمد ثائر', 'phone' => '9647824862386', 'title' => 'مدير مالي', 'role' => 'lead'],
            ['name' => 'نبراس', 'phone' => '9647821319817', 'title' => 'مديرة الفريق — المبيعات', 'role' => 'lead'],
            ['name' => 'حسين علي', 'phone' => '9647827478215', 'title' => 'موظف مبيعات', 'role' => 'member'],
            ['name' => 'شذى', 'phone' => '963938466137', 'title' => 'مديرة مدراء الحسابات', 'role' => 'lead'],
            ['name' => 'مها', 'phone' => '962781252331', 'title' => 'مديرة حساب', 'role' => 'lead'],
            ['name' => 'حسين سلام', 'phone' => '9647830868941', 'title' => 'مدير الفريق — الكتابة', 'role' => 'lead'],
            ['name' => 'ليث', 'phone' => '9647719516585', 'title' => 'كاتب + ميديا باير', 'role' => 'member'],
            ['name' => 'محمود', 'phone' => '201278957988', 'title' => 'كاتب', 'role' => 'member'],
            ['name' => 'محمد خالد', 'phone' => '201098865472', 'title' => 'مدير الفريق — الميديا باير', 'role' => 'lead'],
            ['name' => 'عبدالله عبدالغفور', 'phone' => '201062216962', 'title' => 'ميديا باير', 'role' => 'member'],
        ];
    }

    /**
     * @param  array<int, string>  $findBy
     */
    private function findExistingUserForSkipRow(string $phoneDigits, string $arabicName, array $findBy): ?User
    {
        return User::query()
            ->where(function ($q) use ($phoneDigits, $arabicName, $findBy): void {
                $q->where('email', 'like', $phoneDigits.'@%')
                    ->orWhere('name', $arabicName);
                foreach ($findBy as $hint) {
                    $hint = trim((string) $hint);
                    if ($hint === '') {
                        continue;
                    }
                    $q->orWhere('name', $hint);
                    if (str_contains($hint, '@')) {
                        $q->orWhere('email', $hint);
                    } else {
                        $q->orWhere('email', 'like', $hint.'@%');
                    }
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{email: string, created: bool, updated: bool, wa_sent: bool, wa_skipped?: bool}
     */
    private function provisionOne(
        string $arabicName,
        string $phoneDigits,
        string $title,
        string $role,
        bool $skipCreate,
        array $findBy,
        string $emailHost,
        string $loginUrl,
        bool $dryRun,
        WhatsAppCloudService $whatsAppCloudService,
    ): array {
        $availability = $this->defaultAvailabilityPayload();
        $plainPassword = self::FIXED_PASSWORD;

        $user = null;
        $created = false;
        $updated = false;

        $resolvedEmail = '';

        if ($skipCreate) {
            $user = $this->findExistingUserForSkipRow($phoneDigits, $arabicName, $findBy);

            if ($dryRun) {
                $resolvedEmail = $user?->email ?: '(لم يُعثر على حساب — أضف find_by مثل 1xw أو رقم البريد المشتق من الجوال)';

                return ['email' => $resolvedEmail, 'created' => false, 'updated' => false, 'wa_sent' => false, 'wa_skipped' => true];
            }

            if (! $user) {
                $hints = implode(', ', $findBy) ?: '—';
                throw new \RuntimeException(
                    'لم يُعثر على حساب موجود (بحث بالرقم في البريد، أو الاسم «'.$arabicName.'»، أو find_by: '.$hints.').'
                );
            }

            $resolvedEmail = $user->email;

            DB::transaction(function () use ($user, $plainPassword, $role): void {
                $user->password = $plainPassword;
                if ($user->role !== 'admin') {
                    $user->role = $role;
                }
                $user->save();
            });
            $updated = true;
        } else {
            $localPart = $this->uniqueEmailLocalPart($arabicName, $phoneDigits, $emailHost);
            $email = $localPart.'@staff.'.$emailHost;

            $existing = User::query()->where('email', $email)->first()
                ?? User::query()->where('email', 'like', $phoneDigits.'@%')->first();

            if ($dryRun) {
                $resolvedEmail = $existing?->email ?? $email;

                return ['email' => $resolvedEmail, 'created' => false, 'updated' => false, 'wa_sent' => false, 'wa_skipped' => true];
            }

            if ($existing) {
                $user = $existing;
                DB::transaction(function () use ($user, $arabicName, $plainPassword, $role, $availability): void {
                    $user->name = $arabicName;
                    $user->password = $plainPassword;
                    $user->role = $role;
                    $user->is_bookable = true;
                    $user->availability_days = $availability['availability_days'];
                    $user->availability_start_time = $availability['availability_start_time'];
                    $user->availability_end_time = $availability['availability_end_time'];
                    $user->availability_schedule = $availability['availability_schedule'];
                    $user->email_verified_at ??= now();
                    $user->save();
                });
                $updated = true;
            } else {
                $user = DB::transaction(function () use ($arabicName, $email, $plainPassword, $role, $availability) {
                    return User::query()->create([
                        'name' => $arabicName,
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
                $created = true;
            }

            $resolvedEmail = $user->email;
        }

        $message = $this->buildWhatsAppBody(
            userName: $user->name,
            email: $resolvedEmail,
            arabicDisplayName: $arabicName,
            title: $title,
            loginUrl: $loginUrl,
            plainPassword: $plainPassword,
            role: $role,
        );

        $waSent = false;
        try {
            $whatsAppCloudService->sendText($phoneDigits, $message);
            $waSent = true;
        } catch (Throwable) {
            $this->warn('فشل واتساب لـ '.$arabicName.' — انسخ الرسالة يدويًا:');
            $this->line($message);
        }

        return [
            'email' => $resolvedEmail,
            'created' => $created,
            'updated' => $updated,
            'wa_sent' => $waSent,
        ];
    }

    private function buildWhatsAppBody(
        string $userName,
        string $email,
        string $arabicDisplayName,
        string $title,
        string $loginUrl,
        string $plainPassword,
        string $role,
    ): string {
        $roleLabel = match ($role) {
            'admin' => 'مدير نظام',
            'lead' => 'قائد فريق',
            default => 'موظف',
        };

        $lines = [
            "مرحبًا {$arabicDisplayName}،",
            '',
            'تم تجهيز بيانات الدخول لنظام خارج المخزون.',
        ];

        if ($title !== '') {
            $lines[] = "المنصب: {$title}";
            $lines[] = '';
        }

        $lines[] = 'رابط الدخول:';
        $lines[] = $loginUrl;
        $lines[] = '';
        $lines[] = 'يمكنك تسجيل الدخول بإحدى الطريقتين في حقل اسم المستخدم:';
        $lines[] = '• البريد (الأسهل): '.$email;
        $lines[] = '• أو الاسم الكامل كما بالنظام: '.$userName;
        $lines[] = '';
        $lines[] = 'كلمة المرور الموحدة للجميع حاليًا:';
        $lines[] = $plainPassword;
        $lines[] = '';
        $lines[] = 'الدور في النظام: '.$roleLabel;
        $lines[] = '';
        $lines[] = 'يُفضّل تغيير كلمة المرور لاحقًا من الإعدادات.';

        return implode("\n", $lines);
    }

    /**
     * حرفان لاتينيان مشتقّان من الاسم + آخر 4 أرقام من الهاتف، مع تجنب تعارض البريد.
     */
    private function uniqueEmailLocalPart(string $arabicName, string $phoneDigits, string $emailHost): string
    {
        $suffix = substr($phoneDigits, -4);
        $two = $this->twoLetterLatinKey($arabicName);
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $two.$suffix) ?: 'u'.$suffix);

        $candidate = $base;
        $n = 0;
        while (User::query()->where('email', $candidate.'@staff.'.$emailHost)->exists()) {
            $n++;
            $candidate = $base.$n;
        }

        return $candidate;
    }

    private function twoLetterLatinKey(string $arabicName): string
    {
        $name = trim($arabicName);
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1);
            $b = mb_substr($parts[1], 0, 1);
        } else {
            $single = $parts[0] ?? $name;
            $a = mb_substr($single, 0, 1);
            $b = mb_substr($single, 1, 1) ?: $a;
        }

        return $this->translitChar($a).$this->translitChar($b);
    }

    private function translitChar(string $ch): string
    {
        static $map = [
            'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'ح' => 'h', 'خ' => 'k', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
            'س' => 's', 'ش' => 's', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
            'غ' => 'g', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'h', 'ؤ' => 'w', 'ئ' => 'y',
            'ء' => 'a',
        ];

        return $map[$ch] ?? 'x';
    }

    private function normalizePhoneDigits(string $input): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];
        $s = strtr($input, $map);

        return preg_replace('/\D+/', '', $s) ?: '';
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
}

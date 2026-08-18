<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Services\SmartNotificationService;
use App\Services\WhatsAppCloudService;
use App\Support\EmployeeOutsideContactSync;
use App\Support\EmployeeUsername;
use App\Support\EmployeeWhatsAppLoginBody;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionEmployeeRosterCommand extends Command
{
    private const FIXED_PASSWORD = '12345678';

    protected $signature = 'employee:provision-roster {--dry-run : عرض ما سيُنفَّذ دون كتابة أو واتساب}';

    protected $description = 'إنشاء/تحديث حسابات الموظفين من القائمة المعتمدة (اسم مستخدم حرفان+رقم، بدون بريد) وربط الفرق';

    public function handle(
        WhatsAppCloudService $whatsAppCloudService,
        SmartNotificationService $smartNotifications
    ): int {
        $dry = (bool) $this->option('dry-run');
        $loginUrl = rtrim((string) config('app.url'), '/');

        $this->ensureTeams();

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
            /** @var array<int, array{slug: string, is_lead: bool, allocation?: int|null}> $teamsConfig */
            $teamsConfig = $row['teams'] ?? [];

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
                    teamsConfig: $teamsConfig,
                    skipCreate: $skipCreate,
                    findBy: $findBy,
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
                '%s — اسم المستخدم: %s — %s',
                $arabicName,
                $result['username'],
                $dry ? '[dry-run]' : ($result['wa_sent'] ? 'واتساب ✓' : 'واتساب ✗')
            ));
        }

        if (! $dry) {
            $adminIds = User::query()->where('role', 'admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $smartNotifications->notifyUsers($adminIds, [
                'title' => 'تزامنة قائمة الموظفين',
                'body' => "إنشاء: {$created} | تحديث: {$updated} | واتساب ناجح: {$waOk}",
                'severity' => 'info',
                'category' => 'employees',
                'link' => route('employees.index'),
                'meta' => [],
            ], null);
        }

        $this->info("تم. إنشاء: {$created}, تحديثات: {$updated}, واتساب: {$waOk} ناجح".($waFail ? ", {$waFail} فشل" : ''));

        if (! $dry) {
            $this->newLine();
            $this->comment(
                'واتساب: إن وصلت الرسائل لك فقط، فغالبًا التطبيق في وضع تطوير Meta — أضف أرقام الموظفين كمستلمين معتمدين في لوحة مطوري واتساب، أو انشر التطبيق للإنتاج، أو عيّن قالبًا معتمدًا: WHATSAPP_EMPLOYEE_CREDENTIALS_TEMPLATE (+ LANG) في .env.'
            );
        }

        return $waFail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rosterRows(): array
    {
        $t = fn (string $slug, bool $isLead, ?int $allocation = null) => ['slug' => $slug, 'is_lead' => $isLead, 'allocation' => $allocation];

        return [
            ['name' => 'عبدالعزيز جاسم', 'phone' => '9647805872642', 'title' => 'مدير نظام', 'role' => 'admin', 'teams' => []],
            ['name' => 'أني', 'phone' => '9647831038642', 'title' => '', 'role' => 'member', 'skip_create' => true, 'find_by' => ['1xw'], 'teams' => []],
            ['name' => 'أحمد بشير', 'phone' => '9647868362896', 'title' => 'مدير موارد بشرية', 'role' => 'lead', 'teams' => [$t('hr', true)]],
            ['name' => 'محمد ثائر', 'phone' => '9647824862386', 'title' => 'مدير مالي', 'role' => 'lead', 'teams' => [$t('accounting', true)]],
            ['name' => 'نبراس', 'phone' => '9647821319817', 'title' => 'مديرة الفريق — المبيعات', 'role' => 'lead', 'teams' => [$t('sales', true)]],
            ['name' => 'حسين علي', 'phone' => '9647827478215', 'title' => 'موظف مبيعات', 'role' => 'member', 'teams' => [$t('sales', false)]],
            ['name' => 'شذى', 'phone' => '963938466137', 'title' => 'مديرة مدراء الحسابات', 'role' => 'lead', 'teams' => [$t('account', true)]],
            ['name' => 'مها', 'phone' => '962781252331', 'title' => 'مديرة حساب', 'role' => 'lead', 'teams' => [$t('account', false)]],
            ['name' => 'حسين سلام', 'phone' => '9647830868941', 'title' => 'مدير الفريق — الكتابة', 'role' => 'lead', 'teams' => [$t('writing', true)]],
            ['name' => 'ليث', 'phone' => '9647719516585', 'title' => 'كاتب + ميديا باير', 'role' => 'member', 'teams' => [$t('writing', false, 60), $t('media-buyer', false, 40)]],
            ['name' => 'محمود', 'phone' => '201278957988', 'title' => 'كاتب', 'role' => 'member', 'teams' => [$t('writing', false)]],
            ['name' => 'محمد خالد', 'phone' => '201098865472', 'title' => 'مدير الفريق — الميديا باير', 'role' => 'lead', 'teams' => [$t('media-buyer', true)]],
            ['name' => 'عبدالله عبدالغفور', 'phone' => '201062216962', 'title' => 'ميديا باير', 'role' => 'member', 'teams' => [$t('media-buyer', false)]],
        ];
    }

    /**
     * @param  array<int, array{slug: string, is_lead: bool, allocation?: int|null}>  $teamsConfig
     * @param  array<int, string>  $findBy
     * @return array{username: string, created: bool, updated: bool, wa_sent: bool, wa_skipped?: bool}
     */
    private function provisionOne(
        string $arabicName,
        string $phoneDigits,
        string $title,
        string $role,
        array $teamsConfig,
        bool $skipCreate,
        array $findBy,
        string $loginUrl,
        bool $dryRun,
        WhatsAppCloudService $whatsAppCloudService,
    ): array {
        $availability = $this->defaultAvailabilityPayload();
        $plainPassword = self::FIXED_PASSWORD;

        $user = null;
        $created = false;
        $updated = false;
        $resolvedUsername = '';

        if ($skipCreate) {
            $user = $this->findExistingUserForSkipRow($phoneDigits, $arabicName, $findBy);

            if ($dryRun) {
                $resolvedUsername = $user?->username ?: '(لم يُعثر على حساب — راجع find_by)';

                return ['username' => $resolvedUsername, 'created' => false, 'updated' => false, 'wa_sent' => false, 'wa_skipped' => true];
            }

            if (! $user) {
                throw new \RuntimeException('لم يُعثر على حساب موجود لهذا الصف.');
            }

            $resolvedUsername = (string) $user->username;

            DB::transaction(function () use ($user, $plainPassword, $role): void {
                $user->password = $plainPassword;
                if ($user->role !== 'admin') {
                    $user->role = $role;
                }
                $user->email = null;
                $user->save();
            });
            $updated = true;
        } else {
            $existing = User::query()->where('name', $arabicName)->first();
            $username = EmployeeUsername::uniqueFromArabicNameAndPhone($arabicName, $phoneDigits, $existing?->id);

            if ($dryRun) {
                $resolvedUsername = $existing?->username ?? $username;

                return ['username' => $resolvedUsername, 'created' => false, 'updated' => false, 'wa_sent' => false, 'wa_skipped' => true];
            }

            if ($existing) {
                $user = $existing;
                DB::transaction(function () use ($user, $arabicName, $username, $plainPassword, $role, $availability): void {
                    $user->name = $arabicName;
                    $user->username = $username;
                    $user->email = null;
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
                $user = DB::transaction(function () use ($arabicName, $username, $plainPassword, $role, $availability) {
                    return User::query()->create([
                        'name' => $arabicName,
                        'username' => $username,
                        'email' => null,
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

            $resolvedUsername = (string) $user->username;

            if (! $dryRun) {
                $user->teams()->sync($this->teamSyncPayload($teamsConfig));
            }
        }

        $message = EmployeeWhatsAppLoginBody::build(
            userName: $user->name,
            username: $resolvedUsername,
            arabicDisplayName: $arabicName,
            title: $title,
            loginUrl: $loginUrl,
            plainPassword: $plainPassword,
            role: $role,
        );

        $roleLabel = EmployeeWhatsAppLoginBody::roleLabel($role);

        $waSent = false;
        if (! $dryRun) {
            EmployeeOutsideContactSync::sync((int) $user->id, $phoneDigits, $arabicName);
            try {
                $whatsAppCloudService->sendEmployeeCredentials(
                    $phoneDigits,
                    $message,
                    $arabicName,
                    $resolvedUsername,
                    $plainPassword,
                    $loginUrl,
                    $roleLabel,
                );
                $waSent = true;
            } catch (Throwable $e) {
                $this->warn('فشل واتساب لـ '.$arabicName.' — '.$e->getMessage());
                $this->line('انسخ الرسالة يدويًا:');
                $this->line($message);
            }
        }

        return [
            'username' => $resolvedUsername,
            'created' => $created,
            'updated' => $updated,
            'wa_sent' => $waSent,
        ];
    }

    /**
     * @param  array<int, array{slug: string, is_lead: bool, allocation?: int|null}>  $teamsConfig
     * @return array<int, array<string, mixed>>
     */
    private function teamSyncPayload(array $teamsConfig): array
    {
        $payload = [];

        foreach ($teamsConfig as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $team = Team::query()->where('slug', $slug)->first();
            if (! $team) {
                continue;
            }
            $payload[$team->id] = [
                'allocation_percent' => array_key_exists('allocation', $row) && $row['allocation'] !== null
                    ? (int) $row['allocation']
                    : null,
                'is_lead' => (bool) ($row['is_lead'] ?? false),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $findBy
     */
    private function findExistingUserForSkipRow(string $phoneDigits, string $arabicName, array $findBy): ?User
    {
        return User::query()
            ->where(function ($q) use ($arabicName, $findBy): void {
                $q->where('name', $arabicName);
                foreach ($findBy as $hint) {
                    $hint = trim((string) $hint);
                    if ($hint === '') {
                        continue;
                    }
                    $q->orWhere('name', $hint);
                    $q->orWhere('username', $hint);
                    if (str_contains($hint, '@')) {
                        $q->orWhere('email', $hint);
                    }
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function ensureTeams(): void
    {
        if (Team::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'فريق الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'مدراء الحملات', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'مدراء الحسابات', 'slug' => 'account', 'sort_order' => 30],
            ['name' => 'المبيعات', 'slug' => 'sales', 'sort_order' => 40],
            ['name' => 'الموارد البشرية', 'slug' => 'hr', 'sort_order' => 50],
            ['name' => 'المحاسبة', 'slug' => 'accounting', 'sort_order' => 60],
        ];

        foreach ($defaults as $team) {
            Team::query()->firstOrCreate(['slug' => $team['slug']], $team);
        }
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

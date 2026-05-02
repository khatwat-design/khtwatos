<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Services\SmartNotificationService;
use App\Services\WhatsAppCloudService;
use App\Support\ArabCountryDialCodes;
use App\Support\EmployeeOutsideContactSync;
use App\Support\EmployeeWhatsAppLoginBody;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly WhatsAppCloudService $whatsAppCloudService,
    ) {}

    public function index(Request $request): Response
    {
        $teams = $this->ensureTeams();
        $employees = User::query()
            ->with(['teams:id,name,slug'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Employees/Index', [
            'arab_country_dial_options' => ArabCountryDialCodes::optionsForFront(),
            'employees' => $employees->map(function (User $user) {
                $split = $this->splitPhoneForDisplay($user->phone);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'phone_country_code' => $split['phone_country_code'],
                    'phone_local' => $split['phone_local'],
                    'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                    'role' => $user->role,
                    'is_bookable' => (bool) $user->is_bookable,
                    'availability_days' => $user->availability_days ?: [0, 1, 2, 3, 4],
                    'availability_start_time' => $user->availability_start_time ? substr((string) $user->availability_start_time, 0, 5) : '09:00',
                    'availability_end_time' => $user->availability_end_time ? substr((string) $user->availability_end_time, 0, 5) : '17:00',
                    'availability_schedule' => $this->normalizeAvailabilitySchedule(
                        is_array($user->availability_schedule) ? $user->availability_schedule : null,
                        $user->availability_days ?: [0, 1, 2, 3, 4],
                        $user->availability_start_time ? substr((string) $user->availability_start_time, 0, 5) : '09:00',
                        $user->availability_end_time ? substr((string) $user->availability_end_time, 0, 5) : '17:00',
                    ),
                    'teams' => $user->teams->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'is_lead' => (bool) ($team->pivot?->is_lead),
                        'allocation_percent' => $team->pivot?->allocation_percent,
                    ])->values(),
                ];
            })->values(),
            'teams' => $teams->map(fn (Team $team) => Arr::only($team->toArray(), ['id', 'name', 'slug']))->values(),
            'canAssignAdmin' => $request->user()?->isAdmin() ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedEmployee($request, isCreate: true);

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_bookable' => (bool) ($data['is_bookable'] ?? false),
            'availability_days' => $data['availability_days'],
            'availability_start_time' => $data['availability_start_time'],
            'availability_end_time' => $data['availability_end_time'],
            'availability_schedule' => $data['availability_schedule'],
            'email_verified_at' => now(),
        ]);

        $user->teams()->sync($this->teamPivotSyncPayload($data['teams'] ?? []));
        if (! empty($user->phone)) {
            EmployeeOutsideContactSync::sync((int) $user->id, (string) $user->phone, (string) $user->name);
        }
        $adminIds = User::query()->where('role', 'admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->smartNotifications->notifyUsers($adminIds, [
            'title' => 'إضافة موظف جديد',
            'body' => $user->name,
            'severity' => 'info',
            'category' => 'employees',
            'link' => route('employees.index'),
            'meta' => ['employee_id' => $user->id],
        ], $request->user()?->id);

        return redirect()->route('employees.index');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $this->validatedEmployee($request, isCreate: false, userId: $employee->id);

        $employee->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => null,
            'role' => $data['role'],
            'is_bookable' => (bool) ($data['is_bookable'] ?? false),
            'availability_days' => $data['availability_days'],
            'availability_start_time' => $data['availability_start_time'],
            'availability_end_time' => $data['availability_end_time'],
            'availability_schedule' => $data['availability_schedule'],
        ]);

        if (! empty($data['password'])) {
            $employee->password = $data['password'];
        }

        $employee->save();
        $employee->teams()->sync($this->teamPivotSyncPayload($data['teams'] ?? []));
        if (! empty($employee->phone)) {
            EmployeeOutsideContactSync::sync((int) $employee->id, (string) $employee->phone, (string) $employee->name);
        }

        return redirect()->route('employees.index');
    }

    public function sendLoginWhatsApp(Request $request, User $employee): RedirectResponse
    {
        $phone = $employee->phone;
        if ($phone === null || trim((string) $phone) === '') {
            return redirect()->route('employees.index')->with('error', 'لا يوجد رقم هاتف محفوظ لهذا الموظف.');
        }

        $plain = Str::password(12, symbols: true);
        $employee->password = $plain;
        $employee->save();

        $loginUrl = route('login', [], absolute: true);
        $roleLabel = EmployeeWhatsAppLoginBody::roleLabel((string) $employee->role);
        $body = EmployeeWhatsAppLoginBody::build(
            userName: (string) $employee->name,
            username: (string) $employee->username,
            arabicDisplayName: (string) $employee->name,
            title: '',
            loginUrl: $loginUrl,
            plainPassword: $plain,
            role: (string) $employee->role,
        );

        try {
            $this->whatsAppCloudService->sendEmployeeCredentials(
                (string) $phone,
                $body,
                (string) $employee->name,
                (string) $employee->username,
                $plain,
                $loginUrl,
                $roleLabel,
            );
        } catch (Throwable $e) {
            return redirect()->route('employees.index')->with('error', 'فشل إرسال واتساب: '.$e->getMessage());
        }

        return redirect()->route('employees.index')->with(
            'success',
            'تم إرسال بيانات الدخول عبر واتساب وتعيين كلمة مرور جديدة للموظف.'
        );
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $employee->id) {
            return redirect()->route('employees.index')
                ->withErrors(['employee' => 'لا يمكنك حذف حسابك من صفحة الموظفين.']);
        }

        $employee->delete();

        return redirect()->route('employees.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEmployee(Request $request, bool $isCreate, ?int $userId = null): array
    {
        $this->ensureTeams();
        $allowedDialCodes = array_keys(ArabCountryDialCodes::options());
        $usernameRule = ['required', 'string', 'regex:/^[a-z0-9]{2,32}$/', 'max:32', Rule::unique('users', 'username')];
        if ($userId) {
            $usernameRule = ['required', 'string', 'regex:/^[a-z0-9]{2,32}$/', 'max:32', Rule::unique('users', 'username')->ignore($userId)];
        }

        $passwordRule = $isCreate
            ? ['required', 'string', 'min:8']
            : ['nullable', 'string', 'min:8'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => $usernameRule,
            'password' => $passwordRule,
            'role' => ['required', 'in:admin,lead,member'],
            'is_bookable' => ['nullable', 'boolean'],
            'phone_country_code' => ['nullable', 'string', Rule::in($allowedDialCodes)],
            'phone_local' => ['nullable', 'string', 'max:40'],
            'availability_schedule' => ['required', 'array', 'size:7'],
            'availability_schedule.*.day' => ['required', 'integer', 'between:0,6'],
            'availability_schedule.*.enabled' => ['required', 'boolean'],
            'availability_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'availability_schedule.*.end' => ['nullable', 'date_format:H:i'],
            'teams' => ['nullable', 'array'],
            'teams.*.id' => ['required', 'exists:teams,id'],
            'teams.*.allocation_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'teams.*.is_lead' => ['nullable', 'boolean'],
        ]);

        $cc = isset($data['phone_country_code']) ? trim((string) $data['phone_country_code']) : '';
        $localDigits = ArabCountryDialCodes::normalizeDigits((string) ($data['phone_local'] ?? ''));
        $localDigits = ltrim($localDigits, '0');

        if ($cc === '' && $localDigits === '') {
            $data['phone'] = null;
        } elseif ($cc === '' || $localDigits === '') {
            throw ValidationException::withMessages([
                'phone_local' => 'أكمل رقم الهاتف مع مفتاح الدولة أو اترك الحقلين فارغين.',
            ]);
        } else {
            if (strlen($localDigits) < 6 || strlen($localDigits) > 14) {
                throw ValidationException::withMessages([
                    'phone_local' => 'رقم الهاتف المحلي غير صالح.',
                ]);
            }

            $full = $cc.$localDigits;
            if (strlen($full) > 15) {
                throw ValidationException::withMessages([
                    'phone_local' => 'الرقم الكامل أطول من المسموح.',
                ]);
            }

            $data['phone'] = $full;
        }

        unset($data['phone_country_code'], $data['phone_local']);

        if (($data['role'] ?? null) === 'admin' && ! $request->user()?->isAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'مدير الموارد البشرية لا يمكنه إنشاء أو تعيين مدير نظام.',
            ]);
        }

        $schedule = [];
        $enabledDays = [];
        $firstStart = null;
        $firstEnd = null;

        foreach ($data['availability_schedule'] as $row) {
            $day = (int) ($row['day'] ?? -1);
            if ($day < 0 || $day > 6) {
                continue;
            }

            $enabled = (bool) ($row['enabled'] ?? false);
            $start = $enabled ? ($row['start'] ?? null) : null;
            $end = $enabled ? ($row['end'] ?? null) : null;

            if ($enabled) {
                if (! $start || ! $end) {
                    throw ValidationException::withMessages([
                        "availability_schedule.$day.start" => 'حدد وقت البداية والنهاية لهذا اليوم.',
                    ]);
                }

                if ($start >= $end) {
                    throw ValidationException::withMessages([
                        "availability_schedule.$day.end" => 'وقت النهاية يجب أن يكون بعد البداية.',
                    ]);
                }

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

        if (! count($enabledDays)) {
            throw ValidationException::withMessages([
                'availability_schedule' => 'يجب اختيار يوم توفر واحد على الأقل.',
            ]);
        }

        $data['availability_schedule'] = $schedule;
        $data['availability_days'] = array_values(array_unique($enabledDays));
        $data['availability_start_time'] = $firstStart;
        $data['availability_end_time'] = $firstEnd;

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teams
     * @return array<int, array<string, mixed>>
     */
    private function teamPivotSyncPayload(array $teams): array
    {
        $payload = [];

        foreach ($teams as $team) {
            $id = (int) ($team['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $payload[$id] = [
                'allocation_percent' => isset($team['allocation_percent']) && $team['allocation_percent'] !== ''
                    ? (int) $team['allocation_percent']
                    : null,
                'is_lead' => (bool) ($team['is_lead'] ?? false),
            ];
        }

        return $payload;
    }

    /**
     * @return Collection<int, Team>
     */
    private function ensureTeams()
    {
        if (Team::query()->exists()) {
            return Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']);
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

        return Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']);
    }

    /**
     * @param  array<string, mixed>|null  $schedule
     * @param  array<int, int>  $days
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAvailabilitySchedule(?array $schedule, array $days, string $defaultStart, string $defaultEnd): array
    {
        $normalized = [];

        for ($day = 0; $day <= 6; $day++) {
            $current = Arr::get($schedule ?? [], (string) $day, []);
            $enabled = isset($current['enabled'])
                ? (bool) $current['enabled']
                : in_array($day, $days, true);
            $start = $enabled ? (($current['start'] ?? $defaultStart) ?: $defaultStart) : null;
            $end = $enabled ? (($current['end'] ?? $defaultEnd) ?: $defaultEnd) : null;

            $normalized[] = [
                'day' => $day,
                'enabled' => $enabled,
                'start' => $start ? substr((string) $start, 0, 5) : null,
                'end' => $end ? substr((string) $end, 0, 5) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{phone_country_code: string, phone_local: string}
     */
    private function splitPhoneForDisplay(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return ['phone_country_code' => '964', 'phone_local' => ''];
        }

        $digits = ArabCountryDialCodes::normalizeDigits($stored);
        foreach (ArabCountryDialCodes::codesLongestFirst() as $code) {
            if (str_starts_with($digits, $code)) {
                return [
                    'phone_country_code' => $code,
                    'phone_local' => substr($digits, strlen($code)),
                ];
            }
        }

        return ['phone_country_code' => '964', 'phone_local' => $digits];
    }
}

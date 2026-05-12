<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\User;
use App\Models\UserActivitySession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مسؤول عن سجل حضور الموظفين، وقياس الوقت الفعّال داخل النظام،
 * عبر "نبضات" دورية من المتصفّح + جلسات نشاط، يومًا بيوم.
 */
class EmployeePresenceService
{
    /** بعد هذه الفترة من آخر نبضة نعتبر الجلسة منتهية (ثوانٍ). */
    private const SESSION_TIMEOUT_SECONDS = 180; // 3 دقائق

    /** أقصى ثوانٍ يمكن إضافتها لكل نبضة منفردة لمنع الانتفاخ. */
    private const HEARTBEAT_MAX_ADD_SECONDS = 90;

    public function todayDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    public function attendanceForToday(User $user): ?EmployeeAttendance
    {
        if (! Schema::hasTable('employee_attendances')) {
            return null;
        }

        return $this->findOrNewToday($user)->exists ? $this->findOrNewToday($user) : null;
    }

    /**
     * يضمن استرجاع/إنشاء سجل اليوم بصيغة تاريخ موحّدة (Y-m-d) لمنع تكرار صفوف اليوم نفسه.
     */
    private function findOrNewToday(User $user): EmployeeAttendance
    {
        $date = $this->todayDate()->toDateString();

        /** @var EmployeeAttendance|null $existing */
        $existing = EmployeeAttendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $model = new EmployeeAttendance;
        $model->user_id = $user->id;
        $model->work_date = $date;

        return $model;
    }

    /**
     * هل يحتاج هذا المستخدم إلى تعبئة نموذج الحضور اليوم؟
     */
    public function needsDailyCheckIn(User $user): bool
    {
        $attendance = $this->attendanceForToday($user);

        return $attendance === null || $attendance->checked_in_at === null;
    }

    /**
     * تسجيل حضور اليوم (مع تفاصيل اختيارية).
     *
     * @param  array{status?: string, mood?: ?string, plan_for_today?: ?string, note?: ?string}  $payload
     */
    public function checkIn(User $user, array $payload): EmployeeAttendance
    {
        $attendance = $this->findOrNewToday($user);

        $attendance->status = $payload['status'] ?? 'present';
        $attendance->mood = $payload['mood'] ?? null;
        $attendance->plan_for_today = isset($payload['plan_for_today'])
            ? mb_substr((string) $payload['plan_for_today'], 0, 480)
            : $attendance->plan_for_today;
        $attendance->note = isset($payload['note'])
            ? mb_substr((string) $payload['note'], 0, 480)
            : $attendance->note;

        if ($attendance->checked_in_at === null) {
            $attendance->checked_in_at = Carbon::now();
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * تسجيل انصراف (اختياري — وإلا سنحسب نهاية النشاط تلقائيًا).
     */
    public function checkOut(User $user): ?EmployeeAttendance
    {
        $attendance = $this->attendanceForToday($user);
        if (! $attendance) {
            return null;
        }

        if ($attendance->checked_in_at === null) {
            $attendance->checked_in_at = Carbon::now();
        }

        $attendance->checked_out_at = Carbon::now();
        $attendance->save();

        $this->endOpenSessions($user);

        return $attendance;
    }

    /**
     * تسجيل نبضة نشاط — يستدعى من الواجهة كل ~30 ثانية أثناء بقاء التبويب نشطًا.
     */
    public function recordHeartbeat(User $user, int $elapsedSeconds, ?string $userAgent = null, ?string $ip = null): UserActivitySession
    {
        $now = Carbon::now();
        $today = $this->todayDate()->toDateString();
        $bounded = max(0, min(self::HEARTBEAT_MAX_ADD_SECONDS, $elapsedSeconds));

        return DB::transaction(function () use ($user, $now, $today, $bounded, $userAgent, $ip): UserActivitySession {
            /** @var UserActivitySession|null $session */
            $session = UserActivitySession::query()
                ->where('user_id', $user->id)
                ->where('is_open', true)
                ->where('work_date', $today)
                ->orderByDesc('id')
                ->first();

            $shouldStartNew = $session === null
                || $session->last_heartbeat_at === null
                || $session->last_heartbeat_at->diffInSeconds($now) > self::SESSION_TIMEOUT_SECONDS;

            if ($shouldStartNew) {
                if ($session) {
                    $session->is_open = false;
                    $session->ended_at = $session->last_heartbeat_at ?? $now;
                    $session->save();
                }

                $session = UserActivitySession::query()->create([
                    'user_id' => $user->id,
                    'work_date' => $today,
                    'started_at' => $now,
                    'last_heartbeat_at' => $now,
                    'active_seconds' => $bounded,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 240) : null,
                    'ip' => $ip ? mb_substr($ip, 0, 60) : null,
                    'is_open' => true,
                ]);
            } else {
                $session->active_seconds = (int) $session->active_seconds + $bounded;
                $session->last_heartbeat_at = $now;
                $session->save();
            }

            $this->syncAttendanceActiveSeconds($user, $today);

            return $session;
        });
    }

    /**
     * إنهاء جلسات المستخدم المفتوحة (يتم استدعاؤه عند تسجيل الخروج).
     */
    public function endOpenSessions(User $user): void
    {
        $now = Carbon::now();

        UserActivitySession::query()
            ->where('user_id', $user->id)
            ->where('is_open', true)
            ->get()
            ->each(function (UserActivitySession $session) use ($now): void {
                $session->is_open = false;
                $session->ended_at = $session->last_heartbeat_at ?? $now;
                $session->save();
            });

        $this->syncAttendanceActiveSeconds($user, $this->todayDate()->toDateString());
    }

    /**
     * مهمة مجدولة — تُغلق الجلسات التي تجاوزت مهلة عدم النشاط.
     */
    public function autoCloseStaleSessions(): int
    {
        $now = Carbon::now();
        $threshold = $now->copy()->subSeconds(self::SESSION_TIMEOUT_SECONDS);

        $stale = UserActivitySession::query()
            ->where('is_open', true)
            ->where('last_heartbeat_at', '<', $threshold)
            ->get();

        foreach ($stale as $session) {
            $session->is_open = false;
            $session->ended_at = $session->last_heartbeat_at ?? $now;
            $session->save();
        }

        return $stale->count();
    }

    /**
     * يجمع ثواني الجلسات اليوم ويحدّث سجل الحضور المقابل.
     */
    public function syncAttendanceActiveSeconds(User $user, string $date): void
    {
        if (! Schema::hasTable('employee_attendances')) {
            return;
        }

        $totalSeconds = (int) UserActivitySession::query()
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->sum('active_seconds');

        $attendance = $this->findOrNewToday($user);
        $attendance->active_seconds = $totalSeconds;
        // لا نضبط checked_in_at هنا — يبقى للحضور اليدوي فقط حتى تظهر نافذة التسجيل اليومية للمستخدم.
        if (! $attendance->status) {
            $attendance->status = 'present';
        }
        $attendance->save();
    }
}

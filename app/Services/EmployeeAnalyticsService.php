<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\EmployeeAttendance;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Models\UserActivitySession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EmployeeAnalyticsService
{
    private const DEFAULT_RANGE_DAYS = 30;

    /** افتراض هدف الوقت اليومي للموظف (بالثواني). يُستخدم في حساب نقاط الأداء. */
    private const DAILY_ACTIVE_TARGET_SECONDS = 6 * 3600;

    /** يومان فأكثر بدون نشاط = خامل. */
    private const INACTIVE_DAYS_THRESHOLD = 3;

    /**
     * بطاقة بيانات تحليلية لكل موظف للفترة المطلوبة، مع مقارنة بفترة سابقة مساوية
     * (لاستخراج اتجاه الأداء)، ونقاط أداء مركّبة وإشارات جودة قابلة للقراءة في الواجهة.
     *
     * @param  Collection<int, User>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function analyticsForEmployees(Collection $employees, ?int $rangeDays = null): array
    {
        $rangeDays = max(1, min(180, $rangeDays ?? self::DEFAULT_RANGE_DAYS));
        $from = CarbonImmutable::now()->subDays($rangeDays - 1)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        $prevTo = $from->subSecond();
        $prevFrom = $prevTo->subDays($rangeDays - 1)->startOfDay();

        $userIds = $employees->pluck('id')->all();

        // الفترة الحالية
        $attendance = $this->collectAttendance($userIds, $from, $to);
        $openTasks = $this->collectOpenTasks($userIds);
        $completion = $this->collectTaskCompletion($userIds, $from, $to);
        $tickets = $this->collectTickets($userIds, $from, $to);
        $firstResponse = $this->collectFirstResponse($userIds, $from, $to);
        $todaySessions = $this->collectTodaySessions($userIds);

        // الفترة السابقة (لحساب التطوّر)
        $attendancePrev = $this->collectAttendance($userIds, $prevFrom, $prevTo);
        $completionPrev = $this->collectTaskCompletion($userIds, $prevFrom, $prevTo);
        $ticketsPrev = $this->collectTickets($userIds, $prevFrom, $prevTo);

        // متوسط المهام المفتوحة في الفريق لقياس توازن الأحمال
        $openCounts = array_map(fn ($r) => (int) ($r['count'] ?? 0), $openTasks);
        $teamAvgOpen = empty($openCounts) ? 0 : max(1.0, array_sum($openCounts) / max(1, count($openCounts)));

        // أيام العمل التقديرية في الفترة (لتطبيع نسبة الحضور)
        $businessDays = max(1, (int) round($rangeDays * 5 / 7));

        return $employees->map(function (User $user) use (
            $attendance,
            $openTasks,
            $completion,
            $tickets,
            $firstResponse,
            $attendancePrev,
            $completionPrev,
            $ticketsPrev,
            $rangeDays,
            $teamAvgOpen,
            $businessDays,
            $todaySessions,
        ): array {
            $att = $attendance[$user->id] ?? [];
            $open = $openTasks[$user->id] ?? ['count' => 0, 'overdue' => 0];
            $comp = $completion[$user->id] ?? ['count' => 0, 'avg_seconds' => 0, 'overdue' => 0, 'on_time' => 0];
            $tic = $tickets[$user->id] ?? ['resolved' => 0, 'open' => 0, 'avg_seconds' => 0];
            $fr = $firstResponse[$user->id] ?? ['avg_seconds' => 0, 'count' => 0];

            $attPrev = $attendancePrev[$user->id] ?? [];
            $compPrev = $completionPrev[$user->id] ?? ['count' => 0, 'avg_seconds' => 0];
            $ticPrev = $ticketsPrev[$user->id] ?? ['resolved' => 0];

            // نسب جودة
            $onTimeRatio = $comp['count'] > 0 ? round($comp['on_time'] / $comp['count'], 3) : null;
            $attendanceRatio = $businessDays > 0 ? round(min(1.0, ($att['days_present'] ?? 0) / $businessDays), 3) : 0.0;
            $avgDailyTarget = $att['avg_active_seconds'] ?? 0;
            $activityRatio = self::DAILY_ACTIVE_TARGET_SECONDS > 0
                ? round(min(1.0, $avgDailyTarget / self::DAILY_ACTIVE_TARGET_SECONDS), 3)
                : 0.0;

            // نقاط الأداء (0..100): 40% on-time + 25% حضور + 20% نشاط + 15% مهام منجزة (مع تخفيف)
            $completionFactor = min(1.0, ($comp['count'] ?? 0) / max(5, $rangeDays / 6));
            $score = (int) round(
                40 * ($onTimeRatio ?? 0)
                + 25 * $attendanceRatio
                + 20 * $activityRatio
                + 15 * $completionFactor
            );
            $score = max(0, min(100, $score));
            $grade = match (true) {
                $score >= 85 => 'A',
                $score >= 70 => 'B',
                $score >= 55 => 'C',
                $score >= 40 => 'D',
                default => 'E',
            };

            // اتجاهات (% تغيّر) — null تعني لا يمكن المقارنة (لا أساس).
            $tasksDelta = $this->percentDelta($comp['count'] ?? 0, $compPrev['count'] ?? 0);
            $activeSecondsDelta = $this->percentDelta($att['total_active_seconds'] ?? 0, $attPrev['total_active_seconds'] ?? 0);
            $avgCompletionDelta = $this->percentDelta($comp['avg_seconds'] ?? 0, $compPrev['avg_seconds'] ?? 0);
            $ticketsResolvedDelta = $this->percentDelta($tic['resolved'] ?? 0, $ticPrev['resolved'] ?? 0);

            // إشارات الجودة (badges/alerts) — صفيف من رموز/تسميات قابلة للعرض.
            $signals = [];
            $streak = $this->computeStreak($att['rows_by_date'] ?? []);
            if ($streak >= 3) {
                $signals[] = ['code' => 'streak', 'tone' => 'amber', 'label' => "🔥 {$streak} أيام متتالية"];
            }
            if (($onTimeRatio ?? 0) >= 0.9 && ($comp['count'] ?? 0) >= 5) {
                $signals[] = ['code' => 'on_time_hero', 'tone' => 'emerald', 'label' => 'بطل الالتزام بالمواعيد'];
            }
            if (($open['overdue'] ?? 0) >= 3) {
                $signals[] = ['code' => 'overdue_load', 'tone' => 'rose', 'label' => "⚠ {$open['overdue']} مهام متأخرة"];
            }
            $isOverloaded = ($open['count'] ?? 0) > 5 && ($open['count'] ?? 0) > $teamAvgOpen * 1.6;
            if ($isOverloaded) {
                $signals[] = ['code' => 'overloaded', 'tone' => 'orange', 'label' => 'حِمل عمل مرتفع'];
            }
            $lastActivityDays = $this->daysSinceLastActivity($att['rows_by_date'] ?? []);
            $isInactive = $lastActivityDays !== null && $lastActivityDays >= self::INACTIVE_DAYS_THRESHOLD;
            if ($isInactive) {
                $signals[] = ['code' => 'inactive', 'tone' => 'slate', 'label' => "⏰ بدون نشاط منذ {$lastActivityDays} أيام"];
            }
            if (($fr['avg_seconds'] ?? 0) > 0 && $fr['avg_seconds'] <= 3600 && $fr['count'] >= 3) {
                $signals[] = ['code' => 'fast_response', 'tone' => 'sky', 'label' => 'سريع الاستجابة'];
            }

            // ميزان الحِمل: 100 = مساوٍ للمتوسط، >100 يعني فوق المتوسط، <100 يعني تحته.
            $workloadIndex = $teamAvgOpen > 0
                ? (int) round((($open['count'] ?? 0) / $teamAvgOpen) * 100)
                : 0;

            return [
                'id' => $user->id,
                'range_days' => $rangeDays,
                'attendance' => [
                    'days_present' => $att['days_present'] ?? 0,
                    'total_active_seconds' => $att['total_active_seconds'] ?? 0,
                    'avg_active_seconds' => $att['avg_active_seconds'] ?? 0,
                    'last_active_seconds' => $att['last_active_seconds'] ?? 0,
                    'today_active_seconds' => $att['today_active_seconds'] ?? 0,
                    'today_checked_in' => $att['today_checked_in'] ?? false,
                    'today_mood' => $att['today_mood'] ?? null,
                    'today_note' => $att['today_note'] ?? null,
                    'today_plan' => $att['today_plan'] ?? null,
                    'last_check_in_at' => $att['last_check_in_at'] ?? null,
                    'last_seven_days' => $att['last_seven_days'] ?? [],
                    'streak_days' => $streak,
                    'attendance_ratio' => $attendanceRatio,
                    'days_since_last_activity' => $lastActivityDays,
                    'recent_notes' => $att['recent_notes'] ?? [],
                    'today_sessions' => $todaySessions[$user->id] ?? [],
                ],
                'tasks' => [
                    'open' => (int) ($open['count'] ?? 0),
                    'overdue_open' => (int) ($open['overdue'] ?? 0),
                    'completed' => (int) ($comp['count'] ?? 0),
                    'completed_on_time' => (int) ($comp['on_time'] ?? 0),
                    'completed_overdue' => (int) ($comp['overdue'] ?? 0),
                    'avg_completion_seconds' => (int) ($comp['avg_seconds'] ?? 0),
                    'on_time_ratio' => $onTimeRatio,
                    'workload_index' => $workloadIndex,
                ],
                'tickets' => [
                    'resolved' => (int) ($tic['resolved'] ?? 0),
                    'open' => (int) ($tic['open'] ?? 0),
                    'avg_resolution_seconds' => (int) ($tic['avg_seconds'] ?? 0),
                    'first_response_avg_seconds' => (int) ($fr['avg_seconds'] ?? 0),
                    'first_response_count' => (int) ($fr['count'] ?? 0),
                ],
                'productivity' => [
                    'score' => $score,
                    'grade' => $grade,
                    'breakdown' => [
                        'on_time' => (int) round(($onTimeRatio ?? 0) * 100),
                        'attendance' => (int) round($attendanceRatio * 100),
                        'activity' => (int) round($activityRatio * 100),
                        'completion' => (int) round($completionFactor * 100),
                    ],
                ],
                'trends' => [
                    'tasks_completed_delta' => $tasksDelta,
                    'active_seconds_delta' => $activeSecondsDelta,
                    'avg_completion_seconds_delta' => $avgCompletionDelta,
                    'tickets_resolved_delta' => $ticketsResolvedDelta,
                ],
                'signals' => $signals,
                'is_overloaded' => $isOverloaded,
                'is_inactive' => $isInactive,
            ];
        })->keyBy('id')->toArray();
    }

    /**
     * ملخّص أداء على مستوى الفريق: KPI رئيسية + قوائم أفضل/يحتاج انتباه + مقارنة بالفترة السابقة.
     *
     * @param  array<int, array<string, mixed>>  $analytics
     * @param  Collection<int, User>  $employees
     * @return array<string, mixed>
     */
    public function teamSummary(array $analytics, Collection $employees, int $rangeDays): array
    {
        $rows = array_values($analytics);
        $count = max(1, count($rows));

        $totals = [
            'tasks_completed' => 0,
            'tasks_on_time' => 0,
            'tasks_completed_overdue' => 0,
            'tasks_open' => 0,
            'tasks_overdue_open' => 0,
            'active_seconds' => 0,
            'today_active_seconds' => 0,
            'tickets_resolved' => 0,
            'tickets_open' => 0,
            'first_response_seconds' => 0,
            'first_response_count' => 0,
            'avg_completion_weighted' => 0,
            'days_present' => 0,
            'score_sum' => 0,
            'today_checked_in' => 0,
        ];

        $moodCounts = ['great' => 0, 'good' => 0, 'neutral' => 0, 'tired' => 0, 'low' => 0];
        $todayNotes = [];

        $byEmployee = [];
        foreach ($employees as $emp) {
            $a = $analytics[$emp->id] ?? null;
            if (! $a) {
                continue;
            }

            $todayMood = $a['attendance']['today_mood'] ?? null;
            if ($todayMood && isset($moodCounts[$todayMood])) {
                $moodCounts[$todayMood]++;
            }

            $todayNote = trim((string) ($a['attendance']['today_note'] ?? ''));
            if ($todayNote !== '' && ($a['attendance']['today_checked_in'] ?? false)) {
                $todayNotes[] = [
                    'employee_id' => $emp->id,
                    'name' => (string) $emp->name,
                    'avatar_url' => $emp->avatar_path ? Storage::disk('public')->url($emp->avatar_path) : null,
                    'mood' => $todayMood,
                    'note' => mb_substr($todayNote, 0, 320),
                ];
            }

            $totals['tasks_completed'] += $a['tasks']['completed'] ?? 0;
            $totals['tasks_on_time'] += $a['tasks']['completed_on_time'] ?? 0;
            $totals['tasks_completed_overdue'] += $a['tasks']['completed_overdue'] ?? 0;
            $totals['tasks_open'] += $a['tasks']['open'] ?? 0;
            $totals['tasks_overdue_open'] += $a['tasks']['overdue_open'] ?? 0;
            $totals['active_seconds'] += $a['attendance']['total_active_seconds'] ?? 0;
            $totals['today_active_seconds'] += $a['attendance']['today_active_seconds'] ?? 0;
            $totals['days_present'] += $a['attendance']['days_present'] ?? 0;
            $totals['tickets_resolved'] += $a['tickets']['resolved'] ?? 0;
            $totals['tickets_open'] += $a['tickets']['open'] ?? 0;
            $totals['first_response_seconds'] += ($a['tickets']['first_response_avg_seconds'] ?? 0) * ($a['tickets']['first_response_count'] ?? 0);
            $totals['first_response_count'] += $a['tickets']['first_response_count'] ?? 0;
            $totals['avg_completion_weighted'] += ($a['tasks']['avg_completion_seconds'] ?? 0) * ($a['tasks']['completed'] ?? 0);
            $totals['score_sum'] += $a['productivity']['score'] ?? 0;
            if ($a['attendance']['today_checked_in'] ?? false) {
                $totals['today_checked_in']++;
            }

            $byEmployee[$emp->id] = [
                'id' => $emp->id,
                'name' => (string) $emp->name,
                'avatar_url' => $emp->avatar_path ? Storage::disk('public')->url($emp->avatar_path) : null,
                'score' => $a['productivity']['score'] ?? 0,
                'grade' => $a['productivity']['grade'] ?? 'E',
                'tasks_completed' => $a['tasks']['completed'] ?? 0,
                'overdue_open' => $a['tasks']['overdue_open'] ?? 0,
                'open' => $a['tasks']['open'] ?? 0,
                'on_time_ratio' => $a['tasks']['on_time_ratio'],
                'workload_index' => $a['tasks']['workload_index'] ?? 0,
                'is_inactive' => $a['is_inactive'] ?? false,
                'is_overloaded' => $a['is_overloaded'] ?? false,
                'tickets_resolved' => $a['tickets']['resolved'] ?? 0,
                'today_checked_in' => $a['attendance']['today_checked_in'] ?? false,
                'role' => $emp->role,
            ];
        }

        $bookableCount = 0;
        foreach ($employees as $emp) {
            if ($emp->is_bookable) {
                $bookableCount++;
            }
        }

        $rowsByEmp = array_values($byEmployee);
        usort($rowsByEmp, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topPerformers = array_slice($rowsByEmp, 0, 3);

        $bottlenecks = array_values(array_filter($rowsByEmp, fn ($r) => ($r['overdue_open'] ?? 0) > 0));
        usort($bottlenecks, fn ($a, $b) => $b['overdue_open'] <=> $a['overdue_open']);
        $bottlenecks = array_slice($bottlenecks, 0, 3);

        $inactive = array_values(array_filter($rowsByEmp, fn ($r) => $r['is_inactive']));
        $overloaded = array_values(array_filter($rowsByEmp, fn ($r) => $r['is_overloaded']));

        $completionRate = $totals['tasks_completed'] > 0
            ? round($totals['tasks_on_time'] / $totals['tasks_completed'], 3)
            : null;

        $sla = [
            'avg_completion_seconds' => $totals['tasks_completed'] > 0
                ? (int) round($totals['avg_completion_weighted'] / $totals['tasks_completed'])
                : 0,
            'avg_first_response_seconds' => $totals['first_response_count'] > 0
                ? (int) round($totals['first_response_seconds'] / $totals['first_response_count'])
                : 0,
        ];

        $punctualityToday = $bookableCount > 0
            ? round($totals['today_checked_in'] / $bookableCount, 3)
            : null;

        $avgScore = (int) round($totals['score_sum'] / $count);

        $moodTotal = array_sum($moodCounts);
        $moodDistribution = [];
        foreach ($moodCounts as $key => $cnt) {
            $moodDistribution[$key] = [
                'count' => $cnt,
                'ratio' => $moodTotal > 0 ? round($cnt / $moodTotal, 3) : 0,
            ];
        }

        return [
            'range_days' => $rangeDays,
            'employees_total' => count($byEmployee),
            'bookable_total' => $bookableCount,
            'today_checked_in' => $totals['today_checked_in'],
            'punctuality_today' => $punctualityToday,
            'today_active_seconds' => $totals['today_active_seconds'],
            'period_active_seconds' => $totals['active_seconds'],
            'tasks_completed' => $totals['tasks_completed'],
            'tasks_open' => $totals['tasks_open'],
            'tasks_overdue_open' => $totals['tasks_overdue_open'],
            'tickets_resolved' => $totals['tickets_resolved'],
            'tickets_open' => $totals['tickets_open'],
            'on_time_completion_rate' => $completionRate,
            'sla' => $sla,
            'avg_productivity_score' => $avgScore,
            'top_performers' => $topPerformers,
            'bottlenecks' => $bottlenecks,
            'inactive_employees' => array_slice($inactive, 0, 5),
            'overloaded_employees' => array_slice($overloaded, 0, 5),
            'mood_distribution_today' => $moodDistribution,
            'mood_total_today' => $moodTotal,
            'today_notes' => $todayNotes,
        ];
    }

    /**
     * تجميع KPI على مستوى كل فريق (عبر العضوية في pivot users-teams).
     *
     * @param  array<int, array<string, mixed>>  $analytics
     * @param  Collection<int, User>  $employees
     * @return list<array<string, mixed>>
     */
    public function teamsBreakdown(array $analytics, Collection $employees, int $rangeDays): array
    {
        $teamsMap = [];

        foreach ($employees as $emp) {
            $a = $analytics[$emp->id] ?? null;
            if (! $a) {
                continue;
            }
            $teams = $emp->relationLoaded('teams') ? $emp->teams : $emp->teams()->get();
            foreach ($teams as $team) {
                $tid = (int) $team->id;
                if (! isset($teamsMap[$tid])) {
                    $teamsMap[$tid] = [
                        'id' => $tid,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'lead' => null,
                        'members' => [],
                        '_acc' => [
                            'tasks_completed' => 0,
                            'tasks_on_time' => 0,
                            'tasks_open' => 0,
                            'tasks_overdue_open' => 0,
                            'today_active_seconds' => 0,
                            'period_active_seconds' => 0,
                            'tickets_resolved' => 0,
                            'tickets_open' => 0,
                            'score_sum' => 0,
                            'today_checked_in' => 0,
                            'streak_max' => 0,
                            'avg_completion_weighted' => 0,
                        ],
                    ];
                }

                $teamsMap[$tid]['members'][] = [
                    'id' => $emp->id,
                    'name' => (string) $emp->name,
                    'avatar_url' => $emp->avatar_path ? Storage::disk('public')->url($emp->avatar_path) : null,
                    'score' => $a['productivity']['score'] ?? 0,
                    'grade' => $a['productivity']['grade'] ?? 'E',
                    'today_checked_in' => $a['attendance']['today_checked_in'] ?? false,
                    'today_active_seconds' => $a['attendance']['today_active_seconds'] ?? 0,
                    'tasks_completed' => $a['tasks']['completed'] ?? 0,
                    'overdue_open' => $a['tasks']['overdue_open'] ?? 0,
                    'is_lead' => (bool) ($team->pivot?->is_lead ?? false),
                    'allocation' => $team->pivot?->allocation_percent,
                ];

                if ($team->pivot?->is_lead) {
                    $teamsMap[$tid]['lead'] = [
                        'id' => $emp->id,
                        'name' => (string) $emp->name,
                        'avatar_url' => $emp->avatar_path ? Storage::disk('public')->url($emp->avatar_path) : null,
                    ];
                }

                $acc = &$teamsMap[$tid]['_acc'];
                $acc['tasks_completed'] += $a['tasks']['completed'] ?? 0;
                $acc['tasks_on_time'] += $a['tasks']['completed_on_time'] ?? 0;
                $acc['tasks_open'] += $a['tasks']['open'] ?? 0;
                $acc['tasks_overdue_open'] += $a['tasks']['overdue_open'] ?? 0;
                $acc['today_active_seconds'] += $a['attendance']['today_active_seconds'] ?? 0;
                $acc['period_active_seconds'] += $a['attendance']['total_active_seconds'] ?? 0;
                $acc['tickets_resolved'] += $a['tickets']['resolved'] ?? 0;
                $acc['tickets_open'] += $a['tickets']['open'] ?? 0;
                $acc['score_sum'] += $a['productivity']['score'] ?? 0;
                $acc['streak_max'] = max($acc['streak_max'], $a['attendance']['streak_days'] ?? 0);
                if ($a['attendance']['today_checked_in'] ?? false) {
                    $acc['today_checked_in']++;
                }
                $acc['avg_completion_weighted'] += ($a['tasks']['avg_completion_seconds'] ?? 0) * ($a['tasks']['completed'] ?? 0);
                unset($acc);
            }
        }

        $teams = [];
        foreach ($teamsMap as $row) {
            $memberCount = max(1, count($row['members']));
            $acc = $row['_acc'];
            $teams[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'lead' => $row['lead'],
                'member_count' => count($row['members']),
                'today_checked_in' => $acc['today_checked_in'],
                'punctuality_today' => count($row['members']) > 0
                    ? round($acc['today_checked_in'] / count($row['members']), 3)
                    : null,
                'today_active_seconds' => $acc['today_active_seconds'],
                'period_active_seconds' => $acc['period_active_seconds'],
                'avg_active_per_member_today' => (int) round($acc['today_active_seconds'] / $memberCount),
                'tasks_completed' => $acc['tasks_completed'],
                'tasks_on_time' => $acc['tasks_on_time'],
                'tasks_open' => $acc['tasks_open'],
                'tasks_overdue_open' => $acc['tasks_overdue_open'],
                'on_time_ratio' => $acc['tasks_completed'] > 0
                    ? round($acc['tasks_on_time'] / $acc['tasks_completed'], 3)
                    : null,
                'tickets_resolved' => $acc['tickets_resolved'],
                'tickets_open' => $acc['tickets_open'],
                'avg_productivity_score' => (int) round($acc['score_sum'] / $memberCount),
                'streak_max' => $acc['streak_max'],
                'avg_completion_seconds' => $acc['tasks_completed'] > 0
                    ? (int) round($acc['avg_completion_weighted'] / $acc['tasks_completed'])
                    : 0,
                'members' => array_values((function ($m) {
                    usort($m, fn ($a, $b) => $b['score'] <=> $a['score']);

                    return $m;
                })($row['members'])),
                'range_days' => $rangeDays,
            ];
        }

        usort($teams, fn ($a, $b) => $b['avg_productivity_score'] <=> $a['avg_productivity_score']);

        return $teams;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{count:int, overdue:int}>
     */
    private function collectOpenTasks(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $doneColumnNames = ['تم', 'مكتمل', 'منجز', 'Done', 'Completed'];
        $doneColumnIds = Schema::hasTable('board_columns')
            ? BoardColumn::query()->whereIn('name', $doneColumnNames)->pluck('id')->all()
            : [];

        $query = Task::query()
            ->select('assignee_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('assignee_id', $userIds);

        if (Schema::hasColumn('tasks', 'archived_at')) {
            $query->whereNull('archived_at');
        }
        if (! empty($doneColumnIds)) {
            $query->whereNotIn('board_column_id', $doneColumnIds);
        }

        $counts = $query->groupBy('assignee_id')->get()->keyBy('assignee_id');

        $overdueQuery = Task::query()
            ->select('assignee_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('assignee_id', $userIds)
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now());

        if (Schema::hasColumn('tasks', 'archived_at')) {
            $overdueQuery->whereNull('archived_at');
        }
        if (! empty($doneColumnIds)) {
            $overdueQuery->whereNotIn('board_column_id', $doneColumnIds);
        }

        $overdue = $overdueQuery->groupBy('assignee_id')->get()->keyBy('assignee_id');

        $result = [];
        foreach ($userIds as $uid) {
            $result[$uid] = [
                'count' => (int) ($counts[$uid]->cnt ?? 0),
                'overdue' => (int) ($overdue[$uid]->cnt ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{count:int, avg_seconds:int, overdue:int, on_time:int}>
     */
    private function collectTaskCompletion(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('task_time_logs')) {
            return [];
        }

        $rows = TaskTimeLog::query()
            ->select([
                'user_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('AVG(duration_seconds) as avg_dur'),
                DB::raw('SUM(CASE WHEN was_overdue = 1 THEN 1 ELSE 0 END) as overdue_cnt'),
            ])
            ->whereIn('user_id', $userIds)
            ->whereBetween('completed_at', [$from, $to])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $result = [];
        foreach ($userIds as $uid) {
            $row = $rows[$uid] ?? null;
            $count = (int) ($row?->cnt ?? 0);
            $overdue = (int) ($row?->overdue_cnt ?? 0);
            $result[$uid] = [
                'count' => $count,
                'avg_seconds' => (int) ($row?->avg_dur ?? 0),
                'overdue' => $overdue,
                'on_time' => max(0, $count - $overdue),
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function collectAttendance(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('employee_attendances')) {
            return [];
        }

        $today = CarbonImmutable::now()->toDateString();

        $rangeRows = EmployeeAttendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $byUser = [];
        foreach ($rangeRows as $row) {
            $dateKey = $row->work_date?->toDateString() ?? (string) $row->work_date;
            $byUser[(int) $row->user_id][$dateKey] = $row;
        }

        $result = [];
        foreach ($userIds as $uid) {
            $items = collect($byUser[$uid] ?? []);
            $totalSeconds = (int) $items->sum('active_seconds');
            $daysPresent = $items
                ->filter(fn ($r) => $r->checked_in_at !== null && $r->status === 'present')
                ->count();
            $avg = $daysPresent > 0 ? (int) round($totalSeconds / $daysPresent) : 0;
            $today_attendance = $byUser[$uid][$today] ?? null;

            $last_seven = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = CarbonImmutable::now()->subDays($i)->toDateString();
                $row = $byUser[$uid][$date] ?? null;
                $last_seven[] = [
                    'date' => $date,
                    'active_seconds' => (int) ($row?->active_seconds ?? 0),
                    'status' => $row && $row->checked_in_at !== null ? ($row->status ?? 'present') : 'absent',
                ];
            }

            $lastEntry = $items->sortKeysDesc()->first();

            $recentNotes = [];
            foreach ($items->sortKeysDesc()->take(5) as $r) {
                if (! $r) {
                    continue;
                }
                $body = trim((string) ($r->note ?? ''));
                if ($body === '') {
                    continue;
                }
                $recentNotes[] = [
                    'date' => $r->work_date?->toDateString() ?? (string) $r->work_date,
                    'mood' => $r->mood,
                    'note' => mb_substr($body, 0, 320),
                ];
            }

            $result[$uid] = [
                'days_present' => $daysPresent,
                'total_active_seconds' => $totalSeconds,
                'avg_active_seconds' => $avg,
                'last_active_seconds' => (int) ($lastEntry?->active_seconds ?? 0),
                'today_active_seconds' => (int) ($today_attendance?->active_seconds ?? 0),
                'today_checked_in' => $today_attendance?->checked_in_at !== null,
                'today_mood' => $today_attendance?->mood,
                'today_note' => $today_attendance?->note,
                'today_plan' => $today_attendance?->plan_for_today,
                'last_check_in_at' => $lastEntry?->checked_in_at?->toIso8601String(),
                'last_seven_days' => $last_seven,
                'rows_by_date' => $byUser[$uid] ?? [],
                'recent_notes' => $recentNotes,
            ];
        }

        return $result;
    }

    /**
     * جلسات نشاط اليوم لكل موظف (لرؤية إدارية شفّافة لما يُحسب فعلاً من وقت الدوام).
     *
     * @param  list<int>  $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function collectTodaySessions(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('user_activity_sessions')) {
            return [];
        }

        $today = CarbonImmutable::now()->toDateString();
        $rows = UserActivitySession::query()
            ->whereIn('user_id', $userIds)
            ->where('work_date', $today)
            ->orderBy('started_at')
            ->get();

        $byUser = [];
        foreach ($rows as $r) {
            $byUser[(int) $r->user_id][] = [
                'started_at' => $r->started_at?->toIso8601String(),
                'last_heartbeat_at' => $r->last_heartbeat_at?->toIso8601String(),
                'ended_at' => $r->ended_at?->toIso8601String(),
                'active_seconds' => (int) $r->active_seconds,
                'is_open' => (bool) $r->is_open,
            ];
        }

        return $byUser;
    }

    /**
     * متوسط زمن أول رد للموظف على التذاكر المُسندة إليه في الفترة.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{avg_seconds:int, count:int}>
     */
    private function collectFirstResponse(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('support_tickets')) {
            return [];
        }

        // (first_response_at - created_at) بالثواني — صياغة محايدة لقواعد البيانات.
        $secondsExpr = DB::getDriverName() === 'sqlite'
            ? '(strftime("%s", first_response_at) - strftime("%s", created_at))'
            : 'TIMESTAMPDIFF(SECOND, created_at, first_response_at)';

        $rows = SupportTicket::query()
            ->select([
                'assignee_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw("AVG({$secondsExpr}) as avg_dur"),
            ])
            ->whereIn('assignee_id', $userIds)
            ->whereNotNull('first_response_at')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        $result = [];
        foreach ($userIds as $uid) {
            $row = $rows[$uid] ?? null;
            $result[$uid] = [
                'count' => (int) ($row?->cnt ?? 0),
                'avg_seconds' => max(0, (int) ($row?->avg_dur ?? 0)),
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{resolved:int, open:int, avg_seconds:int}>
     */
    private function collectTickets(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('support_tickets')) {
            return [];
        }

        $resolved = SupportTicket::query()
            ->select([
                'resolved_by_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('AVG(resolution_seconds) as avg_dur'),
            ])
            ->whereIn('resolved_by_id', $userIds)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->groupBy('resolved_by_id')
            ->get()
            ->keyBy('resolved_by_id');

        $open = SupportTicket::query()
            ->select(['assignee_id', DB::raw('COUNT(*) as cnt')])
            ->whereIn('assignee_id', $userIds)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        $result = [];
        foreach ($userIds as $uid) {
            $result[$uid] = [
                'resolved' => (int) ($resolved[$uid]->cnt ?? 0),
                'open' => (int) ($open[$uid]->cnt ?? 0),
                'avg_seconds' => (int) ($resolved[$uid]->avg_dur ?? 0),
            ];
        }

        return $result;
    }

    /**
     * عدد الأيام المتتالية حتى الآن التي حضر فيها الموظف. يسمح بتخطّي اليوم الحالي
     * إذا لم يُسجَّل فيه حضور بعد، لكنه يكسر السلسلة عند أي يوم سابق فائت.
     *
     * @param  array<string, EmployeeAttendance>  $rowsByDate
     */
    private function computeStreak(array $rowsByDate): int
    {
        $streak = 0;
        $today = CarbonImmutable::now();

        for ($i = 0; $i < 90; $i++) {
            $date = $today->subDays($i)->toDateString();
            $row = $rowsByDate[$date] ?? null;
            $present = $row && $row->checked_in_at !== null;

            if ($present) {
                $streak++;

                continue;
            }
            if ($i === 0) {
                // اليوم الحالي بدون حضور — نستمر لقياس السلسلة المنتهية أمس.
                continue;
            }
            break;
        }

        return $streak;
    }

    /**
     * @param  array<string, EmployeeAttendance>  $rowsByDate
     */
    private function daysSinceLastActivity(array $rowsByDate): ?int
    {
        $today = CarbonImmutable::now();
        for ($i = 0; $i < 30; $i++) {
            $date = $today->subDays($i)->toDateString();
            $row = $rowsByDate[$date] ?? null;
            if ($row && ((int) $row->active_seconds > 60 || $row->checked_in_at !== null)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * فرق نسبي بالمئة بين قيمتين. ترجع null إذا لم يمكن المقارنة (لا قيمة سابقة وقيمة حالية صفرية).
     */
    private function percentDelta(float|int $current, float|int $previous): ?float
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous <= 0 && $current <= 0) {
            return null;
        }
        if ($previous <= 0) {
            return 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

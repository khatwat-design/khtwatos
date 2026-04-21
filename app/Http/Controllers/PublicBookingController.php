<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingController extends Controller
{
    public function index(): Response
    {
        $teams = Team::query()
            ->with(['users' => fn ($q) => $q->where('is_bookable', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        $bookableUsers = $teams
            ->flatMap(fn (Team $team) => $team->users)
            ->unique('id')
            ->values();

        $from = now()->startOfDay();
        $to = now()->addDays(21)->endOfDay();
        $busyByUser = Meeting::query()
            ->whereIn('user_id', $bookableUsers->pluck('id'))
            ->where('status', 'scheduled')
            ->where('start_at', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->where('end_at', '>=', $from)
                    ->orWhereNull('end_at');
            })
            ->get()
            ->groupBy('user_id');

        return Inertia::render('Public/Book', [
            'booked' => request()->boolean('booked'),
            'teams' => $teams->map(fn (Team $team) => [
                'name' => $team->name,
                'slug' => $team->slug,
                'members' => $team->users
                    ->values()
                    ->map(fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'availability' => [
                            'schedule' => $this->normalizedSchedule($user),
                        ],
                        'slots' => $this->buildAvailableSlots(
                            $user,
                            $busyByUser[$user->id] ?? collect(),
                        ),
                    ]),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'project_name' => ['required', 'string', 'max:255'],
            'invitee_name' => ['required', 'string', 'max:255'],
            'invitee_email' => ['nullable', 'email', 'max:255'],
            'start_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $host = User::query()
            ->whereKey($data['user_id'])
            ->where('is_bookable', true)
            ->firstOrFail();

        $start = Carbon::parse($data['start_at']);
        $end = (clone $start)->addHour();

        if (! $this->isWithinAvailability($host, $start, $end)) {
            throw ValidationException::withMessages([
                'start_at' => 'الموعد خارج أوقات توفر الموظف.',
            ]);
        }

        $hasConflict = Meeting::query()
            ->where('user_id', $host->id)
            ->where('status', 'scheduled')
            ->where('start_at', '<', $end)
            ->where(function ($q) use ($start) {
                $q->where('end_at', '>', $start)
                    ->orWhereNull('end_at');
            })
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'start_at' => 'هذا الوقت محجوز بالفعل، اختر وقتاً آخر.',
            ]);
        }

        // If the invitee name matches an existing client, link the meeting to that client.
        $matchedClient = Client::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['invitee_name']))])
            ->first();

        Meeting::query()->create([
            'source' => 'internal',
            'external_id' => null,
            'title' => $data['project_name'],
            'start_at' => $start,
            'end_at' => $end,
            'invitee_name' => $data['invitee_name'],
            'invitee_email' => $data['invitee_email'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'scheduled',
            'user_id' => $host->id,
            'client_id' => $matchedClient?->id,
            'raw_payload' => null,
        ]);

        return redirect()->route('book.index', ['booked' => 1]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Meeting> $busyMeetings
     * @return array<int, array<string, string>>
     */
    private function buildAvailableSlots(User $user, \Illuminate\Support\Collection $busyMeetings): array
    {
        $slots = [];

        for ($offset = 0; $offset <= 21; $offset++) {
            $date = now()->addDays($offset);
            $window = $this->dayWindow($user, (int) $date->dayOfWeek);
            if (!$window) {
                continue;
            }

            $cursor = Carbon::parse($date->format('Y-m-d') . ' ' . $window['start']);
            $endOfDay = Carbon::parse($date->format('Y-m-d') . ' ' . $window['end']);

            while ($cursor->copy()->addHour()->lte($endOfDay)) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addHour();
                $cursor->addHour();

                if ($slotStart->lt(now()->addMinutes(15))) {
                    continue;
                }

                $conflict = $busyMeetings->first(function (Meeting $meeting) use ($slotStart, $slotEnd) {
                    $meetingEnd = $meeting->end_at ? $meeting->end_at->copy() : $meeting->start_at->copy()->addHour();
                    return $meeting->start_at->lt($slotEnd) && $meetingEnd->gt($slotStart);
                });

                if ($conflict) {
                    continue;
                }

                $slots[] = [
                    'value' => $slotStart->toIso8601String(),
                    'label' => $slotStart->locale('ar')->translatedFormat('D j M - h:i A'),
                ];
            }

            if (count($slots) >= 30) {
                break;
            }
        }

        return $slots;
    }

    private function isWithinAvailability(User $user, Carbon $start, Carbon $end): bool
    {
        $window = $this->dayWindow($user, (int) $start->dayOfWeek);
        if (!$window) {
            return false;
        }

        $windowStart = Carbon::parse($start->format('Y-m-d') . ' ' . $window['start']);
        $windowEnd = Carbon::parse($start->format('Y-m-d') . ' ' . $window['end']);

        return $start->gte($windowStart) && $end->lte($windowEnd);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedSchedule(User $user): array
    {
        $raw = is_array($user->availability_schedule) ? $user->availability_schedule : [];
        $days = $user->availability_days ?: [0, 1, 2, 3, 4];
        $defaultStart = $user->availability_start_time ? substr($user->availability_start_time, 0, 5) : '09:00';
        $defaultEnd = $user->availability_end_time ? substr($user->availability_end_time, 0, 5) : '17:00';
        $schedule = [];

        for ($day = 0; $day <= 6; $day++) {
            $entry = Arr::get($raw, (string) $day, []);
            $enabled = array_key_exists('enabled', $entry)
                ? (bool) $entry['enabled']
                : in_array($day, $days, true);

            $schedule[] = [
                'day' => $day,
                'enabled' => $enabled,
                'start' => $enabled ? substr((string) ($entry['start'] ?? $defaultStart), 0, 5) : null,
                'end' => $enabled ? substr((string) ($entry['end'] ?? $defaultEnd), 0, 5) : null,
            ];
        }

        return $schedule;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private function dayWindow(User $user, int $day): ?array
    {
        $schedule = collect($this->normalizedSchedule($user));
        $entry = $schedule->first(fn (array $row) => (int) $row['day'] === $day && (bool) $row['enabled']);
        if (!$entry) {
            return null;
        }

        $start = $entry['start'] ?? null;
        $end = $entry['end'] ?? null;
        if (!$start || !$end || $start >= $end) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }
}

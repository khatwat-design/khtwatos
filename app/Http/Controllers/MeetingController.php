<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Services\ClientWorkflowAutomationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(private readonly ClientWorkflowAutomationService $workflowAutomation)
    {
    }

    public function index(Request $request): Response
    {
        $query = Meeting::query()
            ->with([
                'host:id,name,role',
                'client:id,name',
                'participants:id,name,role',
            ])
            ->orderByDesc('start_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        $status = $request->query('status');
        if (in_array($status, ['scheduled', 'completed', 'canceled'], true)) {
            $query->where('status', $status);
        }

        $scope = $request->query('scope');
        if ($scope === 'internal') {
            $query->whereNull('client_id');
        } elseif ($scope === 'client') {
            $query->whereNotNull('client_id');
        }

        return Inertia::render('Meetings/Index', [
            'hosts' => User::orderBy('name')->get(['id', 'name', 'role']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'meetings' => $query->limit(200)->get()->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'start_at' => $m->start_at->toIso8601String(),
                'end_at' => $m->end_at?->toIso8601String(),
                'invitee_name' => $m->invitee_name,
                'invitee_email' => $m->invitee_email,
                'reason' => $m->reason,
                'summary' => $m->summary,
                'completed_at' => $m->completed_at?->toIso8601String(),
                'status' => $m->status,
                'source' => $m->source,
                'host' => $m->host ? [
                    'id' => $m->host->id,
                    'name' => $m->host->name,
                    'role' => $m->host->role,
                    'is_team_manager' => $m->host->role === 'lead',
                ] : null,
                'participants' => $m->participants->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role,
                ])->values(),
                'client' => $m->client ? ['id' => $m->client->id, 'name' => $m->client->name] : null,
            ]),
            'filters' => [
                'user_id' => $request->filled('user_id') ? (int) $request->query('user_id') : null,
                'client_id' => $request->filled('client_id') ? (int) $request->query('client_id') : null,
                'status' => in_array($status, ['scheduled', 'completed', 'canceled'], true) ? $status : null,
                'scope' => in_array($scope, ['internal', 'client'], true) ? $scope : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Meetings/Create', [
            'hosts' => User::orderBy('name')->get(['id', 'name', 'role']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'defaults' => [
                'user_id' => $request->user()->id,
                'client_id' => $request->filled('client_id') ? (int) $request->query('client_id') : null,
                'team_ids' => [],
                'participant_ids' => [],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'team_ids' => $this->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->normalizeIds($request->input('participant_ids', [])),
        ]);

        $data = $this->validatedMeeting($request);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $start = Carbon::parse($data['start_at']);
        $end = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : (clone $start)->addHour();

        $meeting = Meeting::query()->create([
            'source' => 'internal',
            'external_id' => null,
            'title' => $data['title'],
            'start_at' => $start,
            'end_at' => $end,
            'invitee_name' => $data['invitee_name'] ?? $client?->name,
            'invitee_email' => $data['invitee_email'] ?? $client?->email,
            'reason' => $data['reason'] ?? null,
            'status' => 'scheduled',
            'user_id' => $data['user_id'],
            'client_id' => $data['client_id'] ?? null,
            'raw_payload' => null,
        ]);

        $this->syncParticipants($meeting, $data);

        return redirect()->route('meetings.index', array_filter([
            'client_id' => $data['client_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function edit(Meeting $meeting): Response
    {
        $this->ensureInternal($meeting);

        $meeting->load('participants:id,name');

        return Inertia::render('Meetings/Edit', [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'start_at' => $meeting->start_at->format('Y-m-d\TH:i'),
                'end_at' => $meeting->end_at?->format('Y-m-d\TH:i'),
                'reason' => $meeting->reason,
                'summary' => $meeting->summary,
                'invitee_name' => $meeting->invitee_name,
                'invitee_email' => $meeting->invitee_email,
                'user_id' => $meeting->user_id,
                'client_id' => $meeting->client_id,
                'status' => $meeting->status,
                'participant_ids' => $meeting->participants->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'team_ids' => [],
            ],
            'hosts' => User::orderBy('name')->get(['id', 'name', 'role']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureInternal($meeting);
        $previousStatus = $meeting->status;

        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'team_ids' => $this->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->normalizeIds($request->input('participant_ids', [])),
        ]);

        $data = $this->validatedMeeting($request, isUpdate: true);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $start = Carbon::parse($data['start_at']);
        $end = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : (clone $start)->addHour();

        $meeting->update([
            'title' => $data['title'],
            'start_at' => $start,
            'end_at' => $end,
            'invitee_name' => $data['invitee_name'] ?? $client?->name,
            'invitee_email' => $data['invitee_email'] ?? $client?->email,
            'reason' => $data['reason'] ?? null,
            'summary' => $data['summary'] ?? $meeting->summary,
            'completed_at' => ($data['status'] ?? $meeting->status) === 'completed'
                ? ($meeting->completed_at ?? now())
                : null,
            'status' => $data['status'] ?? $meeting->status,
            'user_id' => $data['user_id'],
            'client_id' => $data['client_id'] ?? null,
        ]);

        $this->syncParticipants($meeting, $data);

        $newStatus = $data['status'] ?? $meeting->status;
        if ($previousStatus !== 'completed' && $newStatus === 'completed') {
            $this->workflowAutomation->handleMeetingCompleted($meeting->fresh(), $request->user()?->id);
        }

        return redirect()->route('meetings.index');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->ensureInternal($meeting);
        $meeting->delete();

        return redirect()->route('meetings.index');
    }

    public function complete(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureInternal($meeting);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
        ]);

        $meeting->update([
            'status' => 'completed',
            'summary' => trim($data['summary']),
            'completed_at' => now(),
        ]);

        $this->workflowAutomation->handleMeetingCompleted($meeting->fresh(), $request->user()?->id);

        return redirect()->route('meetings.index');
    }

    private function ensureInternal(Meeting $meeting): void
    {
        if ($meeting->source !== 'internal') {
            abort(403, 'Cannot modify external meetings.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMeeting(Request $request, bool $isUpdate = false): array
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'invitee_email' => $request->filled('invitee_email') ? $request->input('invitee_email') : null,
            'invitee_name' => $request->filled('invitee_name') ? $request->input('invitee_name') : null,
            'end_at' => $request->filled('end_at') ? $request->input('end_at') : null,
            'summary' => $request->filled('summary') ? $request->input('summary') : null,
            'team_ids' => $this->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->normalizeIds($request->input('participant_ids', [])),
        ]);

        $rules = [
            'user_id' => ['required', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'invitee_name' => ['nullable', 'string', 'max:255'],
            'invitee_email' => ['nullable', 'email', 'max:255'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];

        if ($isUpdate) {
            $rules['status'] = ['required', 'in:scheduled,canceled,completed'];
        }

        return $request->validate($rules);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function syncParticipants(Meeting $meeting, array $data): void
    {
        $participantIds = collect($data['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id);

        $teamIds = collect($data['team_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
        if ($teamIds->isNotEmpty()) {
            $teamUserIds = User::query()
                ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
            $participantIds = $participantIds->merge($teamUserIds);
        }

        if (isset($data['user_id'])) {
            $participantIds->push((int) $data['user_id']);
        }

        $meeting->participants()->sync(
            $participantIds
                ->unique()
                ->values()
                ->all()
        );
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}

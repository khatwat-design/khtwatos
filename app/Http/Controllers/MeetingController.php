<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Operational\Meetings\MeetingApplicationService;
use App\Services\ClientWorkflowAutomationService;
use App\Services\OperationalAuthorization;
use App\Services\SmartNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(
        private readonly ClientWorkflowAutomationService $workflowAutomation,
        private readonly SmartNotificationService $smartNotifications,
        private readonly OperationalAuthorization $opsAuth,
        private readonly MeetingApplicationService $meetings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $this->meetings->archiveCompletedMeetings();
        $hasArchiveColumn = Schema::hasColumn('meetings', 'archived_at');
        $includeArchived = $request->boolean('include_archived');

        $query = Meeting::query()
            ->with([
                'host:id,name,role',
                'client:id,name',
                'participants:id,name,role',
            ])
            ->orderByDesc('start_at');

        $this->meetings->applyVisibilityAndFilters($query, $request, $user, $hasArchiveColumn, $includeArchived);

        $status = $request->query('status');
        $scope = $request->query('scope');

        return Inertia::render('Meetings/Index', [
            'hosts' => $this->meetings->hostsForPicker($user),
            'clients' => $this->meetings->clientsForPicker($user),
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
                'archived_at' => $m->archived_at?->toIso8601String(),
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
                'status' => in_array($status, ['scheduled', 'completed', 'canceled', 'postponed'], true) ? $status : null,
                'scope' => in_array($scope, ['internal', 'client'], true) ? $scope : null,
                'include_archived' => $includeArchived,
            ],
            'stats' => $this->meetings->listStats($request, $hasArchiveColumn, $user),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $defaultClientId = null;
        if ($request->filled('client_id')) {
            $cid = (int) $request->query('client_id');
            if ($this->opsAuth->canUseMeetingClientFilter($user, $cid)) {
                $defaultClientId = $cid;
            }
        }

        return Inertia::render('Meetings/Create', [
            'hosts' => $this->meetings->hostsForPicker($user),
            'clients' => $this->meetings->clientsForPicker($user),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'defaults' => [
                'user_id' => $user->id,
                'client_id' => $defaultClientId,
                'team_ids' => [],
                'participant_ids' => [],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'team_ids' => $this->meetings->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->meetings->normalizeIds($request->input('participant_ids', [])),
        ]);

        $data = $this->validatedMeeting($request);

        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canAssignMeetingHost($user, (int) $data['user_id']), 403);
        if (! empty($data['client_id'])) {
            $clientForAuth = Client::query()->find((int) $data['client_id']);
            abort_unless($clientForAuth && $this->opsAuth->canViewClient($user, $clientForAuth), 403);
        }

        $this->opsAuth->ensureMeetingTeamIdsAllowed($user, $data['team_ids'] ?? []);
        $this->opsAuth->ensureMeetingParticipantIdsAllowed($user, $data['participant_ids'] ?? []);

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

        $this->meetings->syncParticipants($meeting, $data);
        $this->smartNotifications->notifyMeetingCreated($meeting->fresh('participants:id,name'), $request->user()?->id);

        return redirect()->route('meetings.index', array_filter([
            'client_id' => $data['client_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function edit(Request $request, Meeting $meeting): Response
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

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
            'hosts' => $this->meetings->hostsForPicker($user),
            'clients' => $this->meetings->clientsForPicker($user),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

        $this->ensureInternal($meeting);
        $previousStatus = $meeting->status;

        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'team_ids' => $this->meetings->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->meetings->normalizeIds($request->input('participant_ids', [])),
        ]);

        $data = $this->validatedMeeting($request, isUpdate: true);

        abort_unless($this->opsAuth->canAssignMeetingHost($user, (int) $data['user_id']), 403);
        if (! empty($data['client_id'])) {
            $clientForAuth = Client::query()->find((int) $data['client_id']);
            abort_unless($clientForAuth && $this->opsAuth->canViewClient($user, $clientForAuth), 403);
        }

        $this->opsAuth->ensureMeetingTeamIdsAllowed($user, $data['team_ids'] ?? []);
        $this->opsAuth->ensureMeetingParticipantIdsAllowed($user, $data['participant_ids'] ?? []);

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

        $this->meetings->syncParticipants($meeting, $data);

        $newStatus = $data['status'] ?? $meeting->status;
        if ($previousStatus !== 'completed' && $newStatus === 'completed') {
            $this->workflowAutomation->handleMeetingCompleted($meeting->fresh(), $request->user()?->id);
            $this->smartNotifications->notifyMeetingCompleted($meeting->fresh('participants:id,name'), $request->user()?->id);
        }

        return redirect()->route('meetings.index');
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

        $this->ensureInternal($meeting);
        $meeting->delete();

        return redirect()->route('meetings.index');
    }

    public function complete(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

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
        $this->smartNotifications->notifyMeetingCompleted($meeting->fresh('participants:id,name'), $request->user()?->id);

        return redirect()->route('meetings.index');
    }

    public function postpone(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

        $this->ensureInternal($meeting);

        $meeting->update([
            'status' => 'postponed',
            'completed_at' => null,
        ]);

        return redirect()->route('meetings.index');
    }

    public function archive(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

        $this->ensureInternal($meeting);
        if (! Schema::hasColumn('meetings', 'archived_at')) {
            return redirect()->route('meetings.index');
        }
        if (! in_array($meeting->status, ['completed', 'canceled'], true)) {
            return redirect()->route('meetings.index');
        }

        $meeting->update([
            'archived_at' => now(),
            'archived_by_id' => $request->user()?->id,
            'archived_reason' => 'manual',
        ]);

        return redirect()->route('meetings.index');
    }

    public function restoreArchive(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->opsAuth->canViewMeeting($user, $meeting), 403);

        if (! Schema::hasColumn('meetings', 'archived_at')) {
            return redirect()->route('meetings.index', ['include_archived' => 1]);
        }

        $meeting->update([
            'archived_at' => null,
            'archived_by_id' => null,
            'archived_reason' => null,
        ]);

        return redirect()->route('meetings.index', ['include_archived' => 1]);
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
            'team_ids' => $this->meetings->normalizeIds($request->input('team_ids', [])),
            'participant_ids' => $this->meetings->normalizeIds($request->input('participant_ids', [])),
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
            $rules['status'] = ['required', 'in:scheduled,canceled,completed,postponed'];
        }

        return $request->validate($rules);
    }
}

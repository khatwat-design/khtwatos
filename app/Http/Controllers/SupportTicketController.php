<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\SmartNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SmartNotificationService $smartNotifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $canTriage = $this->canTriage($user);
        $filterStatus = $request->query('status');
        $filterPriority = $request->query('priority');
        $filterCategory = $request->query('category');
        $filterAssigneeRaw = $request->query('assignee_id');
        $filterAssignee = $filterAssigneeRaw === null || $filterAssigneeRaw === '' ? null : (string) $filterAssigneeRaw;
        $filterClientRaw = $request->query('client_id');
        $filterClient = $filterClientRaw === null || $filterClientRaw === '' ? null : (int) $filterClientRaw;
        $filterMine = $request->boolean('mine');
        $filterQuery = trim((string) $request->query('q', ''));

        $query = SupportTicket::query()
            ->with([
                'reporter:id,name,avatar_path',
                'assignee:id,name,avatar_path',
                'resolver:id,name',
                'client:id,name',
            ])
            ->withCount('messages');

        if (! $canTriage) {
            $query->where(function ($q) use ($user) {
                $q->where('reporter_id', $user->id)->orWhere('assignee_id', $user->id);
            });
        } elseif ($filterMine) {
            $query->where('assignee_id', $user->id);
        }

        if ($filterStatus && in_array($filterStatus, SupportTicket::STATUSES, true)) {
            $query->where('status', $filterStatus);
        }
        if ($filterPriority && in_array($filterPriority, SupportTicket::PRIORITIES, true)) {
            $query->where('priority', $filterPriority);
        }
        if ($filterCategory && in_array($filterCategory, SupportTicket::CATEGORIES, true)) {
            $query->where('category', $filterCategory);
        }
        if ($canTriage && $filterAssignee !== null) {
            if ($filterAssignee === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->where('assignee_id', (int) $filterAssignee);
            }
        }
        if ($filterClient !== null) {
            $query->where('client_id', $filterClient);
        }
        if ($filterQuery !== '') {
            $needle = '%'.$filterQuery.'%';
            $query->where(function ($q) use ($needle) {
                $q->where('title', 'like', $needle)
                    ->orWhere('body', 'like', $needle)
                    ->orWhere('reference', 'like', $needle);
            });
        }

        $tickets = $query
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'waiting' THEN 3 WHEN 'resolved' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $stats = $this->buildStats($user, $canTriage);

        $teamUsers = User::query()->whereIn('role', ['admin', 'lead', 'member'])->orderBy('name')->get(['id', 'name']);
        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets->map(fn (SupportTicket $t) => $this->presentTicket($t))->values(),
            'stats' => $stats,
            'canTriage' => $canTriage,
            'filters' => [
                'status' => $filterStatus,
                'priority' => $filterPriority,
                'category' => $filterCategory,
                'assignee_id' => $filterAssignee,
                'client_id' => $filterClient,
                'mine' => $filterMine,
                'q' => $filterQuery !== '' ? $filterQuery : null,
            ],
            'meta' => [
                'statuses' => SupportTicket::STATUSES,
                'priorities' => SupportTicket::PRIORITIES,
                'categories' => SupportTicket::CATEGORIES,
            ],
            'team_users' => $teamUsers,
            'clients' => $clients,
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $canTriage = $this->canTriage($user);
        if (! $canTriage && (int) $ticket->reporter_id !== (int) $user->id && (int) $ticket->assignee_id !== (int) $user->id) {
            abort(403);
        }

        $ticket->load([
            'reporter:id,name,avatar_path',
            'assignee:id,name,avatar_path',
            'resolver:id,name',
            'client:id,name',
            'messages.user:id,name,avatar_path',
        ]);

        return Inertia::render('Tickets/Show', [
            'ticket' => $this->presentTicketFull($ticket, $canTriage),
            'canTriage' => $canTriage,
            'team_users' => User::query()->whereIn('role', ['admin', 'lead', 'member'])->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'meta' => [
                'statuses' => SupportTicket::STATUSES,
                'priorities' => SupportTicket::PRIORITIES,
                'categories' => SupportTicket::CATEGORIES,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['nullable', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'client_id' => ['nullable', 'exists:clients,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ]);

        $ticket = SupportTicket::query()->create([
            'reference' => SupportTicket::generateReference(),
            'reporter_id' => $user->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
            'client_id' => $data['client_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
        ]);

        if (! empty($data['assignee_id']) && (int) $data['assignee_id'] !== (int) $user->id) {
            $this->smartNotifications->notifyUsers([(int) $data['assignee_id']], [
                'title' => 'تم تعيينك على تذكرة دعم',
                'body' => $ticket->reference.' — '.$ticket->title,
                'severity' => $ticket->priority === 'critical' ? 'warning' : 'info',
                'category' => 'tickets',
                'link' => route('tickets.show', $ticket->id),
                'meta' => ['ticket_id' => $ticket->id],
            ], $user->id);
        }

        $adminIds = User::query()->where('role', 'admin')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->smartNotifications->notifyUsers($adminIds, [
            'title' => 'تذكرة دعم جديدة: '.$ticket->reference,
            'body' => $ticket->title,
            'severity' => $ticket->priority === 'critical' ? 'warning' : 'info',
            'category' => 'tickets',
            'link' => route('tickets.show', $ticket->id),
            'meta' => ['ticket_id' => $ticket->id, 'reference' => $ticket->reference],
        ], $user->id);

        return redirect()->route('tickets.show', $ticket->id);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($this->canTriage($user), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'category' => ['nullable', Rule::in(SupportTicket::CATEGORIES)],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
        ]);

        $previousAssigneeId = $ticket->assignee_id;

        DB::transaction(function () use ($ticket, $data, $user): void {
            $statusChangedToResolved = isset($data['status']) && $data['status'] === 'resolved' && $ticket->status !== 'resolved';
            $statusReopened = isset($data['status']) && $data['status'] !== 'resolved' && $ticket->status === 'resolved';

            $ticket->fill([
                'status' => $data['status'] ?? $ticket->status,
                'priority' => $data['priority'] ?? $ticket->priority,
                'category' => $data['category'] ?? $ticket->category,
                'assignee_id' => array_key_exists('assignee_id', $data) ? $data['assignee_id'] : $ticket->assignee_id,
                'client_id' => array_key_exists('client_id', $data) ? $data['client_id'] : $ticket->client_id,
            ]);

            if ($ticket->first_response_at === null && in_array($ticket->status, ['in_progress', 'waiting', 'resolved'], true)) {
                $ticket->first_response_at = Carbon::now();
            }

            if ($statusChangedToResolved) {
                $ticket->resolved_at = Carbon::now();
                $ticket->resolved_by_id = $user->id;
                $ticket->resolution_seconds = max(0, $ticket->created_at->diffInSeconds(Carbon::now()));
            } elseif ($statusReopened) {
                $ticket->resolved_at = null;
                $ticket->resolved_by_id = null;
                $ticket->resolution_seconds = null;
            }

            $ticket->save();
        });

        if (($ticket->reporter_id ?? null) && $ticket->reporter_id !== $user->id) {
            $this->smartNotifications->notifyUsers([(int) $ticket->reporter_id], [
                'title' => 'تحديث على تذكرتك: '.$ticket->reference,
                'body' => 'الحالة الحالية: '.$ticket->status,
                'severity' => 'info',
                'category' => 'tickets',
                'link' => route('tickets.show', $ticket->id),
                'meta' => ['ticket_id' => $ticket->id],
            ], $user->id);
        }

        if (
            array_key_exists('assignee_id', $data)
            && (int) ($data['assignee_id'] ?? 0) !== (int) ($previousAssigneeId ?? 0)
            && ! empty($data['assignee_id'])
            && (int) $data['assignee_id'] !== (int) $user->id
        ) {
            $this->smartNotifications->notifyUsers([(int) $data['assignee_id']], [
                'title' => 'تم إعادة تعيين تذكرة لك: '.$ticket->reference,
                'body' => $ticket->title,
                'severity' => $ticket->priority === 'critical' ? 'warning' : 'info',
                'category' => 'tickets',
                'link' => route('tickets.show', $ticket->id),
                'meta' => ['ticket_id' => $ticket->id],
            ], $user->id);
        }

        return back();
    }

    public function addMessage(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $canTriage = $this->canTriage($user);
        if (! $canTriage && (int) $ticket->reporter_id !== (int) $user->id && (int) $ticket->assignee_id !== (int) $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false) && $canTriage;

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => trim($data['body']),
            'is_internal' => $isInternal,
        ]);

        if ($ticket->first_response_at === null && $user->id !== $ticket->reporter_id) {
            $ticket->first_response_at = Carbon::now();
            $ticket->save();
        }

        $notifyIds = [];
        if ($user->id !== (int) $ticket->reporter_id && ! $isInternal) {
            $notifyIds[] = (int) $ticket->reporter_id;
        }
        if ($ticket->assignee_id && $ticket->assignee_id !== $user->id) {
            $notifyIds[] = (int) $ticket->assignee_id;
        }
        if (! empty($notifyIds)) {
            $this->smartNotifications->notifyUsers($notifyIds, [
                'title' => 'رسالة جديدة في تذكرة '.$ticket->reference,
                'body' => mb_substr($data['body'], 0, 120),
                'severity' => 'info',
                'category' => 'tickets',
                'link' => route('tickets.show', $ticket->id),
                'meta' => ['ticket_id' => $ticket->id],
            ], $user->id);
        }

        return back();
    }

    public function destroy(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->isAdmin(), 403);

        $ticket->delete();

        return redirect()->route('tickets.index');
    }

    private function canTriage(User $user): bool
    {
        return $user->isAdmin() || Gate::forUser($user)->allows('manage-employees');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTicket(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->category,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
            'first_response_at' => $t->first_response_at?->toIso8601String(),
            'resolved_at' => $t->resolved_at?->toIso8601String(),
            'resolution_seconds' => $t->resolution_seconds,
            'reporter' => $t->reporter ? ['id' => $t->reporter->id, 'name' => $t->reporter->name] : null,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'resolver' => $t->resolver ? ['id' => $t->resolver->id, 'name' => $t->resolver->name] : null,
            'client' => $t->client ? ['id' => $t->client->id, 'name' => $t->client->name] : null,
            'messages_count' => (int) ($t->messages_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTicketFull(SupportTicket $t, bool $canTriage): array
    {
        $messages = $t->messages
            ->reject(fn (SupportTicketMessage $m) => $m->is_internal && ! $canTriage)
            ->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_internal' => (bool) $m->is_internal,
                'created_at' => $m->created_at?->toIso8601String(),
                'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
            ])->values();

        return array_merge($this->presentTicket($t), [
            'body' => $t->body,
            'messages' => $messages,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(User $user, bool $canTriage): array
    {
        $scope = SupportTicket::query();
        if (! $canTriage) {
            $scope->where(function ($q) use ($user) {
                $q->where('reporter_id', $user->id)->orWhere('assignee_id', $user->id);
            });
        }

        return [
            'total' => (int) (clone $scope)->count(),
            'open' => (int) (clone $scope)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'in_progress' => (int) (clone $scope)->where('status', 'in_progress')->count(),
            'resolved' => (int) (clone $scope)->where('status', 'resolved')->count(),
            'critical' => (int) (clone $scope)->where('priority', 'critical')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'mine_open' => (int) SupportTicket::query()
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
        ];
    }
}

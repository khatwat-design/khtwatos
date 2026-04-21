<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Meeting::query()
            ->with(['host:id,name', 'client:id,name'])
            ->orderByDesc('start_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        return Inertia::render('Meetings/Index', [
            'hosts' => User::orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'meetings' => $query->limit(200)->get()->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'start_at' => $m->start_at->toIso8601String(),
                'end_at' => $m->end_at?->toIso8601String(),
                'invitee_name' => $m->invitee_name,
                'invitee_email' => $m->invitee_email,
                'reason' => $m->reason,
                'status' => $m->status,
                'source' => $m->source,
                'host' => $m->host ? ['id' => $m->host->id, 'name' => $m->host->name] : null,
                'client' => $m->client ? ['id' => $m->client->id, 'name' => $m->client->name] : null,
            ]),
            'filters' => [
                'user_id' => $request->filled('user_id') ? (int) $request->query('user_id') : null,
                'client_id' => $request->filled('client_id') ? (int) $request->query('client_id') : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Meetings/Create', [
            'hosts' => User::orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'defaults' => [
                'user_id' => $request->user()->id,
                'client_id' => $request->filled('client_id') ? (int) $request->query('client_id') : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
        ]);

        $data = $this->validatedMeeting($request);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $start = Carbon::parse($data['start_at']);
        $end = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : (clone $start)->addHour();

        Meeting::query()->create([
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

        return redirect()->route('meetings.index', array_filter([
            'client_id' => $data['client_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function edit(Meeting $meeting): Response
    {
        $this->ensureInternal($meeting);

        return Inertia::render('Meetings/Edit', [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'start_at' => $meeting->start_at->format('Y-m-d\TH:i'),
                'end_at' => $meeting->end_at?->format('Y-m-d\TH:i'),
                'reason' => $meeting->reason,
                'invitee_name' => $meeting->invitee_name,
                'invitee_email' => $meeting->invitee_email,
                'user_id' => $meeting->user_id,
                'client_id' => $meeting->client_id,
                'status' => $meeting->status,
            ],
            'hosts' => User::orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureInternal($meeting);

        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
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
            'status' => $data['status'] ?? $meeting->status,
            'user_id' => $data['user_id'],
            'client_id' => $data['client_id'] ?? null,
        ]);

        return redirect()->route('meetings.index');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->ensureInternal($meeting);
        $meeting->delete();

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
        ]);

        $rules = [
            'user_id' => ['required', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'invitee_name' => ['nullable', 'string', 'max:255'],
            'invitee_email' => ['nullable', 'email', 'max:255'],
        ];

        if ($isUpdate) {
            $rules['status'] = ['required', 'in:scheduled,canceled'];
        }

        return $request->validate($rules);
    }
}

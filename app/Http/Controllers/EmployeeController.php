<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $employees = User::query()
            ->with(['teams:id,name,slug'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_bookable' => (bool) $user->is_bookable,
                'availability_days' => $user->availability_days ?: [0, 1, 2, 3, 4],
                'availability_start_time' => $user->availability_start_time ? substr((string) $user->availability_start_time, 0, 5) : '09:00',
                'availability_end_time' => $user->availability_end_time ? substr((string) $user->availability_end_time, 0, 5) : '17:00',
                'teams' => $user->teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'is_lead' => (bool) ($team->pivot?->is_lead),
                    'allocation_percent' => $team->pivot?->allocation_percent,
                ])->values(),
            ])->values(),
            'teams' => Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedEmployee($request, isCreate: true);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_bookable' => (bool) ($data['is_bookable'] ?? false),
            'availability_days' => $data['availability_days'],
            'availability_start_time' => $data['availability_start_time'],
            'availability_end_time' => $data['availability_end_time'],
            'email_verified_at' => now(),
        ]);

        $user->teams()->sync($this->teamPivotSyncPayload($data['teams'] ?? []));

        return redirect()->route('employees.index');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $this->validatedEmployee($request, isCreate: false, userId: $employee->id);

        $employee->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_bookable' => (bool) ($data['is_bookable'] ?? false),
            'availability_days' => $data['availability_days'],
            'availability_start_time' => $data['availability_start_time'],
            'availability_end_time' => $data['availability_end_time'],
        ]);

        if (!empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }

        $employee->save();
        $employee->teams()->sync($this->teamPivotSyncPayload($data['teams'] ?? []));

        return redirect()->route('employees.index');
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
        $emailRule = ['required', 'email', 'max:255', 'unique:users,email'];
        if ($userId) {
            $emailRule = ['required', 'email', 'max:255', 'unique:users,email,'.$userId];
        }

        $passwordRule = $isCreate
            ? ['required', 'string', 'min:8']
            : ['nullable', 'string', 'min:8'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRule,
            'password' => $passwordRule,
            'role' => ['required', 'in:admin,lead,member'],
            'is_bookable' => ['nullable', 'boolean'],
            'availability_days' => ['required', 'array', 'min:1'],
            'availability_days.*' => ['integer', 'between:0,6'],
            'availability_start_time' => ['required', 'date_format:H:i'],
            'availability_end_time' => ['required', 'date_format:H:i', 'after:availability_start_time'],
            'teams' => ['nullable', 'array'],
            'teams.*.id' => ['required', 'exists:teams,id'],
            'teams.*.allocation_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'teams.*.is_lead' => ['nullable', 'boolean'],
        ]);

        $data['availability_days'] = collect($data['availability_days'])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $teams
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
}

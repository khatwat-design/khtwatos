<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDialCodeSaveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{day: int, enabled: bool, start: ?string, end: ?string}>
     */
    private function sampleAvailabilitySchedule(): array
    {
        $rows = [];
        for ($day = 0; $day <= 6; $day++) {
            $rows[] = [
                'day' => $day,
                'enabled' => $day < 5,
                'start' => '09:00',
                'end' => '17:00',
            ];
        }

        return $rows;
    }

    public function test_can_store_employee_with_saudi_dial_code_as_integer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'name' => 'موظف سعودي',
            'username' => 'sauser01',
            'password' => 'secretpass12',
            'role' => 'member',
            'is_bookable' => true,
            'phone_country_code' => 966,
            'phone_local' => '501234567',
            'availability_schedule' => $this->sampleAvailabilitySchedule(),
            'teams' => [],
        ];

        $response = $this->actingAs($admin)->post(route('employees.store'), $payload);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'sauser01',
            'phone' => '966501234567',
        ]);
    }

    public function test_can_store_employee_with_egypt_dial_code_as_integer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'name' => 'موظف مصري',
            'username' => 'eguser01',
            'password' => 'secretpass12',
            'role' => 'member',
            'is_bookable' => true,
            'phone_country_code' => 20,
            'phone_local' => '1001234567',
            'availability_schedule' => $this->sampleAvailabilitySchedule(),
            'teams' => [],
        ];

        $response = $this->actingAs($admin)->post(route('employees.store'), $payload);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'eguser01',
            'phone' => '201001234567',
        ]);
    }

    public function test_empty_team_allocation_percent_does_not_fail_integer_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $team = Team::query()->create([
            'name' => 'فريق تجريبي',
            'slug' => 'tmp-test-team',
            'sort_order' => 900,
        ]);

        $payload = [
            'name' => 'موظف بفريق',
            'username' => 'teamuser01',
            'password' => 'secretpass12',
            'role' => 'member',
            'is_bookable' => true,
            'phone_country_code' => 966,
            'phone_local' => '502222222',
            'availability_schedule' => $this->sampleAvailabilitySchedule(),
            'teams' => [
                [
                    'id' => $team->id,
                    'allocation_percent' => '',
                    'is_lead' => false,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('employees.store'), $payload);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.index'));
    }
}

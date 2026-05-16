<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamChatMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamChatMemberServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamChatMemberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TeamChatMemberService::class);
    }

    public function test_khatwat_room_defaults_to_all_users_when_no_explicit_members(): void
    {
        $khatwat = Team::query()->create(['name' => 'خطوات', 'slug' => 'khatwat', 'sort_order' => 5]);
        $writing = Team::query()->create(['name' => 'كتابة', 'slug' => 'writing', 'sort_order' => 10]);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $writing->users()->attach($alice->id);

        $memberIds = $this->service->memberIdsForTeam((int) $khatwat->id);

        $this->assertContains($alice->id, $memberIds);
        $this->assertContains($bob->id, $memberIds);
    }

    public function test_department_room_defaults_to_team_pivot_when_no_explicit_members(): void
    {
        $writing = Team::query()->create(['name' => 'كتابة', 'slug' => 'writing', 'sort_order' => 10]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $writing->users()->attach($alice->id);

        $memberIds = $this->service->memberIdsForTeam((int) $writing->id);

        $this->assertSame([$alice->id], $memberIds);
        $this->assertNotContains($bob->id, $memberIds);
    }

    public function test_explicit_members_override_defaults(): void
    {
        $khatwat = Team::query()->create(['name' => 'خطوات', 'slug' => 'khatwat', 'sort_order' => 5]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        DB::table('team_chat_members')->insert([
            'team_id' => $khatwat->id,
            'user_id' => $alice->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberIds = $this->service->memberIdsForTeam((int) $khatwat->id);

        $this->assertSame([$alice->id], $memberIds);
        $this->assertNotContains($bob->id, $memberIds);
    }

    public function test_non_admin_can_access_khatwat_without_explicit_row(): void
    {
        $khatwat = Team::query()->create(['name' => 'خطوات', 'slug' => 'khatwat', 'sort_order' => 5]);
        $member = User::factory()->create(['role' => 'member']);

        $this->assertTrue($this->service->userCanAccessTeam($member, (int) $khatwat->id));
        $this->assertContains((int) $khatwat->id, $this->service->accessibleTeamIdsForUser($member));
    }
}

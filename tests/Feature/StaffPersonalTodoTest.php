<?php

namespace Tests\Feature;

use App\Models\StaffPersonalTodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPersonalTodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_add_and_complete_personal_todo(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)
            ->post(route('staff-personal-todos.store'), ['title' => 'متابعة عميل'])
            ->assertRedirect(route('home.index'));

        $todo = StaffPersonalTodo::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($todo);
        $this->assertFalse($todo->is_done);

        $this->actingAs($user)
            ->patch(route('staff-personal-todos.update', $todo), ['is_done' => true])
            ->assertRedirect(route('home.index'));

        $todo->refresh();
        $this->assertTrue($todo->is_done);
        $this->assertNotNull($todo->completed_at);
    }

    public function test_user_cannot_update_another_users_todo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $todo = StaffPersonalTodo::query()->create([
            'user_id' => $owner->id,
            'title' => 'خاصة',
        ]);

        $this->actingAs($other)
            ->patch(route('staff-personal-todos.update', $todo), ['is_done' => true])
            ->assertForbidden();
    }
}

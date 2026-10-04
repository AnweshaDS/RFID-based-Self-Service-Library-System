<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_tasks_assigned_to_them(): void
    {
        $role = Role::create(['name' => 'Attendant']);

        $me = User::factory()->create(['name' => 'Me']);
        $me->roles()->attach($role->id);

        $someoneElse = User::factory()->create(['name' => 'Someone Else']);
        $supervisor = User::factory()->create(['name' => 'Supervisor']);

        TaskDelegation::create([
            'task' => 'Shelve returned books, Section B',
            'assigned_by' => $supervisor->id,
            'assigned_to' => $me->id,
            'status' => 'pending',
        ]);

        TaskDelegation::create([
            'task' => 'Shelve returned books, Section C',
            'assigned_by' => $supervisor->id,
            'assigned_to' => $someoneElse->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($me)->get(route('my-tasks'));

        $response->assertOk();
        $response->assertSee('Section B');
        $response->assertDontSee('Section C');
    }
}
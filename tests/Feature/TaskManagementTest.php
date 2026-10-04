<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithTaskAssignPermission(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'tasks.assign']);
        $role = Role::create(['name' => 'Librarian']);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_user_with_permission_can_create_a_task(): void
    {
        $assigner = $this->userWithTaskAssignPermission();
        $assignee = User::factory()->create();

        $response = $this->actingAs($assigner)->post(route('tasks.store'), [
            'assigned_to' => $assignee->id,
            'task' => 'Shelve returned books',
            'notes' => 'Section B',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('task_delegations', [
            'task' => 'Shelve returned books',
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_without_permission_cannot_access_task_creation(): void
    {
        $role = Role::create(['name' => 'Attendant']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('tasks.create'));

        $response->assertStatus(403);
    }

    public function test_assignee_can_update_their_own_task_status(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();

        $task = TaskDelegation::create([
            'task' => 'Test task',
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($assignee)->patch(route('tasks.update-status', $task), [
            'status' => 'done',
        ]);

        $response->assertRedirect(route('my-tasks'));
        $this->assertDatabaseHas('task_delegations', ['id' => $task->id, 'status' => 'done']);
    }

    public function test_user_cannot_update_someone_elses_task_status(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();
        $stranger = User::factory()->create();

        $task = TaskDelegation::create([
            'task' => 'Test task',
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($stranger)->patch(route('tasks.update-status', $task), [
            'status' => 'done',
        ]);

        $response->assertStatus(403);
    }
}
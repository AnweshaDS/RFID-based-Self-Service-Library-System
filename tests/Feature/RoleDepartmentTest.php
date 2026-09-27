<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleDepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_can_be_created(): void
    {
        $dept = Department::create([
            'name' => 'Computer Science and Engineering',
            'code' => 'CSE',
        ]);

        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Computer Science and Engineering',
            'code' => 'CSE',
        ]);
    }

    public function test_role_can_be_created(): void
    {
        $role = Role::create([
            'name' => 'Librarian',
            'description' => 'Staff role for library management',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Librarian',
            'description' => 'Staff role for library management',
        ]);
    }

    public function test_user_can_belong_to_a_department(): void
    {
        $dept = Department::create([
            'name' => 'Electrical and Electronic Engineering',
            'code' => 'EEE',
        ]);

        $user = User::factory()->create([
            'department_id' => $dept->id,
        ]);

        $this->assertEquals($dept->id, $user->department->id);
        $this->assertEquals('EEE', $user->department->code);
        $this->assertTrue($dept->users->contains($user));
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $adminRole = Role::create(['name' => 'Admin']);
        $librarianRole = Role::create(['name' => 'Librarian']);

        $user = User::factory()->create();
        $user->roles()->attach([$adminRole->id, $librarianRole->id]);

        $this->assertCount(2, $user->fresh()->roles);
        $this->assertTrue($user->roles->contains('name', 'Admin'));
        $this->assertTrue($user->roles->contains('name', 'Librarian'));
    }

    public function test_duplicate_user_role_assignment_is_prevented(): void
    {
        $role = Role::create(['name' => 'Student']);
        $user = User::factory()->create();

        $user->roles()->attach($role->id);

        $this->expectException(\Exception::class);
        $user->roles()->attach($role->id);
    }

    public function test_has_role_returns_true_for_assigned_role(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasRole('Admin'));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_has_role_returns_false_for_unassigned_role(): void
    {
        $role = Role::create(['name' => 'Librarian']);
        $user = User::factory()->create();

        $this->assertFalse($user->hasRole('Librarian'));
        $this->assertFalse($user->hasRole('Admin'));
    }

    public function test_task_delegation_can_be_created_between_users(): void
    {
        $assigner = User::factory()->create(['name' => 'Head Librarian']);
        $assignee = User::factory()->create(['name' => 'Assistant Librarian']);

        $task = TaskDelegation::create([
            'task' => 'inventory',
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending',
            'notes' => 'Perform quarterly shelf audit.',
        ]);

        $this->assertDatabaseHas('task_delegations', [
            'id' => $task->id,
            'task' => 'inventory',
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($assigner->delegatedTasks->contains($task));
        $this->assertTrue($assignee->assignedTasks->contains($task));
        $this->assertEquals($assigner->id, $task->assignedBy->id);
        $this->assertEquals($assignee->id, $task->assignedTo->id);
    }

    public function test_role_middleware_allows_authorized_users(): void
    {
        Route::get('/test-admin-route', fn () => 'Access Granted')->middleware('role:Admin');

        $role = Role::create(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-admin-route');

        $response->assertOk();
        $response->assertSee('Access Granted');
    }

    public function test_role_middleware_blocks_unauthorized_users(): void
    {
        Route::get('/test-admin-route-blocked', fn () => 'Access Granted')->middleware('role:Admin');

        $role = Role::create(['name' => 'Student']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-admin-route-blocked');

        $response->assertStatus(403);
    }
}

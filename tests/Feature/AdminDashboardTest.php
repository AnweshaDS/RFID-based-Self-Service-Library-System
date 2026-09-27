<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_admin_or_librarian_role_receives_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_librarian_can_access_dashboard(): void
    {
        $role = Role::create(['name' => 'Librarian']);
        $librarian = User::factory()->create();
        $librarian->roles()->attach($role->id);

        $response = $this->actingAs($librarian)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_displays_user_count(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_users'] === User::count());
    }

    public function test_dashboard_displays_department_count(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        Department::create(['name' => 'Computer Science and Engineering', 'code' => 'CSE']);
        Department::create(['name' => 'Electrical and Electronic Engineering', 'code' => 'EEE']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_departments'] === Department::count());
    }

    public function test_dashboard_displays_role_count(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        Role::create(['name' => 'Librarian']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_roles'] === Role::count());
    }

    public function test_dashboard_displays_todays_activity_statistics(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        ActivityLog::create([
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Book borrowed successfully.',
        ]);

        ActivityLog::create([
            'action' => 'return',
            'status' => 'failed',
            'message' => 'Return processing failed.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['activities_today'] >= 2
                && $stats['successful_borrows_today'] >= 1
                && $stats['failed_operations_today'] >= 1;
        });
    }

    public function test_dashboard_displays_recent_activities(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        ActivityLog::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Book borrowed successfully.',
            'barcode' => 'BOOK001',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Book borrowed successfully.');
    }

    public function test_dashboard_displays_task_delegations(): void
    {
        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        $assigner = User::factory()->create(['name' => 'Head Librarian']);
        $assignee = User::factory()->create(['name' => 'Assistant Librarian']);

        TaskDelegation::create([
            'task' => 'Quarterly shelf audit',
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Quarterly shelf audit');
        $response->assertSee('Assistant Librarian');
    }

    public function test_dashboard_loads_when_koha_is_unavailable(): void
    {
        config([
            'services.koha.base_url' => 'http://localhost:8080',
            'services.koha.client_id' => 'test-id',
            'services.koha.client_secret' => 'test-secret',
        ]);

        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $role = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Unavailable');
    }
}
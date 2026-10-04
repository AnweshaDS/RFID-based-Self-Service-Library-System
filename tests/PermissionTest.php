<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_access_protected_route(): void
    {
        Route::get('/test-permission-route', fn () => 'Access Granted')
            ->middleware('permission:catalog.edit');

        $permission = Permission::create(['name' => 'catalog.edit']);
        $role = Role::create(['name' => 'Cataloger']);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-permission-route');

        $response->assertOk();
        $response->assertSee('Access Granted');
    }

    public function test_user_without_permission_is_blocked(): void
    {
        Route::get('/test-permission-route-blocked', fn () => 'Access Granted')
            ->middleware('permission:catalog.edit');

        $role = Role::create(['name' => 'Attendant']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-permission-route-blocked');

        $response->assertStatus(403);
    }

    public function test_dual_role_user_has_permissions_from_both_roles(): void
    {
        $catalogPermission = Permission::create(['name' => 'catalog.edit']);
        $taskPermission = Permission::create(['name' => 'tasks.assign']);

        $catalogerRole = Role::create(['name' => 'Cataloger']);
        $catalogerRole->permissions()->attach($catalogPermission->id);

        $librarianRole = Role::create(['name' => 'Librarian']);
        $librarianRole->permissions()->attach($taskPermission->id);

        $user = User::factory()->create();
        $user->roles()->attach([$catalogerRole->id, $librarianRole->id]);

        $this->assertTrue($user->hasPermission('catalog.edit'));
        $this->assertTrue($user->hasPermission('tasks.assign'));
    }
}
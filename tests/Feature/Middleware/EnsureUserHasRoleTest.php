<?php

namespace Tests\Feature\Middleware;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-role-middleware', fn () => 'Role Granted')
            ->middleware('role:Admin,Librarian');
    }

    public function test_unauthenticated_request_aborts_401(): void
    {
        $response = $this->get('/test-role-middleware');

        $response->assertStatus(401);
    }

    public function test_user_without_specified_role_aborts_403(): void
    {
        $role = Role::create(['name' => 'Attendant']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-role-middleware');

        $response->assertStatus(403);
    }

    public function test_user_with_one_of_specified_roles_passes(): void
    {
        $role = Role::create(['name' => 'Librarian']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/test-role-middleware');

        $response->assertOk();
        $response->assertSee('Role Granted');
    }
}

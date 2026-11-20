<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $role = Role::create(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_guest_cannot_access_retention_settings(): void
    {
        $response = $this->get(route('admin.retention.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_admin_or_librarian_role_receives_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.retention.edit'));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_retention_settings_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.retention.edit'));

        $response->assertOk();
        $response->assertViewIs('admin.retention');
        $response->assertViewHasAll([
            'normalDays',
            'flaggedDays',
            'min',
            'max',
            'total',
            'expiredNow',
            'waiting',
            'flaggedCount',
        ]);
    }

    public function test_admin_can_update_retention_settings(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->put(route('admin.retention.update'), [
            'photo_retention_days' => 60,
            'flagged_retention_days' => 180,
        ]);

        $response->assertRedirect(route('admin.retention.edit'));
        $response->assertSessionHas('status', 'Retention settings saved.');

        $this->assertEquals('60', AppSetting::read('photo_retention_days'));
        $this->assertEquals('180', AppSetting::read('flagged_retention_days'));
    }

    public function test_retention_settings_validation_enforces_bounds(): void
    {
        $admin = $this->createAdminUser();

        // Below minimum (min is 7)
        $response = $this->actingAs($admin)->put(route('admin.retention.update'), [
            'photo_retention_days' => 2,
            'flagged_retention_days' => 5,
        ]);
        $response->assertSessionHasErrors(['photo_retention_days', 'flagged_retention_days']);

        // flagged_retention_days must be >= photo_retention_days
        $responseLesser = $this->actingAs($admin)->put(route('admin.retention.update'), [
            'photo_retention_days' => 100,
            'flagged_retention_days' => 30,
        ]);
        $responseLesser->assertSessionHasErrors('flagged_retention_days');
    }
}

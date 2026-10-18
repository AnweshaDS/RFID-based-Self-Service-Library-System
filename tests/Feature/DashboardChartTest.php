<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cataloger_sees_cataloging_link_but_no_circulation_chart(): void
    {
        $role = Role::create(['name' => 'Cataloger']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cataloging');
        $response->assertDontSee('Borrows &amp; Returns', false);
    }

    public function test_circulation_staff_sees_circulation_link_and_weekly_chart(): void
    {
        $role = Role::create(['name' => 'Circulation Staff']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Circulation');
        $response->assertViewHas('hasCirculationAccess', true);
    }

    public function test_user_with_no_role_sees_no_koha_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No Koha sections configured for your role yet.');
    }
}
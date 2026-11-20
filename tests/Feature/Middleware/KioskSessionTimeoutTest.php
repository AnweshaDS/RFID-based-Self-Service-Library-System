<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class KioskSessionTimeoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-kiosk-timeout', fn () => 'Still Active')
            ->middleware('kiosk.timeout');
    }

    public function test_active_session_updates_last_activity_and_passes(): void
    {
        Session::put('kiosk_last_activity', now()->subSeconds(30));

        $response = $this->get('/test-kiosk-timeout');

        $response->assertOk();
        $response->assertSee('Still Active');
        $this->assertTrue(Carbon::parse(Session::get('kiosk_last_activity'))->isToday());
    }

    public function test_expired_session_invalidates_and_redirects_to_welcome(): void
    {
        config(['kiosk.idle_timeout' => 60]); // limit is 60 + 5 = 65s

        Session::put('kiosk_patron', ['name' => 'Test Patron']);
        Session::put('kiosk_last_activity', now()->subSeconds(70));

        $response = $this->get('/test-kiosk-timeout');

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHas('status', 'Session timed out for your security.');
        $this->assertFalse(Session::has('kiosk_patron'));
    }
}

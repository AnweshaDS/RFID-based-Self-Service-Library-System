<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.koha.client_id' => 'test-client-id',
            'services.koha.client_secret' => 'test-client-secret',
        ]);
        $this->seed();
    }

    public function test_valid_cardnumber_entry_redirects_to_dashboard_and_stores_patron_with_checkouts(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/patrons*' => Http::response([
                [
                    'patron_id' => 10021,
                    'cardnumber' => 'STU001',
                    'firstname' => 'Arif',
                    'surname' => 'Hasan',
                ],
            ], 200),
            '*/api/v1/checkouts*' => Http::response([
                [
                    'checkout_id' => 88,
                    'patron_id' => 10021,
                    'item_id' => 501,
                    'due_date' => '2026-10-10T23:59:59Z',
                    'item' => [
                        'barcode' => 'BOOK999',
                        'biblio' => [
                            'title' => 'Design Patterns',
                            'author' => 'Erich Gamma',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.enter'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHas('kiosk_patron');

        $sessionPatron = session('kiosk_patron');
        $this->assertEquals('10021', $sessionPatron['patron_id']);
        $this->assertEquals('STU001', $sessionPatron['cardnumber']);
        $this->assertEquals('Arif Hasan', $sessionPatron['name']);
        $this->assertCount(1, $sessionPatron['borrowed_books']);
        $this->assertEquals('Design Patterns', $sessionPatron['borrowed_books'][0]['title']);
        $this->assertEquals('Erich Gamma', $sessionPatron['borrowed_books'][0]['author']);
        $this->assertEquals('BOOK999', $sessionPatron['borrowed_books'][0]['barcode']);

        $dashboardResponse = $this->get(route('kiosk.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Design Patterns');
        $dashboardResponse->assertSee('Erich Gamma');
    }

    public function test_patron_with_no_borrowed_books_stores_empty_array_and_dashboard_works(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/patrons*' => Http::response([
                [
                    'patron_id' => 10021,
                    'cardnumber' => 'STU001',
                    'firstname' => 'Arif',
                    'surname' => 'Hasan',
                ],
            ], 200),
            '*/api/v1/checkouts*' => Http::response([], 200),
        ]);

        $response = $this->post(route('kiosk.enter'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $sessionPatron = session('kiosk_patron');
        $this->assertEquals([], $sessionPatron['borrowed_books']);

        $dashboardResponse = $this->get(route('kiosk.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('No books currently borrowed.');
    }

    public function test_checkouts_lookup_failure_fails_gracefully(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/patrons*' => Http::response([
                [
                    'patron_id' => 10021,
                    'cardnumber' => 'STU001',
                    'firstname' => 'Arif',
                    'surname' => 'Hasan',
                ],
            ], 200),
            '*/api/v1/checkouts*' => Http::response([
                'error' => 'Internal Server Error',
            ], 500),
        ]);

        $response = $this->post(route('kiosk.enter'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertSessionHasErrors(['cardnumber' => 'Koha service unavailable. Please try again later.']);
    }

    public function test_unrecognized_cardnumber_fails_gracefully(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/patrons*' => Http::response([], 200),
        ]);

        $response = $this->post(route('kiosk.enter'), [
            'cardnumber' => 'UNKNOWN999',
        ]);

        $response->assertSessionHasErrors(['cardnumber' => 'Patron not found.']);
    }

    public function test_koha_oauth_failure_fails_gracefully_without_exposing_credentials(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'error' => 'invalid_client',
            ], 401),
        ]);

        $response = $this->post(route('kiosk.enter'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertSessionHasErrors(['cardnumber' => 'Koha service unavailable. Please try again later.']);
    }
}
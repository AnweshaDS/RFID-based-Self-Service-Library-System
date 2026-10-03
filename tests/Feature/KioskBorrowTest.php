<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskBorrowTest extends TestCase
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

    protected function createPatronSession(): void
    {
        session([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'status' => 'Active',
                'borrowed_books' => [],
                'recent_activity' => [],
            ],
            'kiosk_last_activity' => now(),
        ]);
    }

    public function test_no_patron_session_redirects_to_welcome(): void
    {
        $response = $this->get(route('kiosk.borrow'));

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHasErrors(['uid' => 'Please scan your RFID card first.']);
    }

    public function test_book_lookup_succeeds(): void
    {
        $this->createPatronSession();

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([
                [
                    'item_id' => 501,
                    'barcode' => 'BOOK001',
                    'biblio' => [
                        'title' => 'Clean Code',
                        'author' => 'Robert C. Martin',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.borrow.lookup'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.borrow.confirm'));
        $this->assertTrue(session()->has('kiosk_borrow_item'));
        $this->assertEquals(501, session('kiosk_borrow_item')['item_id']);

        $confirmResponse = $this->get(route('kiosk.borrow.confirm'));
        $confirmResponse->assertOk();
        $confirmResponse->assertSee('Clean Code');
        $confirmResponse->assertSee('Robert C. Martin');
    }

    public function test_book_not_found_shows_error(): void
    {
        $this->createPatronSession();

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([], 404),
        ]);

        $response = $this->post(route('kiosk.borrow.lookup'), [
            'barcode' => 'NONEXISTENT',
        ]);

        $response->assertSessionHasErrors(['barcode' => 'Book not found.']);
        $this->assertFalse(session()->has('kiosk_borrow_item'));

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/api/v1/checkouts');
        });
    }

    public function test_unavailable_book_cannot_be_borrowed(): void
    {
        $this->createPatronSession();

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([
                [
                    'item_id' => 502,
                    'barcode' => 'BOOK002',
                    'checked_out' => true,
                    'biblio' => [
                        'title' => 'Design Patterns',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.borrow.lookup'), [
            'barcode' => 'BOOK002',
        ]);

        $response->assertSessionHasErrors(['barcode' => 'This book is currently unavailable.']);
        $this->assertFalse(session()->has('kiosk_borrow_item'));
    }

    public function test_successful_checkout_refreshes_dashboard(): void
    {
        $this->createPatronSession();

        session([
            'kiosk_borrow_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
            ],
        ]);

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/checkouts' => Http::response([
                'checkout_id' => 99,
                'patron_id' => 10021,
                'item_id' => 501,
                'due_date' => '2026-10-15T23:59:59Z',
            ], 201),
            '*/api/v1/checkouts*' => Http::response([
                [
                    'checkout_id' => 99,
                    'patron_id' => 10021,
                    'item_id' => 501,
                    'due_date' => '2026-10-15T23:59:59Z',
                    'item' => [
                        'barcode' => 'BOOK001',
                        'biblio' => [
                            'title' => 'Clean Code',
                            'author' => 'Robert C. Martin',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.borrow.confirm.store'));

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHas('status', 'Book borrowed successfully!');
        $this->assertFalse(session()->has('kiosk_borrow_item'));

        $patron = session('kiosk_patron');
        $this->assertCount(1, $patron['borrowed_books']);
        $this->assertEquals('Clean Code', $patron['borrowed_books'][0]['title']);
    }

    public function test_checkout_failure_shows_user_friendly_error(): void
    {
        $this->createPatronSession();

        session([
            'kiosk_borrow_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
            ],
        ]);

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/checkouts' => Http::response([
                'error' => 'Checkout denied by Koha',
            ], 400),
        ]);

        $response = $this->post(route('kiosk.borrow.confirm.store'));

        $response->assertRedirect(route('kiosk.borrow'));
        $response->assertSessionHasErrors(['barcode' => 'Unable to borrow this book. Please try again.']);
        $this->assertEquals([], session('kiosk_patron')['borrowed_books']);
    }

    public function test_cancel_borrow_clears_session_and_redirects(): void
    {
        $this->createPatronSession();
        session([
            'kiosk_borrow_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
            ],
        ]);

        $response = $this->get(route('kiosk.borrow.cancel'));

        $response->assertRedirect(route('kiosk.borrow'));
        $this->assertFalse(session()->has('kiosk_borrow_item'));
    }
}

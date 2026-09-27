<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskReturnTest extends TestCase
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

    protected function createPatronSession(array $borrowedBooks = []): void
    {
        session([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'status' => 'Active',
                'borrowed_books' => $borrowedBooks,
                'recent_activity' => [],
            ],
            'kiosk_last_activity' => now(),
        ]);
    }

    public function test_return_page_requires_patron_session(): void
    {
        $response = $this->get(route('kiosk.return'));

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHasErrors(['uid' => 'Please scan your RFID card first.']);
    }

    public function test_return_page_displays_current_borrowed_books(): void
    {
        $this->createPatronSession([
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'barcode' => 'BOOK001',
                'due' => '2026-10-15T23:59:59Z',
                'raw' => ['item_id' => 501],
            ],
        ]);

        $response = $this->get(route('kiosk.return'));

        $response->assertOk();
        $response->assertSee('Clean Code');
        $response->assertSee('Robert C. Martin');
        $response->assertSee('BOOK001');
    }

    public function test_unknown_barcode_gives_error(): void
    {
        $this->createPatronSession();

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([], 404),
        ]);

        $response = $this->post(route('kiosk.return.lookup'), [
            'barcode' => 'UNKNOWN999',
        ]);

        $response->assertSessionHasErrors(['barcode' => 'Book not found.']);
        $this->assertFalse(session()->has('kiosk_return_item'));
    }

    public function test_item_not_checked_out_gives_error(): void
    {
        $this->createPatronSession(); // No borrowed books

        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([
                [
                    'item_id' => 505,
                    'barcode' => 'BOOK005',
                    'biblio' => ['title' => 'Refactoring'],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.return.lookup'), [
            'barcode' => 'BOOK005',
        ]);

        $response->assertSessionHasErrors(['barcode' => 'This book is not currently borrowed on your account.']);
        $this->assertFalse(session()->has('kiosk_return_item'));
    }

    public function test_item_borrowed_by_another_patron_cannot_be_returned_by_current_patron(): void
    {
        // Patron has BOOK001
        $this->createPatronSession([
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'barcode' => 'BOOK001',
                'due' => '2026-10-15T23:59:59Z',
                'raw' => ['item_id' => 501],
            ],
        ]);

        // Attempting to return BOOK002 (borrowed by someone else)
        Http::fake([
            '*/api/v1/oauth/token' => Http::response(['access_token' => 'fake-token-123'], 200),
            '*/api/v1/items*' => Http::response([
                [
                    'item_id' => 502,
                    'barcode' => 'BOOK002',
                    'biblio' => ['title' => 'Design Patterns'],
                ],
            ], 200),
        ]);

        $response = $this->post(route('kiosk.return.lookup'), [
            'barcode' => 'BOOK002',
        ]);

        $response->assertSessionHasErrors(['barcode' => 'This book is not currently borrowed on your account.']);
        $this->assertFalse(session()->has('kiosk_return_item'));
    }

    public function test_confirmation_page_displays_selected_book(): void
    {
        $this->createPatronSession([
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'barcode' => 'BOOK001',
                'due' => '2026-10-15T23:59:59Z',
                'raw' => ['item_id' => 501],
            ],
        ]);

        session([
            'kiosk_return_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'biblio' => [
                    'title' => 'Clean Code',
                    'author' => 'Robert C. Martin',
                ],
            ],
        ]);

        $response = $this->get(route('kiosk.return.confirm'));

        $response->assertOk();
        $response->assertSee('Confirm Return');
        $response->assertSee('Clean Code');
        $response->assertSee('Robert C. Martin');
        $response->assertSee('BOOK001');
    }

    public function test_confirming_return_does_not_falsely_report_success(): void
    {
        $borrowedBooks = [
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'barcode' => 'BOOK001',
                'due' => '2026-10-15T23:59:59Z',
                'raw' => ['item_id' => 501],
            ],
        ];

        $this->createPatronSession($borrowedBooks);

        session([
            'kiosk_return_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'biblio' => ['title' => 'Clean Code'],
            ],
        ]);

        $response = $this->post(route('kiosk.return.confirm.store'));

        // Fails gracefully with clear message because Koha checkin transport is not configured yet
        $response->assertRedirect(route('kiosk.return'));
        $response->assertSessionHasErrors([
            'barcode' => 'Return processing is not connected to Koha yet. The book was not marked as returned.',
        ]);

        // Verify book is NOT removed from patron session
        $this->assertCount(1, session('kiosk_patron')['borrowed_books']);
    }

    public function test_cancellation_returns_to_dashboard(): void
    {
        $this->createPatronSession();
        session(['kiosk_return_item' => ['item_id' => 501]]);

        $response = $this->get(route('kiosk.return.cancel'));

        $response->assertRedirect(route('kiosk.dashboard'));
        $this->assertFalse(session()->has('kiosk_return_item'));
    }
}

<?php

namespace Tests\Feature;

use App\Services\KioskCartService;
use App\Services\KohaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
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
        Session::put('kiosk_patron', [
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'name' => 'Arif Hasan',
            'status' => 'Active',
            'borrowed_books' => $borrowedBooks,
            'recent_activity' => [],
        ]);
        Session::put('kiosk_last_activity', now());
    }

    public function test_return_page_requires_patron_session(): void
    {
        $response = $this->get(route('kiosk.return'));

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHasErrors(['uid' => 'Please scan your RFID card first.']);
    }

    public function test_return_page_renders_cart_for_patron(): void
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
        $response->assertViewIs('kiosk.cart');
        $response->assertViewHas('mode', 'return');
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
    }

    public function test_item_borrowed_by_another_patron_cannot_be_returned_by_current_patron(): void
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
    }

    public function test_valid_book_is_added_to_return_cart(): void
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

        $response = $this->post(route('kiosk.return.lookup'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.return'));
        $response->assertSessionHas('status');

        $cart = app(KioskCartService::class);
        $this->assertTrue($cart->has('return', 'BOOK001'));
    }

    public function test_clear_return_cart_removes_all_items(): void
    {
        $this->createPatronSession();

        $cart = app(KioskCartService::class);
        $cart->add('return', [
            'item_id' => 501,
            'barcode' => 'BOOK001',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
        ]);

        $response = $this->post(route('kiosk.return.clear'));

        $response->assertRedirect(route('kiosk.return'));
        $this->assertEmpty($cart->items('return'));
    }

    public function test_confirm_return_commits_items_and_redirects_to_success(): void
    {
        $this->createPatronSession([
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'barcode' => 'BOOK001',
                'raw' => ['item_id' => 501],
            ],
        ]);

        $cart = app(KioskCartService::class);
        $cart->add('return', [
            'item_id' => 501,
            'barcode' => 'BOOK001',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
        ]);
        $token = $cart->token('return');

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('checkinItem')
            ->with(501, 'BOOK001')
            ->willReturn(['item_id' => 501, 'status' => 'returned']);

        $mockKoha->expects($this->once())
            ->method('getPatronCheckouts')
            ->with(10021)
            ->willReturn([]);

        $this->app->instance(KohaService::class, $mockKoha);

        $response = $this->post(route('kiosk.return.confirm.store'), [
            'cart_token' => $token,
        ]);

        $response->assertRedirect(route('kiosk.success'));
        $this->assertTrue(Session::has('kiosk_last_receipt'));
    }
}

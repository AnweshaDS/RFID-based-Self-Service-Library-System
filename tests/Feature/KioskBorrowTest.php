<?php

namespace Tests\Feature;

use App\Models\PatronVerificationCapture;
use App\Services\KioskCartService;
use App\Services\KohaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
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

    public function test_no_patron_session_redirects_to_welcome(): void
    {
        $response = $this->get(route('kiosk.borrow'));

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHasErrors(['uid' => 'Please scan your RFID card first.']);
    }

    public function test_book_lookup_succeeds_and_adds_to_cart(): void
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

        $response->assertRedirect(route('kiosk.borrow'));
        $response->assertSessionHas('status');

        $cart = app(KioskCartService::class);
        $this->assertTrue($cart->has('borrow', 'BOOK001'));
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
    }

    public function test_clear_borrow_cart_removes_all_items(): void
    {
        $this->createPatronSession();

        $cart = app(KioskCartService::class);
        $cart->add('borrow', [
            'item_id' => 501,
            'barcode' => 'BOOK001',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
        ]);

        $response = $this->post(route('kiosk.borrow.clear'));

        $response->assertRedirect(route('kiosk.borrow'));
        $this->assertEmpty($cart->items('borrow'));
    }

    public function test_confirm_borrow_requires_verification_photo(): void
    {
        $this->createPatronSession();

        $cart = app(KioskCartService::class);
        $cart->add('borrow', [
            'item_id' => 501,
            'barcode' => 'BOOK001',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
        ]);
        $token = $cart->token('borrow');

        $response = $this->post(route('kiosk.borrow.confirm.store'), [
            'cart_token' => $token,
        ]);

        $response->assertRedirect(route('kiosk.borrow'));
        $response->assertSessionHasErrors(['barcode' => 'Please complete the identity verification photo before confirming.']);
    }

    public function test_successful_borrow_with_verification_redirects_to_success(): void
    {
        $this->createPatronSession();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'captures/1.jpg',
            'face_detected' => true,
        ]);
        Session::put('kiosk_verification_capture_borrow', $capture->id);

        $cart = app(KioskCartService::class);
        $cart->add('borrow', [
            'item_id' => 501,
            'barcode' => 'BOOK001',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
        ]);
        $token = $cart->token('borrow');

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('checkoutItem')
            ->with(10021, 501)
            ->willReturn(['checkout_id' => 99, 'due_date' => '2026-12-01']);

        $mockKoha->expects($this->once())
            ->method('getPatronCheckouts')
            ->with(10021)
            ->willReturn([]);

        $this->app->instance(KohaService::class, $mockKoha);

        $response = $this->post(route('kiosk.borrow.confirm.store'), [
            'cart_token' => $token,
        ]);

        $response->assertRedirect(route('kiosk.success'));
        $this->assertTrue(Session::has('kiosk_last_receipt'));
    }
}

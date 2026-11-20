<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\KohaService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class KioskRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function setPatronSession(array $borrowedBooks = []): void
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

    public function test_unauthenticated_session_redirects_to_welcome(): void
    {
        $response = $this->post(route('kiosk.renew'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.welcome'));
        $response->assertSessionHasErrors(['uid' => 'Please scan your RFID card first.']);
    }

    public function test_renew_requires_valid_barcode(): void
    {
        $this->setPatronSession();

        $response = $this->post(route('kiosk.renew'), []);
        $response->assertSessionHasErrors('barcode');
    }

    public function test_renew_fails_when_book_is_not_borrowed_by_patron(): void
    {
        $this->setPatronSession([
            [
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
                'raw' => ['checkout_id' => 123, 'item_id' => 501],
            ],
        ]);

        $response = $this->post(route('kiosk.renew'), [
            'barcode' => 'DIFFERENT_BOOK',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHasErrors(['renew' => 'This book is not on your account.']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'renew',
            'status' => 'failed',
            'patron_id' => '10021',
            'barcode' => 'DIFFERENT_BOOK',
        ]);
    }

    public function test_renew_fails_when_koha_disallows_renewal(): void
    {
        $this->setPatronSession([
            [
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
                'raw' => ['checkout_id' => 123, 'item_id' => 501],
            ],
        ]);

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getRenewability')
            ->with(123)
            ->willReturn([
                'allowed' => false,
                'reason' => 'too_many',
            ]);

        $this->app->instance(KohaService::class, $mockKoha);

        $response = $this->post(route('kiosk.renew'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHasErrors(['renew' => 'You have reached the maximum number of renewals for this book.']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'renew',
            'status' => 'failed',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
        ]);
    }

    public function test_renew_succeeds_and_updates_session_and_logs_activity(): void
    {
        $this->setPatronSession([
            [
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
                'raw' => ['checkout_id' => 123, 'item_id' => 501],
            ],
        ]);

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getRenewability')
            ->with(123)
            ->willReturn(['allowed' => true, 'reason' => null]);

        $mockKoha->expects($this->once())
            ->method('renewCheckout')
            ->with(123)
            ->willReturn([
                'checkout_id' => 123,
                'due_date' => '2026-12-01T23:59:59Z',
            ]);

        $mockKoha->expects($this->once())
            ->method('getPatronCheckouts')
            ->with(10021)
            ->willReturn([
                [
                    'checkout_id' => 123,
                    'item_id' => 501,
                    'due_date' => '2026-12-01T23:59:59Z',
                    'item' => [
                        'barcode' => 'BOOK001',
                        'biblio' => [
                            'title' => 'Clean Code',
                            'author' => 'Robert C. Martin',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(KohaService::class, $mockKoha);

        $response = $this->post(route('kiosk.renew'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHas('renewed');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'renew',
            'status' => 'success',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
            'item_id' => 501,
        ]);
    }

    public function test_renew_handles_koha_exception_gracefully(): void
    {
        $this->setPatronSession([
            [
                'barcode' => 'BOOK001',
                'title' => 'Clean Code',
                'raw' => ['checkout_id' => 123, 'item_id' => 501],
            ],
        ]);

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getRenewability')
            ->with(123)
            ->willThrowException(new Exception('Network timeout'));

        $this->app->instance(KohaService::class, $mockKoha);

        $response = $this->post(route('kiosk.renew'), [
            'barcode' => 'BOOK001',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));
        $response->assertSessionHasErrors('renew');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'renew',
            'status' => 'failed',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
        ]);
    }
}

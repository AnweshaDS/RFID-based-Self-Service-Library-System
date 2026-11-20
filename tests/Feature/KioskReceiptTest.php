<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class KioskReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function setPatronSession(): void
    {
        Session::put('kiosk_patron', [
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'name' => 'Arif Hasan',
            'status' => 'Active',
            'borrowed_books' => [],
            'recent_activity' => [],
        ]);
        Session::put('kiosk_last_activity', now());
    }

    public function test_show_receipt_view_redirects_when_no_receipt_in_session(): void
    {
        $this->setPatronSession();

        $response = $this->get(route('kiosk.receipt.view'));

        $response->assertRedirect(route('kiosk.dashboard'));
    }

    public function test_show_receipt_view_renders_when_receipt_present(): void
    {
        $this->setPatronSession();
        Session::put('kiosk_last_receipt', [
            'type' => 'borrow',
            'patron_name' => 'Arif Hasan',
            'patron_id' => '10021',
            'items' => [
                ['barcode' => 'BOOK001', 'title' => 'Clean Code', 'author' => 'Robert Martin', 'ok' => true],
            ],
            'timestamp' => now(),
        ]);

        $response = $this->get(route('kiosk.receipt.view'));

        $response->assertOk();
        $response->assertViewIs('kiosk.receipt-view');
    }

    public function test_print_receipt_html_renders_printable_receipt(): void
    {
        $this->setPatronSession();
        Session::put('kiosk_last_receipt', [
            'type' => 'borrow',
            'patron_name' => 'Arif Hasan',
            'patron_id' => '10021',
            'items' => [
                ['barcode' => 'BOOK001', 'title' => 'Clean Code', 'author' => 'Robert Martin', 'ok' => true],
            ],
            'timestamp' => now(),
        ]);

        $response = $this->get(route('kiosk.receipt'));

        $response->assertOk();
        $response->assertViewIs('kiosk.receipt');
        $response->assertSee('Clean Code');
    }

    public function test_receipt_pdf_downloads_pdf_when_receipt_present(): void
    {
        $this->setPatronSession();
        Session::put('kiosk_last_receipt', [
            'type' => 'borrow',
            'patron_name' => 'Arif Hasan',
            'patron_id' => '10021',
            'items' => [
                ['barcode' => 'BOOK001', 'title' => 'Clean Code', 'author' => 'Robert Martin', 'ok' => true],
            ],
            'timestamp' => now(),
        ]);

        $response = $this->get(route('kiosk.receipt.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_receipt_pdf_redirects_when_no_receipt(): void
    {
        $this->setPatronSession();

        $response = $this->get(route('kiosk.receipt.pdf'));

        $response->assertRedirect(route('kiosk.dashboard'));
    }

    public function test_keepalive_returns_204_no_content(): void
    {
        $this->setPatronSession();

        $response = $this->post(route('kiosk.keepalive'));

        $response->assertNoContent();
    }

    public function test_logout_flushes_session_and_redirects(): void
    {
        $this->setPatronSession();
        Session::put('kiosk_verification_capture_borrow', 10);
        Cache::put('kiosk.pending_scan', ['id' => 1], 10);

        $response = $this->post(route('kiosk.logout'));

        $response->assertRedirect(route('kiosk.welcome'));
        $this->assertFalse(Session::has('kiosk_patron'));
        $this->assertFalse(Session::has('kiosk_verification_capture_borrow'));
        $this->assertNull(Cache::get('kiosk.pending_scan'));
    }
}

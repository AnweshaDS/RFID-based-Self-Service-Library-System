<?php

namespace Tests\Feature;

use App\Models\BookTag;
use App\Models\RfidCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class KioskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.kiosk.token' => 'secret-kiosk-token-123']);
        Cache::flush();
    }

    public function test_scan_aborts_without_valid_token_header(): void
    {
        $response = $this->postJson('/api/kiosk/scan', [
            'uid' => '04A1B2C3',
        ]);

        $response->assertStatus(401);

        $responseInvalid = $this->postJson('/api/kiosk/scan', [
            'uid' => '04A1B2C3',
        ], [
            'X-Kiosk-Token' => 'wrong-token',
        ]);

        $responseInvalid->assertStatus(401);
    }

    public function test_scan_with_empty_uid_returns_422(): void
    {
        $response = $this->postJson('/api/kiosk/scan', [
            'uid' => '',
        ], [
            'X-Kiosk-Token' => 'secret-kiosk-token-123',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false, 'message' => 'Invalid UID']);
    }

    public function test_scan_patron_card_stores_pending_scan(): void
    {
        RfidCard::create([
            'uid' => 'E0040100',
            'cardnumber' => 'STU1001',
            'active' => true,
        ]);

        $response = $this->postJson('/api/kiosk/scan', [
            'uid' => 'e0-04-01-00', // Test UID cleaning & uppercase
        ], [
            'X-Kiosk-Token' => 'secret-kiosk-token-123',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'patron']);

        $pending = Cache::get('kiosk.pending_scan');
        $this->assertNotNull($pending);
        $this->assertEquals('patron', $pending['type']);
        $this->assertEquals('STU1001', $pending['cardnumber']);

        $this->assertTrue(Cache::get('kiosk.rfid_scan.STU1001'));
    }

    public function test_scan_book_tag_stores_pending_book_scan(): void
    {
        BookTag::create([
            'uid' => 'B001B002',
            'barcode' => 'BOOK999',
            'active' => true,
        ]);

        $response = $this->postJson('/api/kiosk/scan', [
            'uid' => 'B001B002',
        ], [
            'X-Kiosk-Token' => 'secret-kiosk-token-123',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'book']);

        $pending = Cache::get('kiosk.pending_scan');
        $this->assertNotNull($pending);
        $this->assertEquals('book', $pending['type']);
        $this->assertEquals('BOOK999', $pending['barcode']);
    }

    public function test_scan_unregistered_tag_stores_error_and_returns_404(): void
    {
        $response = $this->postJson('/api/kiosk/scan', [
            'uid' => 'UNKNOWN123',
        ], [
            'X-Kiosk-Token' => 'secret-kiosk-token-123',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['ok' => false, 'message' => 'Tag not registered']);

        $pending = Cache::get('kiosk.pending_scan');
        $this->assertNotNull($pending);
        $this->assertEquals('error', $pending['type']);
    }

    public function test_poll_scan_returns_idle_when_empty(): void
    {
        $response = $this->getJson('/api/kiosk/poll');

        $response->assertOk();
        $response->assertJson(['idle' => true]);
    }

    public function test_poll_scan_returns_and_forgets_pending_scan(): void
    {
        Cache::put('kiosk.pending_scan', [
            'id' => 'scan-1',
            'type' => 'patron',
            'cardnumber' => 'STU001',
        ], 10);

        $response = $this->getJson('/api/kiosk/poll?type=patron');

        $response->assertOk();
        $response->assertJson([
            'id' => 'scan-1',
            'type' => 'patron',
            'cardnumber' => 'STU001',
        ]);

        $this->assertNull(Cache::get('kiosk.pending_scan'));
    }

    public function test_poll_scan_detects_switch_when_patron_taps_during_book_workflow(): void
    {
        Cache::put('kiosk.pending_scan', [
            'id' => 'scan-switch',
            'type' => 'patron',
            'cardnumber' => 'STU002',
        ], 10);

        $response = $this->getJson('/api/kiosk/poll?type=book');

        $response->assertOk();
        $response->assertJson([
            'id' => 'scan-switch',
            'switch' => true,
            'cardnumber' => 'STU002',
        ]);
    }

    public function test_reader_status_endpoint_records_status_with_token(): void
    {
        $response = $this->postJson('/api/kiosk/reader-status', [
            'connected' => true,
        ], [
            'X-Kiosk-Token' => 'secret-kiosk-token-123',
        ]);

        $response->assertNoContent();

        $onlineResponse = $this->getJson('/api/kiosk/reader-status');
        $onlineResponse->assertOk();
        $onlineResponse->assertJson(['online' => true]);
    }

    public function test_reader_status_endpoint_aborts_without_token(): void
    {
        $response = $this->postJson('/api/kiosk/reader-status', [
            'connected' => true,
        ]);

        $response->assertStatus(401);
    }
}

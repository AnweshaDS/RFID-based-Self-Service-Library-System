<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\RfidCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_activity_log_model_can_be_created(): void
    {
        $log = ActivityLog::create([
            'patron_id' => '10021',
            'action' => 'rfid_scan',
            'status' => 'success',
            'message' => 'RFID card scanned successfully.',
            'barcode' => 'BOOK001',
            'item_id' => 501,
            'metadata' => ['ip' => '127.0.0.1'],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'patron_id' => '10021',
            'action' => 'rfid_scan',
            'status' => 'success',
            'message' => 'RFID card scanned successfully.',
            'barcode' => 'BOOK001',
            'item_id' => 501,
        ]);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->fresh()->metadata);
    }

    public function test_successful_rfid_scan_creates_activity_log(): void
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

        $response = $this->post(route('kiosk.scan'), [
            'uid' => '04:b2:11:8a:92:31',
        ]);

        $response->assertRedirect(route('kiosk.dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'rfid_scan',
            'status' => 'success',
            'patron_id' => '10021',
        ]);
    }

    public function test_unknown_rfid_creates_failed_activity_log(): void
    {
        Http::fake();

        $response = $this->post(route('kiosk.scan'), [
            'uid' => 'FF:FF:FF:FF:FF:FF',
        ]);

        $response->assertSessionHasErrors(['uid']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'rfid_scan',
            'status' => 'failed',
            'message' => 'RFID card not recognized.',
        ]);
    }

    public function test_successful_borrow_creates_successful_activity_log(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/checkouts' => Http::response([
                'checkout_id' => 99,
                'patron_id' => 10021,
                'item_id' => 501,
                'due_date' => '2026-10-15T23:59:59Z',
            ], 201),
            '*/api/v1/checkouts*' => Http::response([], 200),
        ]);

        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'borrowed_books' => [],
            ],
            'kiosk_borrow_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'title' => 'Laravel Up & Running',
            ],
        ]);

        $response = $this->post(route('kiosk.borrow.confirm.store'));

        $response->assertRedirect(route('kiosk.dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'borrow',
            'status' => 'success',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
            'item_id' => 501,
            'message' => 'Book borrowed successfully.',
        ]);
    }

    public function test_failed_borrow_creates_failed_activity_log(): void
    {
        Http::fake([
            '*/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-token-123',
            ], 200),
            '*/api/v1/checkouts' => Http::response([
                'error' => 'Item is not available for checkout',
            ], 403),
        ]);

        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'borrowed_books' => [],
            ],
            'kiosk_borrow_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'title' => 'Laravel Up & Running',
            ],
        ]);

        $response = $this->post(route('kiosk.borrow.confirm.store'));

        $response->assertRedirect(route('kiosk.borrow'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'borrow',
            'status' => 'failed',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
            'item_id' => 501,
        ]);
    }

    public function test_return_attempt_while_checkin_transport_is_unavailable_creates_failed_activity_log(): void
    {
        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'borrowed_books' => [
                    [
                        'item_id' => 501,
                        'barcode' => 'BOOK001',
                        'title' => 'Clean Code',
                    ],
                ],
            ],
            'kiosk_return_item' => [
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'biblio' => ['title' => 'Clean Code'],
            ],
        ]);

        $response = $this->post(route('kiosk.return.confirm.store'));

        $response->assertRedirect(route('kiosk.return'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'return',
            'status' => 'failed',
            'patron_id' => '10021',
            'barcode' => 'BOOK001',
            'item_id' => 501,
            'message' => 'Return processing is not connected to Koha yet.',
        ]);
    }

    public function test_dashboard_displays_recent_activity_for_current_patron(): void
    {
        ActivityLog::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Book borrowed successfully.',
            'barcode' => 'BOOK001',
        ]);

        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'status' => 'Active',
                'borrowed_books' => [],
            ],
        ]);

        $response = $this->get(route('kiosk.dashboard'));

        $response->assertOk();
        $response->assertSee('Book borrowed successfully.');
        $response->assertSee('success');
    }

    public function test_dashboard_does_not_display_another_patrons_activities(): void
    {
        ActivityLog::create([
            'patron_id' => '99999',
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Secret Activity Patron B',
        ]);

        ActivityLog::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'status' => 'success',
            'message' => 'Visible Activity Patron A',
        ]);

        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'status' => 'Active',
                'borrowed_books' => [],
            ],
        ]);

        $response = $this->get(route('kiosk.dashboard'));

        $response->assertOk();
        $response->assertSee('Visible Activity Patron A');
        $response->assertDontSee('Secret Activity Patron B');
    }

    public function test_activity_list_is_limited_to_most_recent_records(): void
    {
        $baseTime = now()->subHours(1);
        for ($i = 1; $i <= 15; $i++) {
            $log = ActivityLog::create([
                'patron_id' => '10021',
                'action' => 'rfid_scan',
                'status' => 'success',
                'message' => "Activity Log #{$i}",
            ]);
            $log->timestamps = false;
            $log->created_at = $baseTime->copy()->addMinutes($i);
            $log->save();
        }

        $this->withSession([
            'kiosk_patron' => [
                'patron_id' => '10021',
                'cardnumber' => 'STU001',
                'name' => 'Arif Hasan',
                'status' => 'Active',
                'borrowed_books' => [],
            ],
        ]);

        $response = $this->get(route('kiosk.dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentActivities', function ($activities) {
            return count($activities) === 10 && $activities->first()->message === 'Activity Log #15';
        });
    }
}

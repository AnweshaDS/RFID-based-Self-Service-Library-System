<?php

namespace Tests\Unit;

use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_creates_activity_record(): void
    {
        config(['kiosk.id' => 'kiosk-laptop-01']);

        $service = new ActivityLogService();

        $log = $service->log(
            action: 'borrow',
            status: 'success',
            patronId: '10021',
            message: 'Book borrowed',
            barcode: 'BOOK001',
            itemId: 501,
            metadata: ['device' => 'rfid-reader-1']
        );

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'action' => 'borrow',
            'status' => 'success',
            'patron_id' => '10021',
            'message' => 'Book borrowed',
            'barcode' => 'BOOK001',
            'item_id' => 501,
            'kiosk_id' => 'kiosk-laptop-01',
        ]);

        $this->assertEquals(['device' => 'rfid-reader-1'], $log->metadata);
    }
}

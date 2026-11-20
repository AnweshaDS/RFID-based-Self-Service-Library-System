<?php

namespace Tests\Feature;

use App\Models\PatronVerificationCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KioskVerificationTest extends TestCase
{
    use RefreshDatabase;

    // 1x1 transparent PNG as base64 data URL
    private string $validPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('kiosk.photo_disk'));
    }

    private function setPatronSession(): void
    {
        Session::put('kiosk_patron', [
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'name' => 'Arif Hasan',
        ]);
    }

    public function test_invalid_action_returns_404(): void
    {
        $this->setPatronSession();

        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'invalid']), [
            'image' => $this->validPngBase64,
        ]);

        $response->assertNotFound();
    }

    public function test_no_patron_session_returns_403(): void
    {
        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'borrow']), [
            'image' => $this->validPngBase64,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'No active patron session.']);
    }

    public function test_invalid_or_corrupt_image_payload_returns_422(): void
    {
        $this->setPatronSession();

        // Not a data:image/... url
        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'borrow']), [
            'image' => 'not-a-base64-image',
        ]);
        $response->assertStatus(422);

        // Data url prefix with invalid bytes
        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'borrow']), [
            'image' => 'data:image/png;base64,bm90LWFuLWltYWdl',
        ]);
        $response->assertStatus(422);
    }

    public function test_successful_capture_saves_file_and_creates_capture_record(): void
    {
        $this->setPatronSession();

        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'borrow']), [
            'image' => $this->validPngBase64,
            'face_detected' => true,
            'barcode' => 'BOOK001',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'capture_id']);

        $captureId = $response->json('capture_id');
        $this->assertNotNull($captureId);

        $capture = PatronVerificationCapture::find($captureId);
        $this->assertNotNull($capture);
        $this->assertEquals('10021', $capture->patron_id);
        $this->assertEquals('STU001', $capture->cardnumber);
        $this->assertEquals('borrow', $capture->action);
        $this->assertEquals('BOOK001', $capture->barcode);
        $this->assertTrue($capture->face_detected);

        Storage::disk(config('kiosk.photo_disk'))->assertExists($capture->photo_path);
        $this->assertEquals($captureId, Session::get('kiosk_verification_capture_borrow'));
    }

    public function test_duplicate_capture_within_window_reuses_existing(): void
    {
        $this->setPatronSession();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/test.png',
            'face_detected' => true,
        ]);

        Session::put('kiosk_verification_capture_borrow', $capture->id);

        $response = $this->postJson(route('kiosk.verification.capture', ['action' => 'borrow']), [
            'image' => $this->validPngBase64,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'capture_id' => $capture->id,
        ]);

        $this->assertEquals(1, PatronVerificationCapture::count());
    }
}

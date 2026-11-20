<?php

namespace Tests\Unit;

use App\Models\PatronVerificationCapture;
use App\Services\FaceMatchService;
use App\Services\KohaPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('kiosk.photo_disk'));
    }

    public function test_check_returns_skipped_no_file_when_photo_is_missing(): void
    {
        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $service = new FaceMatchService($mockPhotos);

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'nonexistent/path.jpg',
        ]);

        $this->assertEquals('skipped_no_file', $service->check($capture));
    }

    public function test_check_returns_skipped_koha_when_koha_unavailable(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('patron-verification-captures/test.jpg', 'img');

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('lookup')
            ->with(10021)
            ->willReturn(['state' => 'unavailable', 'bytes' => null]);

        $service = new FaceMatchService($mockPhotos);

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/test.jpg',
        ]);

        $this->assertEquals('skipped_koha', $service->check($capture));
    }

    public function test_check_saves_no_reference_when_koha_has_no_photo(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('patron-verification-captures/test.jpg', 'img');

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('lookup')
            ->with(10021)
            ->willReturn(['state' => 'none', 'bytes' => null]);

        $service = new FaceMatchService($mockPhotos);

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/test.jpg',
        ]);

        $result = $service->check($capture);

        $this->assertEquals('no_reference', $result);
        $capture->refresh();
        $this->assertEquals('no_reference', $capture->match_status);
        $this->assertNull($capture->match_score);
        $this->assertNotNull($capture->matched_at);
    }
}

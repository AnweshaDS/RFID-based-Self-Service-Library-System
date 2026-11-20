<?php

namespace Tests\Unit;

use App\Services\KohaPhotoService;
use App\Services\KohaService;
use Exception;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KohaPhotoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('kiosk.photo_disk'));
    }

    public function test_lookup_returns_none_for_invalid_patron_id(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $service = new KohaPhotoService($mockKoha);

        $result = $service->lookup(0);
        $this->assertEquals(['state' => 'none', 'bytes' => null], $result);

        $resultNeg = $service->lookup(-5);
        $this->assertEquals(['state' => 'none', 'bytes' => null], $resultNeg);
    }

    public function test_lookup_uses_cached_photo_if_fresh(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('koha-photos/10021.jpg', 'cached-jpeg-bytes');

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->never())->method('getPatronImage');

        $service = new KohaPhotoService($mockKoha);
        $result = $service->lookup(10021);

        $this->assertEquals('ready', $result['state']);
        $this->assertEquals('cached-jpeg-bytes', $result['bytes']);
        $this->assertNotNull($service->localPath(10021));
    }

    public function test_lookup_uses_cached_missing_marker_if_fresh(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('koha-photos/10021.none', (string) time());

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->never())->method('getPatronImage');

        $service = new KohaPhotoService($mockKoha);
        $result = $service->lookup(10021);

        $this->assertEquals('none', $result['state']);
        $this->assertNull($result['bytes']);
    }

    public function test_lookup_marks_missing_when_koha_returns_null(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getPatronImage')
            ->with(10021)
            ->willReturn(null);

        $service = new KohaPhotoService($mockKoha);
        $result = $service->lookup(10021);

        $this->assertEquals('none', $result['state']);
        $this->assertNull($result['bytes']);
        $disk->assertExists('koha-photos/10021.none');
    }

    public function test_lookup_falls_back_to_unavailable_when_koha_throws_and_no_cache(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getPatronImage')
            ->with(10021)
            ->willThrowException(new Exception('Network error'));

        $service = new KohaPhotoService($mockKoha);
        $result = $service->lookup(10021);

        $this->assertEquals('unavailable', $result['state']);
        $this->assertNull($result['bytes']);
    }

    public function test_lookup_falls_back_to_stale_cache_when_koha_throws(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('koha-photos/10021.jpg', 'stale-jpeg-bytes');

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getPatronImage')
            ->with(10021)
            ->willThrowException(new Exception('Network error'));

        $service = new KohaPhotoService($mockKoha);
        // Force refresh triggers download attempt which throws and falls back to disk
        $result = $service->lookup(10021, true);

        $this->assertEquals('ready', $result['state']);
        $this->assertEquals('stale-jpeg-bytes', $result['bytes']);
    }
}

<?php

namespace Tests\Feature\Commands;

use App\Models\PatronVerificationCapture;
use App\Services\KohaPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheKohaPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_on_non_numeric_patron_option(): void
    {
        $this->artisan('verification:cache-koha-photos --patron=abc')
            ->expectsOutputToContain('--patron must be a Koha patron id (digits only).')
            ->assertFailed();
    }

    public function test_warns_when_no_patrons_to_process(): void
    {
        $this->artisan('verification:cache-koha-photos')
            ->expectsOutputToContain('No patrons to process.')
            ->assertSuccessful();
    }

    public function test_processes_patron_from_option(): void
    {
        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('lookup')
            ->with(10021, false)
            ->willReturn([
                'state' => 'ready',
                'bytes' => 'binary-data-of-a-photo',
            ]);

        $this->app->instance(KohaPhotoService::class, $mockPhotos);

        $this->artisan('verification:cache-koha-photos --patron=10021')
            ->expectsOutputToContain('patron 10021: ready')
            ->assertSuccessful();
    }

    public function test_processes_distinct_patrons_from_captures_table(): void
    {
        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'p1.jpg',
        ]);
        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'return',
            'photo_path' => 'p2.jpg',
        ]);
        PatronVerificationCapture::create([
            'patron_id' => '10022',
            'action' => 'borrow',
            'photo_path' => 'p3.jpg',
        ]);

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->exactly(2))
            ->method('lookup')
            ->willReturn(['state' => 'none', 'bytes' => null]);

        $this->app->instance(KohaPhotoService::class, $mockPhotos);

        $this->artisan('verification:cache-koha-photos')
            ->expectsOutputToContain('patron 10021:')
            ->expectsOutputToContain('patron 10022:')
            ->assertSuccessful();
    }
}

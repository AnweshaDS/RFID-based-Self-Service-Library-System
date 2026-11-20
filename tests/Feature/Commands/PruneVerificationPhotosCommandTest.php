<?php

namespace Tests\Feature\Commands;

use App\Models\AppSetting;
use App\Models\PatronVerificationCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneVerificationPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('kiosk.photo_disk'));
    }

    public function test_fails_without_arguments(): void
    {
        $this->artisan('verification:prune')
            ->expectsOutputToContain('Give --scheduled, --card=CARDNUMBER, --older-than=DAYS, or a combination.')
            ->assertFailed();
    }

    public function test_fails_with_invalid_days_argument(): void
    {
        $this->artisan('verification:prune --older-than=abc')
            ->expectsOutputToContain('--older-than must be a whole number of days.')
            ->assertFailed();
    }

    public function test_dry_run_reports_candidate_count_without_deleting(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('patron-verification-captures/old.jpg', 'data');

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/old.jpg',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('verification:prune --older-than=30')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertDatabaseHas('patron_verification_captures', ['id' => $capture->id]);
        $disk->assertExists('patron-verification-captures/old.jpg');
    }

    public function test_force_deletes_files_and_records(): void
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('patron-verification-captures/old.jpg', 'data');

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/old.jpg',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('verification:prune --older-than=30 --force')
            ->expectsOutputToContain('Deleted 1 photo')
            ->assertSuccessful();

        $this->assertDatabaseMissing('patron_verification_captures', ['id' => $capture->id]);
        $disk->assertMissing('patron-verification-captures/old.jpg');
    }

    public function test_scheduled_prune_respects_protected_flagged_photos(): void
    {
        AppSetting::write('photo_retention_days', '30');
        AppSetting::write('flagged_retention_days', '180');

        $disk = Storage::disk(config('kiosk.photo_disk'));
        $disk->put('patron-verification-captures/normal_old.jpg', 'normal');
        $disk->put('patron-verification-captures/flagged_old.jpg', 'flagged');

        $normal = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/normal_old.jpg',
            'review_status' => 'ok',
            'created_at' => now()->subDays(40),
        ]);

        $flagged = PatronVerificationCapture::create([
            'patron_id' => '10022',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/flagged_old.jpg',
            'review_status' => 'flagged',
            'created_at' => now()->subDays(40),
        ]);

        $this->artisan('verification:prune --scheduled --force')
            ->assertSuccessful();

        // Normal photo deleted (40 > 30)
        $this->assertDatabaseMissing('patron_verification_captures', ['id' => $normal->id]);
        $disk->assertMissing('patron-verification-captures/normal_old.jpg');

        // Flagged photo retained (40 < 180)
        $this->assertDatabaseHas('patron_verification_captures', ['id' => $flagged->id]);
        $disk->assertExists('patron-verification-captures/flagged_old.jpg');
    }
}

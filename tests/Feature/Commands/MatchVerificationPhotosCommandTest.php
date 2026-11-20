<?php

namespace Tests\Feature\Commands;

use App\Models\PatronVerificationCapture;
use App\Services\FaceMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchVerificationPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_single_capture_by_id(): void
    {
        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
        ]);

        $mockMatcher = $this->createMock(FaceMatchService::class);
        $mockMatcher->expects($this->once())
            ->method('check')
            ->willReturnCallback(function (PatronVerificationCapture $c) {
                $c->match_score = 0.85;
                return 'matched';
            });

        $this->app->instance(FaceMatchService::class, $mockMatcher);

        $this->artisan("verification:match --id={$capture->id}")
            ->expectsOutputToContain('#' . $capture->id . ' patron 10021 STU001: matched (0.850)')
            ->expectsOutputToContain('Checked 1 photo(s).')
            ->assertSuccessful();
    }

    public function test_stops_when_koha_is_unavailable(): void
    {
        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
        ]);

        $mockMatcher = $this->createMock(FaceMatchService::class);
        $mockMatcher->expects($this->once())
            ->method('check')
            ->willReturn('skipped_koha');

        $this->app->instance(FaceMatchService::class, $mockMatcher);

        $this->artisan('verification:match')
            ->expectsOutputToContain('Koha could not be reached for a reference photo. Stopping; try again later.')
            ->assertSuccessful();
    }

    public function test_fails_when_matching_tool_reports_failure(): void
    {
        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
        ]);

        $mockMatcher = $this->createMock(FaceMatchService::class);
        $mockMatcher->expects($this->once())
            ->method('check')
            ->willReturn('failed');

        $this->app->instance(FaceMatchService::class, $mockMatcher);

        $this->artisan('verification:match')
            ->expectsOutputToContain('The Python matching tool failed.')
            ->assertFailed();
    }
}

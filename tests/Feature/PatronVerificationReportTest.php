<?php

namespace Tests\Feature;

use App\Models\PatronVerificationCapture;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatronVerificationReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthorizedUser(): User
    {
        $role = Role::create(['name' => 'Librarian']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createUnauthorizedUser(): User
    {
        $role = Role::create(['name' => 'Cataloger']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $user = $this->createUnauthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification.report'));
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_report_index(): void
    {
        $user = $this->createAuthorizedUser();

        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
            'match_status' => 'matched',
            'match_score' => 0.75,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('patron-verification.report'));

        $response->assertOk();
        $response->assertViewIs('patron-verification.report');
        $response->assertViewHasAll(['from', 'to', 'scopes', 'mainScopes', 'defaultChoice', 'counts', 'summary', 'maxDays']);
    }

    public function test_report_validates_date_range_not_in_reverse(): void
    {
        $user = $this->createAuthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification.report', [
            'from' => '2026-11-20',
            'to' => '2026-11-10',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_report_validates_maximum_days(): void
    {
        $user = $this->createAuthorizedUser();

        // 40 days apart (max is 31 days)
        $response = $this->actingAs($user)->get(route('patron-verification.report', [
            'from' => '2026-10-01',
            'to' => '2026-11-15',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_csv_export_returns_streamed_csv(): void
    {
        $user = $this->createAuthorizedUser();

        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'barcode' => 'BOOK100',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
            'match_status' => 'matched',
            'match_score' => 0.812,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('patron-verification.report.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('verification-report-', $response->headers->get('Content-Disposition'));
    }

    public function test_printable_report_renders_print_view(): void
    {
        $user = $this->createAuthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification.report.print'));

        $response->assertOk();
        $response->assertViewIs('patron-verification.report-print');
        $response->assertViewHasAll(['entries', 'summary', 'from', 'to', 'scope']);
    }
}

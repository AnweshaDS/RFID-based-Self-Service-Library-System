<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\PatronVerificationCapture;
use App\Models\Role;
use App\Models\User;
use App\Support\CheckoutFormatter;
use App\Support\PatronCategory;
use App\Support\PatronStatus;
use App\Support\ReceiptLines;
use App\Support\RetentionSettings;
use App\Support\VerificationPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportClassesTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_formatter_maps_fields_and_handles_fallbacks(): void
    {
        $rawCheckouts = [
            [
                'item' => [
                    'barcode' => 'B001',
                    'biblio' => [
                        'title' => 'The Pragmatic Programmer',
                        'author' => 'Andy Hunt',
                    ],
                ],
                'due_date' => '2026-12-15',
                'issuedate' => '2026-11-20',
            ],
            [
                'title' => 'Fallback Title',
                'author' => 'Fallback Author',
                'due' => '2026-12-10',
                'checkout_date' => '2026-11-15',
            ],
        ];

        $formatted = CheckoutFormatter::format($rawCheckouts);

        $this->assertCount(2, $formatted);
        $this->assertEquals('The Pragmatic Programmer', $formatted[0]['title']);
        $this->assertEquals('Andy Hunt', $formatted[0]['author']);
        $this->assertEquals('B001', $formatted[0]['barcode']);
        $this->assertEquals('2026-12-15', $formatted[0]['due']);
        $this->assertEquals('2026-11-20', $formatted[0]['checkout_date']);

        $this->assertEquals('Fallback Title', $formatted[1]['title']);
        $this->assertEquals('Fallback Author', $formatted[1]['author']);
        $this->assertEquals('N/A', $formatted[1]['barcode']);
    }

    public function test_patron_category_mappings_and_defaults(): void
    {
        config([
            'kiosk.categories' => [
                'UG' => ['label' => 'Undergraduate', 'max_books' => 3],
                'PG' => ['label' => 'Postgraduate', 'max_books' => 5],
            ],
            'kiosk.default_category' => ['label' => 'Standard Patron', 'max_books' => 2],
        ]);

        $ug = PatronCategory::for('ug'); // case insensitive
        $this->assertEquals('UG', $ug['code']);
        $this->assertEquals('Undergraduate', $ug['label']);
        $this->assertEquals(3, $ug['max_books']);
        $this->assertTrue($ug['mapped']);

        $unknown = PatronCategory::for('UNKNOWN');
        $this->assertEquals('Standard Patron', $unknown['label']);
        $this->assertEquals(2, $unknown['max_books']);
        $this->assertFalse($unknown['mapped']);
    }

    public function test_patron_status_logic(): void
    {
        $this->assertEquals('Restricted', PatronStatus::from(['restricted' => true]));

        $this->assertEquals('Expired', PatronStatus::from([
            'restricted' => false,
            'expiry_date' => '2020-01-01',
        ]));

        $this->assertEquals('Active', PatronStatus::from([
            'restricted' => false,
            'expiry_date' => '2099-01-01',
        ]));

        // Unparseable expiry date falls back to Active
        $this->assertEquals('Active', PatronStatus::from([
            'restricted' => false,
            'expiry_date' => 'invalid-date-string',
        ]));
    }

    public function test_receipt_lines_extraction(): void
    {
        // Multi-item receipt
        $multi = [
            'items' => [
                ['barcode' => 'B1', 'title' => 'Book 1', 'ok' => true],
                ['barcode' => 'B2', 'title' => 'Book 2', 'ok' => false],
                ['barcode' => 'B3', 'title' => 'Book 3', 'ok' => true],
            ],
        ];

        $res = ReceiptLines::from($multi);
        $this->assertCount(2, $res['lines']);
        $this->assertEquals(1, $res['failed']);

        // Legacy single-item receipt
        $single = [
            'title' => 'Single Book',
            'barcode' => 'B99',
        ];

        $resSingle = ReceiptLines::from($single);
        $this->assertCount(1, $resSingle['lines']);
        $this->assertEquals(0, $resSingle['failed']);
        $this->assertEquals('Single Book', $resSingle['lines']->first()['title']);
    }

    public function test_retention_settings_clamping_and_dependencies(): void
    {
        AppSetting::write('photo_retention_days', '10');
        AppSetting::write('flagged_retention_days', '15');

        $this->assertEquals(10, RetentionSettings::normalDays());
        $this->assertEquals(15, RetentionSettings::flaggedDays());

        // Value below minimum (min 7)
        AppSetting::write('photo_retention_days', '3');
        $this->assertEquals(7, RetentionSettings::normalDays());

        // Value above maximum (max 3650)
        AppSetting::write('photo_retention_days', '5000');
        $this->assertEquals(3650, RetentionSettings::normalDays());

        // Flagged days never shorter than normal days
        AppSetting::write('photo_retention_days', '100');
        AppSetting::write('flagged_retention_days', '50');
        $this->assertEquals(100, RetentionSettings::flaggedDays());
    }

    public function test_verification_photos_scopes_and_review_permission(): void
    {
        config([
            'koha_menu' => [
                'Admin' => [
                    'menu' => [['label' => 'Patrons']],
                ],
                'Cataloger' => [
                    'menu' => [['label' => 'Cataloging']],
                ],
            ],
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $catalogerRole = Role::create(['name' => 'Cataloger']);
        $cataloger = User::factory()->create();
        $cataloger->roles()->attach($catalogerRole->id);

        $this->assertTrue(VerificationPhotos::userCanReview($admin));
        $this->assertFalse(VerificationPhotos::userCanReview($cataloger));

        $unreviewedWarning = PatronVerificationCapture::create([
            'patron_id' => '100',
            'action' => 'borrow',
            'match_status' => 'mismatch',
            'photo_path' => 'p1.jpg',
        ]);

        $reviewedOk = PatronVerificationCapture::create([
            'patron_id' => '101',
            'action' => 'borrow',
            'match_status' => 'mismatch',
            'review_status' => 'ok',
            'photo_path' => 'p2.jpg',
        ]);

        $this->assertEquals(1, VerificationPhotos::pendingWarningCount());
        $this->assertEquals(1, VerificationPhotos::needsReview(PatronVerificationCapture::query())->count());
    }
}

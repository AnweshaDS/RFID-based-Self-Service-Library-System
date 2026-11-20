<?php

namespace Tests\Feature;

use App\Models\PatronVerificationCapture;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatronVerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthorizedUser(): User
    {
        $role = Role::create(['name' => 'Admin']);
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

    public function test_unauthorized_user_cannot_access_history(): void
    {
        $user = $this->createUnauthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification.history'));
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_history(): void
    {
        $user = $this->createAuthorizedUser();

        PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
        ]);

        $response = $this->actingAs($user)->get(route('patron-verification.history'));

        $response->assertOk();
        $response->assertViewIs('patron-verification.history');
        $response->assertViewHas('patrons');
    }

    public function test_authorized_user_can_view_single_capture(): void
    {
        $user = $this->createAuthorizedUser();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
        ]);

        $response = $this->actingAs($user)->get(route('patron-verification.history.show', $capture));

        $response->assertOk();
        $response->assertViewIs('patron-verification.capture');
        $response->assertViewHas('capture', fn ($c) => $c->id === $capture->id);
    }

    public function test_user_can_review_capture_as_ok(): void
    {
        $user = $this->createAuthorizedUser();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
            'match_status' => 'mismatch',
        ]);

        $response = $this->actingAs($user)->post(route('patron-verification.history.review', $capture), [
            'decision' => 'ok',
            'note' => 'Confirmed it is the patron wearing a hat',
        ]);

        $response->assertRedirect(route('patron-verification.history.show', $capture));
        $response->assertSessionHas('status', 'Marked as OK.');

        $capture->refresh();
        $this->assertEquals('ok', $capture->review_status);
        $this->assertEquals($user->id, $capture->reviewed_by);
        $this->assertNotNull($capture->reviewed_at);
        $this->assertEquals('Confirmed it is the patron wearing a hat', $capture->review_note);
    }

    public function test_user_can_review_capture_as_flagged(): void
    {
        $user = $this->createAuthorizedUser();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
        ]);

        $response = $this->actingAs($user)->post(route('patron-verification.history.review', $capture), [
            'decision' => 'flagged',
            'note' => 'Impersonation detected',
        ]);

        $response->assertRedirect(route('patron-verification.history.show', $capture));
        $response->assertSessionHas('status', 'Flagged as a problem.');

        $capture->refresh();
        $this->assertEquals('flagged', $capture->review_status);
        $this->assertEquals($user->id, $capture->reviewed_by);
    }

    public function test_user_can_reopen_reviewed_capture(): void
    {
        $user = $this->createAuthorizedUser();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'patron_name' => 'Arif Hasan',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/1.jpg',
            'face_detected' => true,
            'review_status' => 'flagged',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => 'Suspect note',
        ]);

        $response = $this->actingAs($user)->post(route('patron-verification.history.review', $capture), [
            'decision' => 'reopen',
        ]);

        $response->assertRedirect(route('patron-verification.history.show', $capture));
        $response->assertSessionHas('status', 'Photo re-opened for review.');

        $capture->refresh();
        $this->assertNull($capture->review_status);
        $this->assertNull($capture->reviewed_by);
        $this->assertNull($capture->reviewed_at);
        $this->assertNull($capture->review_note);
    }

    public function test_serving_capture_photo_streams_file_or_404_when_missing(): void
    {
        Storage::fake(config('kiosk.photo_disk'));
        $user = $this->createAuthorizedUser();

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/missing.jpg',
            'face_detected' => true,
        ]);

        // File does not exist yet -> 404
        $response404 = $this->actingAs($user)->get(route('patron-verification.history.photo', $capture));
        $response404->assertNotFound();

        // Create file in disk
        Storage::disk(config('kiosk.photo_disk'))->put($capture->photo_path, 'image-binary-data');

        $responseOk = $this->actingAs($user)->get(route('patron-verification.history.photo', $capture));
        $responseOk->assertOk();
        $this->assertEquals('image-binary-data', $responseOk->getContent());
    }
}

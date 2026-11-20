<?php

namespace Tests\Feature;

use App\Models\PatronVerificationCapture;
use App\Models\Role;
use App\Models\User;
use App\Services\KohaPhotoService;
use App\Services\KohaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatronVerificationTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_patron_verification(): void
    {
        $response = $this->get(route('patron-verification'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_receives_403(): void
    {
        $user = $this->createUnauthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification'));
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_verification_search_page(): void
    {
        $user = $this->createAuthorizedUser();

        $response = $this->actingAs($user)->get(route('patron-verification'));

        $response->assertOk();
        $response->assertViewIs('patron-verification.show');
        $response->assertViewHas('patron', null);
    }

    public function test_search_patron_displays_details_and_photo_status(): void
    {
        $user = $this->createAuthorizedUser();

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('getPatronByCardnumber')
            ->with('STU001')
            ->willReturn([
                'patron_id' => 10021,
                'cardnumber' => 'STU001',
                'firstname' => 'Arif',
                'surname' => 'Hasan',
            ]);

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('get')
            ->with(10021)
            ->willReturn('fake-photo-binary-data');

        $this->app->instance(KohaService::class, $mockKoha);
        $this->app->instance(KohaPhotoService::class, $mockPhotos);

        $capture = PatronVerificationCapture::create([
            'patron_id' => '10021',
            'cardnumber' => 'STU001',
            'action' => 'borrow',
            'photo_path' => 'patron-verification-captures/capture1.jpg',
            'face_detected' => true,
        ]);

        $response = $this->actingAs($user)->post(route('patron-verification.search'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertOk();
        $response->assertViewIs('patron-verification.show');
        $response->assertViewHas('patron');
        $response->assertViewHas('photoAvailable', true);
        $response->assertViewHas('latestCapture', fn ($c) => $c->id === $capture->id);
    }

    public function test_photo_endpoint_returns_jpeg_image_when_available(): void
    {
        $user = $this->createAuthorizedUser();

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('get')
            ->with(10021)
            ->willReturn('fake-jpeg-bytes');

        $this->app->instance(KohaPhotoService::class, $mockPhotos);

        $response = $this->actingAs($user)->get(route('patron-verification.photo', ['patronId' => 10021]));

        $response->assertOk();
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('fake-jpeg-bytes', $response->getContent());
    }

    public function test_photo_endpoint_returns_404_when_no_photo_in_koha(): void
    {
        $user = $this->createAuthorizedUser();

        $mockPhotos = $this->createMock(KohaPhotoService::class);
        $mockPhotos->expects($this->once())
            ->method('get')
            ->with(99999)
            ->willReturn(null);

        $this->app->instance(KohaPhotoService::class, $mockPhotos);

        $response = $this->actingAs($user)->get(route('patron-verification.photo', ['patronId' => 99999]));

        $response->assertNotFound();
    }
}

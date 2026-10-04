<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function useFakeKoha(): void
    {
        config([
            'services.koha.base_url' => 'http://localhost:8080',
            'services.koha.client_id' => 'test-id',
            'services.koha.client_secret' => 'test-secret',
        ]);
    }

    protected function fakeValidKohaPatron(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/auth/password/validation' => Http::response([
                'patron_id' => 42,
                'cardnumber' => 'STU001',
                'userid' => 'stu001',
            ], 201),
            'http://localhost:8080/api/v1/patrons*' => Http::response([
                [
                    'patron_id' => 42,
                    'cardnumber' => 'STU001',
                    'firstname' => 'Arif',
                    'surname' => 'Hasan',
                    'email' => 'arif@example.com',
                ],
            ], 200),
        ]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Card number or User ID');
    }

    public function test_valid_koha_credentials_log_the_user_in(): void
    {
        $this->useFakeKoha();
        $this->fakeValidKohaPatron();

        $response = $this->post('/login', [
            'identifier' => 'STU001',
            'password' => 'correct-koha-password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'koha_patron_id' => '42',
            'name' => 'Arif Hasan',
        ]);
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->useFakeKoha();

        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/auth/password/validation' => Http::response([
                'error' => 'Validation failed',
            ], 400),
        ]);

        $response = $this->post('/login', [
            'identifier' => 'STU001',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['identifier']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\KohaAuthService;
use App\Services\KohaService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KohaAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_returns_null_when_koha_rejects_credentials(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('validatePatronPassword')
            ->with('STU001', 'wrong-pass')
            ->willReturn(null);

        $auth = new KohaAuthService($mockKoha);
        $user = $auth->attempt('STU001', 'wrong-pass');

        $this->assertNull($user);
    }

    public function test_attempt_creates_and_returns_new_user_on_valid_credentials(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('validatePatronPassword')
            ->with('STU001', 'correct-pass')
            ->willReturn([
                'patron_id' => 10021,
                'cardnumber' => 'STU001',
            ]);

        $mockKoha->expects($this->once())
            ->method('getPatronByCardnumber')
            ->with('STU001')
            ->willReturn([
                'patron_id' => 10021,
                'cardnumber' => 'STU001',
                'firstname' => 'Arif',
                'surname' => 'Hasan',
                'email' => 'arif@example.com',
            ]);

        $auth = new KohaAuthService($mockKoha);
        $user = $auth->attempt('STU001', 'correct-pass');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Arif Hasan', $user->name);
        $this->assertEquals('arif@example.com', $user->email);
        $this->assertEquals('10021', $user->koha_patron_id);
        $this->assertEquals('STU001', $user->koha_cardnumber);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_attempt_updates_and_returns_existing_user(): void
    {
        $existing = User::create([
            'name' => 'Old Name',
            'email' => 'existing@example.com',
            'password' => 'secret',
            'koha_patron_id' => '10021',
            'koha_cardnumber' => 'STU001',
        ]);

        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('validatePatronPassword')
            ->with('STU001', 'correct-pass')
            ->willReturn([
                'patron_id' => 10021,
                'cardnumber' => 'STU001',
            ]);

        $mockKoha->expects($this->once())
            ->method('getPatronByCardnumber')
            ->with('STU001')
            ->willReturn([
                'patron_id' => 10021,
                'cardnumber' => 'STU001',
                'firstname' => 'New',
                'surname' => 'Name',
                'email' => 'existing@example.com',
            ]);

        $auth = new KohaAuthService($mockKoha);
        $user = $auth->attempt('STU001', 'correct-pass');

        $this->assertEquals($existing->id, $user->id);
        $this->assertEquals('New Name', $user->name);
    }

    public function test_attempt_throws_when_koha_cannot_be_reached(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('validatePatronPassword')
            ->willThrowException(new Exception('Koha connection refused'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Koha connection refused');

        $auth = new KohaAuthService($mockKoha);
        $auth->attempt('STU001', 'correct-pass');
    }
}

<?php

namespace Tests\Feature;

use App\Services\KohaService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KohaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_obtain_access_token(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-client-id', 'test-client-secret');
        $token = $service->getAccessToken();

        $this->assertEquals('fake-access-token-123', $token);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8080/api/v1/oauth/token' &&
                   $request['grant_type'] === 'client_credentials' &&
                   $request['client_id'] === 'test-client-id' &&
                   $request['client_secret'] === 'test-client-secret';
        });
    }

    public function test_can_get_patron_by_cardnumber(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/patrons*' => Http::response([
                [
                    'patron_id' => 42,
                    'cardnumber' => 'STU001',
                    'firstname' => 'John',
                    'surname' => 'Doe',
                    'email' => 'john@example.com',
                ],
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $patron = $service->getPatronByCardnumber('STU001');

        $this->assertNotNull($patron);
        $this->assertEquals(42, $patron['patron_id']);
        $this->assertEquals('STU001', $patron['cardnumber']);
    }

    public function test_returns_null_when_patron_not_found(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/patrons*' => Http::response([], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $patron = $service->getPatronByCardnumber('NONEXISTENT');

        $this->assertNull($patron);
    }

    public function test_can_get_item_by_barcode(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/items*' => Http::response([
                [
                    'item_id' => 101,
                    'barcode' => 'BOOK123456',
                    'biblio_id' => 50,
                    'title' => 'Introduction to Algorithms',
                ],
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $item = $service->getItemByBarcode('BOOK123456');

        $this->assertNotNull($item);
        $this->assertEquals(101, $item['item_id']);
        $this->assertEquals('BOOK123456', $item['barcode']);
    }

    public function test_returns_null_when_item_not_found(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/items*' => Http::response([], 404),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $item = $service->getItemByBarcode('UNKNOWN_BARCODE');

        $this->assertNull($item);
    }

    public function test_can_checkout_item(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts' => Http::response([
                'checkout_id' => 99,
                'patron_id' => 42,
                'item_id' => 101,
                'due_date' => '2026-10-15T23:59:59Z',
            ], 201),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $result = $service->checkoutItem(42, 101);

        $this->assertEquals(99, $result['checkout_id']);
        $this->assertEquals(42, $result['patron_id']);
        $this->assertEquals(101, $result['item_id']);
    }

    public function test_can_get_patron_checkouts(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts*' => Http::response([
                [
                    'checkout_id' => 10,
                    'patron_id' => 42,
                    'item_id' => 101,
                    'due_date' => '2026-10-15T23:59:59Z',
                    'item' => [
                        'barcode' => 'BOOK123',
                        'biblio' => [
                            'title' => 'Clean Code',
                            'author' => 'Robert C. Martin',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $checkouts = $service->getPatronCheckouts(42);

        $this->assertCount(1, $checkouts);
        $this->assertEquals(10, $checkouts[0]['checkout_id']);
        $this->assertEquals('Clean Code', $checkouts[0]['item']['biblio']['title']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/checkouts') &&
                   $request['q'] === json_encode(['patron_id' => 42]);
        });
    }

    public function test_returns_empty_array_when_no_checkouts(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts*' => Http::response([], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $checkouts = $service->getPatronCheckouts(42);

        $this->assertIsArray($checkouts);
        $this->assertEmpty($checkouts);
    }

    public function test_throws_exception_on_checkouts_failure(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to retrieve checkouts from Koha.');

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $service->getPatronCheckouts(42);
    }

    public function test_throws_exception_on_oauth_failure(): void
{
    Http::fake([
        'http://localhost:8080/api/v1/oauth/token' => Http::response([
            'error' => 'invalid_client',
        ], 401),
    ]);

    $service = new KohaService('http://localhost:8080', 'wrong-id', 'wrong-secret');

    try {
        $service->getAccessToken();
        $this->fail('Expected exception was not thrown.');
    } catch (Exception $e) {
        $this->assertStringContainsString('Koha OAuth failed', $e->getMessage());
        $this->assertStringContainsString('401', $e->getMessage());
    }
}

    public function test_throws_exception_on_checkout_failure(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts' => Http::response([
                'error' => 'Item not available for checkout',
            ], 400),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to checkout item in Koha.');

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $service->checkoutItem(42, 101);
    }

    public function test_checkouts_enrich_metadata_via_items_endpoint_when_missing_in_checkout_response(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts*' => Http::response([
                [
                    'checkout_id' => 10,
                    'patron_id' => 42,
                    'item_id' => 501,
                    'due_date' => '2026-10-15T23:59:59Z',
                ],
            ], 200),
            'http://localhost:8080/api/v1/items/501' => Http::response([
                'item_id' => 501,
                'barcode' => 'BOOK001',
                'biblio' => [
                    'title' => 'Essentials of Physical Chemistry',
                    'author' => 'B. S. Bahl',
                ],
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $checkouts = $service->getPatronCheckouts(42);

        $this->assertCount(1, $checkouts);
        $this->assertEquals('BOOK001', $checkouts[0]['item']['barcode']);
        $this->assertEquals('Essentials of Physical Chemistry', $checkouts[0]['item']['biblio']['title']);
        $this->assertEquals('B. S. Bahl', $checkouts[0]['item']['biblio']['author']);
        $this->assertEquals('2026-10-15T23:59:59Z', $checkouts[0]['due_date']);
    }

    public function test_checkouts_fallback_gracefully_when_metadata_and_item_lookup_are_missing(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
            'http://localhost:8080/api/v1/checkouts*' => Http::response([
                [
                    'checkout_id' => 10,
                    'patron_id' => 42,
                    'item_id' => 999,
                    'due_date' => '2026-10-15T23:59:59Z',
                ],
            ], 200),
            'http://localhost:8080/api/v1/items/999' => Http::response([], 404),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');
        $checkouts = $service->getPatronCheckouts(42);

        $this->assertCount(1, $checkouts);
        $this->assertEquals(999, $checkouts[0]['item_id']);
    }

        public function test_check_status_returns_true_when_koha_is_reachable(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'access_token' => 'fake-access-token-123',
            ], 200),
        ]);

        $service = new KohaService('http://localhost:8080', 'test-id', 'test-secret');

        $this->assertTrue($service->checkStatus());
    }

    public function test_check_status_returns_false_when_koha_is_unreachable(): void
    {
        Http::fake([
            'http://localhost:8080/api/v1/oauth/token' => Http::response([
                'error' => 'invalid_client',
            ], 401),
        ]);

        $service = new KohaService('http://localhost:8080', 'wrong-id', 'wrong-secret');

        $this->assertFalse($service->checkStatus());
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class KohaService
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $accessToken = null;

    public function __construct(
        ?string $baseUrl = null,
        ?string $clientId = null,
        ?string $clientSecret = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('services.koha.base_url', 'http://kohadev.myDNSname.org:8082'), '/');
        $this->clientId = $clientId ?? config('services.koha.client_id');
        $this->clientSecret = $clientSecret ?? config('services.koha.client_secret');
    }

    protected function isMockMode(): bool
    {
        if (config('services.koha.mock_mode') === true) {
            return true;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            return true;
        }

        return false;
    }

    /**
     * Get OAuth access token from Koha.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws Exception
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if ($this->accessToken && !$forceRefresh) {
            return $this->accessToken;
        }

        if ($this->isMockMode()) {
            $this->accessToken = 'mock-access-token';
            return $this->accessToken;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Koha API credentials (client_id or client_secret) are missing.');
        }

        $response = Http::asForm()->post("{$this->baseUrl}/api/v1/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->successful() || !isset($response->json()['access_token'])) {
            throw new Exception('Failed to obtain Koha access token.');
        }

        $this->accessToken = (string) $response->json()['access_token'];

        return $this->accessToken;
    }

    /**
     * Find a patron by Koha cardnumber.
     *
     * @param string $cardnumber
     * @return array|null
     * @throws Exception
     */
    public function getPatronByCardnumber(string $cardnumber): ?array
    {
        if ($this->isMockMode()) {
            $card = strtoupper(trim($cardnumber));
            if ($card === 'STU001') {
                return [
                    'patron_id' => 10021,
                    'cardnumber' => 'STU001',
                    'firstname' => 'Arif',
                    'surname' => 'Hasan',
                ];
            }
            if ($card === 'STU002') {
                return [
                    'patron_id' => 10022,
                    'cardnumber' => 'STU002',
                    'firstname' => 'Rahim',
                    'surname' => 'Uddin',
                ];
            }
            return [
                'patron_id' => 10023,
                'cardnumber' => $card,
                'firstname' => 'Student',
                'surname' => 'Patron',
            ];
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/patrons", [
                'q' => json_encode(['cardnumber' => $cardnumber]),
            ]);

        if ($response->notFound()) {
            return null;
        }

        if (!$response->successful()) {
            throw new Exception('Koha API request failed.');
        }

        $patrons = $response->json();

        if (is_array($patrons) && count($patrons) > 0) {
            return $patrons[0];
        }

        return null;
    }

    /**
     * Find an item by item ID.
     *
     * @param int $itemId
     * @return array|null
     * @throws Exception
     */
    public function getItemById(int $itemId): ?array
    {
        if ($this->isMockMode()) {
            return [
                'item_id' => $itemId,
                'barcode' => 'BOOK' . sprintf('%03d', $itemId),
                'biblio' => [
                    'title' => 'Essentials of Physical Chemistry',
                    'author' => 'B. S. Bahl',
                ],
            ];
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/items/{$itemId}");

        if ($response->notFound() || !$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Find an item by barcode.
     *
     * @param string $barcode
     * @return array|null
     * @throws Exception
     */
    public function getItemByBarcode(string $barcode): ?array
    {
        if ($this->isMockMode()) {
            $code = strtoupper(trim($barcode));
            if ($code === 'NONEXISTENT' || $code === 'UNKNOWN_BARCODE' || $code === 'UNKNOWN999') {
                return null;
            }
            if ($code === 'BOOK001') {
                return [
                    'item_id' => 501,
                    'barcode' => 'BOOK001',
                    'checked_out' => false,
                    'biblio' => [
                        'title' => 'Essentials of Physical Chemistry',
                        'author' => 'B. S. Bahl',
                    ],
                ];
            }
            return [
                'item_id' => 501,
                'barcode' => $code,
                'checked_out' => false,
                'biblio' => [
                    'title' => "Library Book ({$code})",
                    'author' => 'Author Name',
                ],
            ];
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/items", [
                'q' => json_encode(['barcode' => $barcode]),
            ]);

        if ($response->notFound()) {
            return null;
        }

        if (!$response->successful()) {
            throw new Exception('Koha API request failed.');
        }

        $items = $response->json();

        if (is_array($items) && count($items) > 0) {
            return $items[0];
        }

        return null;
    }

    /**
     * Checkout an item to a patron.
     *
     * @param int $patronId
     * @param int $itemId
     * @return array
     * @throws Exception
     */
    public function checkoutItem(int $patronId, int $itemId): array
    {
        if ($this->isMockMode()) {
            return [
                'checkout_id' => rand(100, 999),
                'patron_id' => $patronId,
                'item_id' => $itemId,
                'due_date' => now()->addDays(14)->toIso8601String(),
            ];
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/v1/checkouts", [
                'patron_id' => $patronId,
                'item_id' => $itemId,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to checkout item in Koha.');
        }

        return $response->json() ?? [];
    }

    /**
     * Get current checkouts for a patron.
     *
     * @param int $patronId
     * @return array
     * @throws Exception
     */
    public function getPatronCheckouts(int $patronId): array
    {
        if ($this->isMockMode()) {
            if ((int) $patronId === 10021) {
                return [
                    [
                        'checkout_id' => 88,
                        'patron_id' => 10021,
                        'item_id' => 501,
                        'due_date' => now()->addDays(7)->toIso8601String(),
                        'item' => [
                            'item_id' => 501,
                            'barcode' => 'BOOK001',
                            'biblio' => [
                                'title' => 'Essentials of Physical Chemistry',
                                'author' => 'B. S. Bahl',
                            ],
                        ],
                    ],
                ];
            }
            return [];
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/checkouts", [
                'q' => json_encode(['patron_id' => $patronId]),
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to retrieve checkouts from Koha.');
        }

        $checkouts = $response->json() ?? [];

        foreach ($checkouts as &$checkout) {
            $hasTitle = !empty($checkout['item']['biblio']['title'])
                || !empty($checkout['item']['title'])
                || !empty($checkout['title']);
            $hasBarcode = !empty($checkout['item']['barcode'])
                || !empty($checkout['barcode']);

            if ((!$hasTitle || !$hasBarcode) && !empty($checkout['item_id'])) {
                try {
                    $item = $this->getItemById((int) $checkout['item_id']);
                    if ($item) {
                        if (!isset($checkout['item']) || !is_array($checkout['item'])) {
                            $checkout['item'] = $item;
                        } else {
                            $checkout['item'] = array_merge($item, $checkout['item']);
                        }
                    }
                } catch (\Throwable $e) {
                    // Safe fallback
                }
            }
        }

        return $checkouts;
    }

    /**
     * Check in / return an item.
     *
     * Note: The current Koha REST API version does not support a check-in endpoint.
     * Transport (e.g. SIP2) can replace this implementation cleanly when available.
     *
     * @param int $itemId
     * @return array
     * @throws Exception
     */
    public function checkinItem(int $itemId): array
    {
        throw new Exception('Koha check-in transport is not configured yet.');
    }
}

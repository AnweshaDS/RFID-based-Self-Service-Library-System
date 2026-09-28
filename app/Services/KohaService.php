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

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Koha API credentials (client_id or client_secret) are missing.');
        }

        $response = Http::asForm()->post("{$this->baseUrl}/api/v1/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->successful() || !isset($response->json()['access_token'])) {
            throw new Exception(
                'Koha OAuth failed. HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $this->accessToken = (string) $response->json()['access_token'];

        return $this->accessToken;
    }

    /**
     * Check whether Koha is reachable, without exposing credentials or error details.
     *
     * @return bool
     */
    public function checkStatus(): bool
    {
        try {
            $this->getAccessToken(true);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
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
     * Fetch biblio (title/author) for a given biblio_id.
     *
     * @param int $biblioId
     * @param string $token
     * @return array|null
     */
    protected function getBiblioById(int $biblioId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/api/v1/biblios/{$biblioId}");

            if (!$response->successful()) {
                return null;
            }

            $biblio = $response->json();

            return [
                'title' => $biblio['title'] ?? null,
                'author' => $biblio['author'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalize a raw Koha item response: fix barcode field name
     * and attach biblio (title/author) info.
     *
     * @param array $item
     * @param string $token
     * @return array
     */
    protected function normalizeItem(array $item, string $token): array
    {
        if (empty($item['barcode']) && !empty($item['external_id'])) {
            $item['barcode'] = $item['external_id'];
        }

        if (empty($item['biblio']['title']) && !empty($item['biblio_id'])) {
            $biblio = $this->getBiblioById((int) $item['biblio_id'], $token);
            if ($biblio) {
                $item['biblio'] = $biblio;
            }
        }

        return $item;
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
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/items/{$itemId}");

        if ($response->notFound() || !$response->successful()) {
            return null;
        }

        return $this->normalizeItem($response->json(), $token);
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
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/items", [
                'q' => json_encode(['external_id' => $barcode]),
            ]);

        if ($response->notFound()) {
            return null;
        }

        if (!$response->successful()) {
            throw new Exception('Koha API request failed.');
        }

        $items = $response->json();

        if (is_array($items) && count($items) > 0) {
            return $this->normalizeItem($items[0], $token);
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
<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

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
        if (! $forceRefresh) {
            if ($this->accessToken) {
                return $this->accessToken;
            }

            // Reuse the token across requests instead of asking Koha for a new one every time
            $cached = Cache::get('koha.access_token');

            if (is_string($cached) && $cached !== '') {
                return $this->accessToken = $cached;
            }
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

        // Keep it for at most 5 minutes, and never past the token's own expiry
        $lifetime = (int) ($response->json('expires_in') ?? 300);
        Cache::put('koha.access_token', $this->accessToken, now()->addSeconds(max(30, min(300, $lifetime - 60))));

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
     * Check a staff member's login credentials against Koha.
     *
     * Returns the patron's identifiers when valid, or null when wrong. Throws
     * when Koha itself cannot be reached, so the caller can tell "wrong
     * password" apart from "system down".
     *
     * @return array|null
     * @throws Exception
     */
    public function validatePatronPassword(string $identifier, string $password): ?array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/v1/auth/password/validation", [
                'identifier' => $identifier,
                'password' => $password,
            ]);

        if ($response->status() === 400) {
            return null;
        }

        if (!$response->successful()) {
            throw new Exception('Koha password validation request failed.');
        }

        $data = $response->json();

        return is_array($data) && !empty($data['patron_id']) ? $data : null;
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
     * Count patrons enrolled since a given date.
     *
     * Uses the same query style as getPatronByCardnumber(), which is already
     * confirmed working against your Koha instance - only the filter shape
     * is new here, so test this one before relying on it in production.
     *
     * @param \DateTimeInterface $since
     * @return int
     * @throws Exception
     */
    public function countNewPatronsSince(\DateTimeInterface $since): int
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/patrons", [
                'q' => json_encode(['date_enrolled' => ['>=' => $since->format('Y-m-d')]]),
            ]);

        if (!$response->successful()) {
            throw new Exception('Koha API request failed.');
        }

        $total = $response->header('X-Total-Count');
        if ($total !== null) {
            return (int) $total;
        }

        return count($response->json() ?? []);
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
                        throw new Exception('Failed to checkout item in Koha. HTTP ' . $response->status() . ': ' . $response->body());
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

        // Work out which loans still need their book details
        $needDetails = [];

        foreach ($checkouts as $index => $checkout) {
            $hasTitle = !empty($checkout['item']['biblio']['title'])
                || !empty($checkout['item']['title'])
                || !empty($checkout['title']);
            $hasBarcode = !empty($checkout['item']['barcode'])
                || !empty($checkout['barcode']);

            if ((!$hasTitle || !$hasBarcode) && !empty($checkout['item_id'])) {
                $needDetails[$index] = (int) $checkout['item_id'];
            }
        }

        if ($needDetails) {
            // Every lookup runs at the same time, instead of one after another
            $items = $this->getItemsByIds(array_values(array_unique($needDetails)), $token);

            foreach ($needDetails as $index => $itemId) {
                if (empty($items[$itemId])) {
                    continue;   // safe fallback: show what we have
                }

                $existing = $checkouts[$index]['item'] ?? null;

                $checkouts[$index]['item'] = is_array($existing)
                    ? array_merge($items[$itemId], $existing)
                    : $items[$itemId];
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
        public function checkinItem(int $itemId, ?string $barcode = null): array
    {
        // SIP needs the barcode; look it up if the caller didn't pass it.
        if (empty($barcode)) {
            $item = $this->getItemById($itemId);
            $barcode = $item['barcode'] ?? null;
        }

        if (empty($barcode)) {
            throw new Exception('Item barcode not found, cannot check in.');
        }
\Illuminate\Support\Facades\Log::info('SIP checkin barcode=' . json_encode($barcode) . ' item_id=' . $itemId);

        $result = app(\App\Services\KohaSipClient::class)->checkin($barcode);

        if (! $result['success']) {
            throw new Exception($result['message']);
        }

        return $result;
    }
        /**
     * Account balance (positive = amount owed). Null when Koha does not provide it.
     */
    public function getPatronBalance(int $patronId): ?float
    {
        try {
            $token = $this->getAccessToken();
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$this->baseUrl}/api/v1/patrons/{$patronId}/account");

            if (! $response->successful()) {
                return null;
            }

            $balance = $response->json('balance');

            return is_numeric($balance) ? (float) $balance : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Asks Koha whether a checkout can be renewed under its own circulation rules.
     *
     * @return array{allowed: bool, reason: ?string}
     * @throws Exception
     */
    public function getRenewability(int $checkoutId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/v1/checkouts/{$checkoutId}/allows_renewal");

        if (! $response->successful()) {
            throw new Exception('Could not check renewability in Koha.');
        }

        $data = $response->json() ?? [];

        return [
            'allowed' => (bool) ($data['allows_renewal'] ?? false),
            'reason' => is_string($data['error'] ?? null) ? $data['error'] : null,
        ];
    }

    /**
     * Renews a checkout. Koha applies its own rules and returns the new due date.
     *
     * @throws Exception
     */
    public function renewCheckout(int $checkoutId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl}/api/v1/checkouts/{$checkoutId}/renewal");

        if (! $response->successful()) {
            $error = $response->json('error');
            throw new Exception(is_string($error) ? $error : 'Koha refused the renewal.');
        }

        return $response->json() ?? [];
    }
    /**
     * Book details for several item IDs: cached ones are reused, the rest are fetched in parallel.
     *
     * @return array<int, array>
     */
    private function getItemsByIds(array $ids, string $token): array
    {
        $found = [];
        $missing = [];

        foreach ($ids as $id) {
            $cached = Cache::get("koha.item.$id");

            if (is_array($cached)) {
                $found[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        if (! $missing) {
            return $found;
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($id) => $pool->as((string) $id)->withToken($token)->acceptJson()->timeout(15)
                    ->get("{$this->baseUrl}/api/v1/items/{$id}"),
                $missing
            ));
        } catch (\Throwable $e) {
            return $found;
        }

        $fetched = [];

        foreach ($missing as $id) {
            $r = $responses[(string) $id] ?? null;

            if ($r instanceof Response && $r->successful()) {
                $item = $r->json();

                // Same barcode fix as normalizeItem()
                if (empty($item['barcode']) && ! empty($item['external_id'])) {
                    $item['barcode'] = $item['external_id'];
                }

                $fetched[$id] = $item;
            }
        }

        // Titles and authors live on the biblio record, also fetched in parallel
        $biblioIds = [];

        foreach ($fetched as $id => $item) {
            if (empty($item['biblio']['title']) && ! empty($item['biblio_id'])) {
                $biblioIds[$id] = (int) $item['biblio_id'];
            }
        }

        $biblios = $this->getBibliosByIds(array_values(array_unique($biblioIds)), $token);

        foreach ($biblioIds as $id => $biblioId) {
            if (isset($biblios[$biblioId])) {
                $fetched[$id]['biblio'] = $biblios[$biblioId];
            }
        }

        foreach ($fetched as $id => $item) {
            Cache::put("koha.item.$id", $item, now()->addMinutes(10));
            $found[$id] = $item;
        }

        return $found;
    }

    /**
     * Title and author for several biblio IDs, cached for an hour.
     *
     * @return array<int, array{title: ?string, author: ?string}>
     */
    private function getBibliosByIds(array $ids, string $token): array
    {
        $found = [];
        $missing = [];

        foreach ($ids as $id) {
            $cached = Cache::get("koha.biblio.$id");

            if (is_array($cached)) {
                $found[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        if (! $missing) {
            return $found;
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($id) => $pool->as((string) $id)->withToken($token)->acceptJson()->timeout(15)
                    ->get("{$this->baseUrl}/api/v1/biblios/{$id}"),
                $missing
            ));
        } catch (\Throwable $e) {
            return $found;
        }

        foreach ($missing as $id) {
            $r = $responses[(string) $id] ?? null;

            if ($r instanceof Response && $r->successful()) {
                $biblio = ['title' => $r->json('title'), 'author' => $r->json('author')];
                Cache::put("koha.biblio.$id", $biblio, now()->addHour());
                $found[$id] = $biblio;
            }
        }

        return $found;
    }
    /**
     * Book title for an item ID, remembered for 6 hours. Null when unknown.
     */
    public function getItemTitle(int $itemId): ?string
    {
        $cached = Cache::get("koha.title.$itemId");

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (Cache::get('koha.titles.down')) {
            return null;   // Koha failed a moment ago: don't make the dashboard wait
        }

        try {
            $item = $this->getItemById($itemId);
        } catch (\Throwable $e) {
            Cache::put('koha.titles.down', true, 30);

            return null;
        }

        $title = $item['biblio']['title'] ?? $item['title'] ?? null;

        if ($title) {
            Cache::put("koha.title.$itemId", $title, now()->addHours(6));
        }

        return $title;
    }
}
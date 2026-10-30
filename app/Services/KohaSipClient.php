<?php

namespace App\Services;

use RuntimeException;

class KohaSipClient
{
    private $socket;

    /** Hard ceiling for the entire operation (connect + login + checkin), in seconds. */
    private const TOTAL_TIMEOUT = 15;

    private float $deadline;

    private function connect(): void
    {
        $cfg = config('services.koha_sip');

        $this->deadline = microtime(true) + self::TOTAL_TIMEOUT;

        $connectTimeout = $this->remaining();
        if ($connectTimeout <= 0) {
            throw new RuntimeException('SIP operation timed out before connecting.');
        }

        $this->socket = @stream_socket_client(
            "tcp://{$cfg['host']}:{$cfg['port']}", $errno, $errstr, $connectTimeout
        );
        if (!$this->socket) {
            throw new RuntimeException("SIP connect failed: $errstr ($errno)");
        }

        try {
            $reply = $this->send("9300CN{$cfg['user']}|CO{$cfg['password']}|CP{$cfg['institution']}|");
        } catch (RuntimeException $e) {
            throw new RuntimeException('login step: ' . $e->getMessage());
        }
        if (!str_starts_with($reply, '941')) {
            throw new RuntimeException('SIP login failed');
        }
    }

    /**
     * Seconds left on the total operation deadline, never negative.
     */
    private function remaining(): float
    {
        return max(0, $this->deadline - microtime(true));
    }

    private function send(string $message): string
    {
        $remaining = $this->remaining();
        if ($remaining <= 0) {
            throw new RuntimeException('SIP operation timed out.');
        }

        // Bound this specific read to whatever's left of the total budget
        stream_set_timeout($this->socket, (int) ceil($remaining));

        fwrite($this->socket, $message . "\r");

        do {
            if ($this->remaining() <= 0) {
                throw new RuntimeException('SIP operation timed out.');
            }

            $line = fgets($this->socket);
            $meta = stream_get_meta_data($this->socket);

            if ($meta['timed_out'] ?? false) {
                throw new RuntimeException('No reply from SIP server (timed out)');
            }

            if ($line === false) {
                throw new RuntimeException('No reply from SIP server');
            }

            $line = trim($line);
        } while ($line === '');

        return $line;
    }

    private function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function field(string $reply, string $code): ?string
    {
        foreach (explode('|', $reply) as $part) {
            if (str_starts_with($part, $code)) {
                return substr($part, 2);
            }
        }
        return null;
    }

    public function checkin(string $itemBarcode): array
    {
        $cfg = config('services.koha_sip');
        $date = date('Ymd') . '    ' . date('His');

        $this->connect();
        try {
            $reply = $this->send(
                "09N{$date}{$date}AP{$cfg['institution']}|AO{$cfg['institution']}|AB{$itemBarcode}|AC{$cfg['user']}|"
            );
        } catch (RuntimeException $e) {
            throw new RuntimeException('checkin step: ' . $e->getMessage());
        } finally {
            $this->close();
        }

        $ok = str_starts_with($reply, '101');

        return [
            'success' => $ok,
            'title'   => $this->field($reply, 'AJ'),
            'patron'  => $this->field($reply, 'AA'),
            'message' => $ok ? 'Item checked in' : ($this->field($reply, 'AF') ?? 'Check-in failed'),
            'raw'     => $reply,
        ];
    }
}
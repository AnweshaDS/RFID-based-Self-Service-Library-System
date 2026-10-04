<?php

namespace App\Services;

use RuntimeException;

class KohaSipClient
{
    private $socket;

    private function connect(): void
    {
        $cfg = config('services.koha_sip');

        $this->socket = @stream_socket_client(
            "tcp://{$cfg['host']}:{$cfg['port']}", $errno, $errstr, 5
        );
        if (!$this->socket) {
            throw new RuntimeException("SIP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 10);

        $reply = $this->send("9300CN{$cfg['user']}|CO{$cfg['password']}|CP{$cfg['institution']}|");
        if (!str_starts_with($reply, '941')) {
            throw new RuntimeException('SIP login failed');
        }
    }

    private function send(string $message): string
    {
        fwrite($this->socket, $message . "\r");
        do {
            $line = fgets($this->socket);
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

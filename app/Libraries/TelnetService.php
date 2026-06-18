<?php

namespace App\Libraries;

class TelnetService
{
    private $socket = null;
    private int $timeout = 5;
    private string $buffer = '';

    // Telnet IAC codes
    const IAC  = "\xFF";
    const DONT = "\xFE";
    const DO   = "\xFD";
    const WONT = "\xFC";
    const WILL = "\xFB";
    const SB   = "\xFA";
    const SE   = "\xF0";

    public function connect(string $host, int $port, string $username, string $password): void
    {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new \Exception("Tidak bisa konek ke {$host}:{$port} — {$errstr} ({$errno})");
        }
        stream_set_timeout($this->socket, $this->timeout);

        // Proses negosiasi IAC telnet
        $this->handleNegotiation();

        // Login
        $prompt = $this->waitFor(['Username:', 'login:', 'user:'], 10);
        if (empty(trim($prompt))) {
            throw new \Exception("Tidak ada prompt login dari OLT");
        }

        $this->send($username);
        $this->waitFor(['Password:', 'password:'], 5);
        $this->send($password);

        $response = $this->waitFor(['#', '>', 'fail', 'incorrect', 'denied', 'bad'], 6);
        if (
            stripos($response, 'fail') !== false ||
            stripos($response, 'incorrect') !== false ||
            stripos($response, 'denied') !== false
        ) {
            throw new \Exception("Autentikasi gagal. Periksa username/password OLT.");
        }
    }

    private function handleNegotiation(): void
    {
        $raw = '';
        $start = microtime(true);

        while (microtime(true) - $start < 3) {
            $byte = @fread($this->socket, 1);
            if ($byte === false || $byte === '') {
                usleep(50000);
                continue;
            }

            if ($byte === self::IAC) {
                $cmd = @fread($this->socket, 1);
                if ($cmd === self::DO || $cmd === self::WILL) {
                    $opt = @fread($this->socket, 1);
                    // Tolak semua opsi
                    fwrite($this->socket, self::IAC . ($cmd === self::DO ? self::WONT : self::DONT) . $opt);
                } elseif ($cmd === self::DONT || $cmd === self::WONT) {
                    @fread($this->socket, 1);
                } elseif ($cmd === self::SB) {
                    // Baca sampai IAC SE
                    while (true) {
                        $b = @fread($this->socket, 1);
                        if ($b === self::IAC && @fread($this->socket, 1) === self::SE) break;
                    }
                }
            } else {
                $raw .= $byte;
                // Cek apakah sudah ada prompt login
                if (
                    stripos($raw, 'Username:') !== false ||
                    stripos($raw, 'login:') !== false ||
                    stripos($raw, 'user:') !== false
                ) {
                    $this->buffer = $raw;
                    return;
                }
            }
        }
        $this->buffer = $raw;
    }

    public function waitFor(array $prompts, int $timeout = 5): string
    {
        $output = $this->buffer;
        $this->buffer = '';
        $start = microtime(true);

        while (microtime(true) - $start < $timeout) {
            $chunk = @fread($this->socket, 4096);
            if ($chunk !== false && $chunk !== '') {
                $output .= $this->stripIac($chunk);
                foreach ($prompts as $prompt) {
                    if (stripos($output, $prompt) !== false) {
                        return $output;
                    }
                }
            }
            usleep(100000); // 100ms
        }
        return $output;
    }

    public function send(string $cmd): void
    {
        fwrite($this->socket, $cmd . "\r\n");
    }

    public function execute(string $cmd, array $waitPrompts = ['#'], int $timeout = 5): string
    {
        $this->send($cmd);
        usleep(300000); // 300ms jeda setelah kirim command
        return $this->waitFor($waitPrompts, $timeout);
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "exit\r\n");
            // ZTE C320 menampilkan "confirm to logout without saving? [yes/no]:" setelah exit.
            // write sudah dijalankan sebelumnya — jawab yes agar sesi tertutup bersih.
            $response = $this->waitFor(['[yes/no]', 'Username:', 'login:', 'user:'], 3);
            if (stripos($response, '[yes/no]') !== false) {
                @fwrite($this->socket, "yes\r\n");
                usleep(300000);
            }
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && !feof($this->socket);
    }

    private function stripIac(string $data): string
    {
        // Hapus sequence IAC (3 byte: FF + cmd + opt)
        return preg_replace('/\xFF[\xFB-\xFF][\x00-\xFF]/', '', $data);
    }
}

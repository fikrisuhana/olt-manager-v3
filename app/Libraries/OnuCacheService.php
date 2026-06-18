<?php

namespace App\Libraries;

/**
 * Cache data ONU per OLT ke file JSON di writable/onu_cache/
 *
 * Format file: olt_{id}.json
 * Isi:
 * {
 *   "olt_id": 1,
 *   "updated_at": "2024-01-01 12:00:00",
 *   "ports": {
 *     "1/1/1": [
 *       {"index": 1, "sn": "FHTT12345678", "type": "ALL-ONT", "name": "Pelanggan A"},
 *       ...
 *     ]
 *   }
 * }
 */
class OnuCacheService
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = WRITEPATH . 'onu_cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function cachePath(int $oltId): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . "olt_{$oltId}.json";
    }

    /**
     * Simpan hasil getRegisteredOnus() ke cache.
     * $onus = array dari driver (field: board, slot, port, index, sn, type, name, status)
     */
    public function save(int $oltId, array $onus): void
    {
        $ports = [];
        foreach ($onus as $onu) {
            $portKey = "{$onu['board']}/{$onu['slot']}/{$onu['port']}";
            // Driver mengembalikan 'onu_index' (bukan 'index')
            $index   = (int)($onu['onu_index'] ?? $onu['index'] ?? 0);
            $ports[$portKey][] = [
                'index'  => $index,
                'sn'     => $onu['sn'],
                'type'   => $onu['onu_type'] ?? $onu['type'] ?? 'ALL-ONT',
                'name'   => $onu['name'] ?? '',
                'status' => $onu['status'] ?? 'unknown',
            ];
        }

        // Urutkan index di setiap port
        foreach ($ports as &$list) {
            usort($list, fn($a, $b) => $a['index'] <=> $b['index']);
        }

        $data = [
            'olt_id'     => $oltId,
            'updated_at' => date('Y-m-d H:i:s'),
            'ports'      => $ports,
        ];

        file_put_contents($this->cachePath($oltId), json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Tambahkan satu ONU baru ke cache (setelah register berhasil).
     * Dipanggil oleh OnuController::register() supaya tidak perlu scan ulang.
     */
    public function addOnu(int $oltId, string $board, string $slot, string $port, int $index, string $sn, string $type = 'ALL-ONT', string $name = ''): void
    {
        $data = $this->load($oltId);
        $portKey = "{$board}/{$slot}/{$port}";

        $data['ports'][$portKey][] = [
            'index' => $index,
            'sn'    => $sn,
            'type'  => $type,
            'name'  => $name,
        ];

        usort($data['ports'][$portKey], fn($a, $b) => $a['index'] <=> $b['index']);
        $data['updated_at'] = date('Y-m-d H:i:s');

        file_put_contents($this->cachePath($oltId), json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Hapus satu ONU dari cache (setelah delete ONU).
     */
    public function removeOnu(int $oltId, string $board, string $slot, string $port, int $index): void
    {
        $data = $this->load($oltId);
        $portKey = "{$board}/{$slot}/{$port}";

        if (isset($data['ports'][$portKey])) {
            $data['ports'][$portKey] = array_values(
                array_filter($data['ports'][$portKey], fn($o) => (int)$o['index'] !== $index)
            );
            $data['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($this->cachePath($oltId), json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load cache. Kalau belum ada file, return struktur kosong.
     */
    public function load(int $oltId): array
    {
        $path = $this->cachePath($oltId);
        if (!file_exists($path)) {
            return ['olt_id' => $oltId, 'updated_at' => null, 'ports' => []];
        }
        $data = json_decode(file_get_contents($path), true);
        return $data ?: ['olt_id' => $oltId, 'updated_at' => null, 'ports' => []];
    }

    /**
     * Ambil semua ONU di satu port dari cache.
     * Return array [{index, sn, type, name}]
     */
    public function getOnusByPort(int $oltId, string $board, string $slot, string $port): array
    {
        $data    = $this->load($oltId);
        $portKey = "{$board}/{$slot}/{$port}";
        return $data['ports'][$portKey] ?? [];
    }

    /**
     * Hitung index ONU berikutnya untuk port tertentu.
     * Ambil max(index) dari cache + 1. Kalau kosong, mulai dari 1.
     */
    public function nextIndex(int $oltId, string $board, string $slot, string $port): int
    {
        $onus = $this->getOnusByPort($oltId, $board, $slot, $port);
        if (empty($onus)) return 1;
        return max(array_column($onus, 'index')) + 1;
    }

    /**
     * Cek apakah SN sudah ada di cache OLT ini (di port mana pun).
     */
    public function snExists(int $oltId, string $sn): bool
    {
        $data = $this->load($oltId);
        foreach ($data['ports'] as $list) {
            foreach ($list as $o) {
                if (strcasecmp($o['sn'], $sn) === 0) return true;
            }
        }
        return false;
    }

    /**
     * Kapan terakhir cache di-refresh dari OLT.
     */
    public function lastUpdated(int $oltId): ?string
    {
        return $this->load($oltId)['updated_at'];
    }

    /**
     * Hapus cache (force refresh dari OLT).
     */
    public function clear(int $oltId): void
    {
        $path = $this->cachePath($oltId);
        if (file_exists($path)) unlink($path);
    }

    // ── ACS cache (terpisah dari OLT cache) ──────────────────────────

    private function acsCachePath(int $oltId): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . "olt_{$oltId}_acs.json";
    }

    /**
     * Simpan hasil ACS batch query ke cache.
     * $devices = hasil AcsService::getDevicesBySns() — keyed by SN uppercase
     */
    public function saveAcs(int $oltId, array $devices): void
    {
        file_put_contents(
            $this->acsCachePath($oltId),
            json_encode(['updated_at' => date('Y-m-d H:i:s'), 'devices' => $devices], JSON_PRETTY_PRINT)
        );
    }

    public function loadAcs(int $oltId): array
    {
        $path = $this->acsCachePath($oltId);
        if (!file_exists($path)) return ['updated_at' => null, 'devices' => []];
        return json_decode(file_get_contents($path), true) ?: ['updated_at' => null, 'devices' => []];
    }

    public function lastUpdatedAcs(int $oltId): ?string
    {
        return $this->loadAcs($oltId)['updated_at'];
    }
}

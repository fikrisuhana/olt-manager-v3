<?php

namespace App\Libraries;

/**
 * Kebijakan ACS mandiri — gpon-manager mengendalikan GenieACS langsung lewat NBI,
 * tanpa ext / sinkronisasi ke layanan luar.
 *
 * Tiap fitur = 1 provision (kode di app/Libraries/AcsProvisions/<nama>.js) + 1 preset
 * dengan nama sama. Fitur aktif → preset dibuat/ditimpa dengan args dari pengaturan;
 * fitur mati → preset dihapus (provision tetap ada, tidak berjalan).
 * Hanya preset/provision berawalan "gm-" yang disentuh; preset lain dibiarkan.
 */
class AcsPolicyService
{
    public const PREFIX = 'gm-';

    public const DEFAULTS = [
        'inform' => [
            'enabled'  => true,
            'interval' => 300,   // detik
            'cr_user'  => '',
            'cr_pass'  => '',
            'acs_url'  => '',    // kosong = URL ACS di modem tidak disentuh
        ],
        'refresh' => [
            'enabled'      => true,
            'interval_min' => 60,
        ],
        'credentials' => [
            'enabled'        => false,
            'user_name'      => 'user',
            'user_pass'      => '',
            'admin_name'     => 'admin',
            'admin_pass'     => '',
            // Model yang terbukti menolak/mengabaikan perubahan akun (dari pengalaman fleet LDP)
            'exclude_models' => 'HG6145F, HG6245N, HG6243C, F663NV3a, H1s-3, F609, HT803G-WS2',
        ],
    ];

    /** Gabungkan pengaturan tersimpan dengan default (kunci baru otomatis terisi). */
    public static function withDefaults(?array $settings): array
    {
        $out = self::DEFAULTS;
        foreach ($out as $group => $vals) {
            foreach ($vals as $k => $v) {
                if (isset($settings[$group][$k])) {
                    $out[$group][$k] = $settings[$group][$k];
                }
            }
        }
        return $out;
    }

    /**
     * Baca & validasi input form. Field password kosong = pakai nilai lama (tidak dihapus).
     * @return array{0: array, 1: string[]} [settings, errors]
     */
    public static function fromPost(array $post, array $current): array
    {
        $errors = [];
        $s      = self::withDefaults($current);
        $g      = fn(string $k, $def = '') => trim((string)($post[$k] ?? $def));

        // Inform & Connection Request
        $s['inform']['enabled']  = !empty($post['inform_enabled']);
        $s['inform']['interval'] = (int)$g('inform_interval', 300);
        $s['inform']['cr_user']  = $g('cr_user');
        if ($g('cr_pass') !== '')        $s['inform']['cr_pass'] = $g('cr_pass');
        if (!empty($post['cr_pass_clear'])) $s['inform']['cr_pass'] = '';
        $s['inform']['acs_url']  = rtrim($g('acs_url'), '/');
        if ($s['inform']['enabled']) {
            if ($s['inform']['interval'] < 60 || $s['inform']['interval'] > 86400) {
                $errors[] = 'Interval inform harus 60–86400 detik.';
            }
            if ($s['inform']['acs_url'] !== '' && !preg_match('#^https?://[^\s"]+$#i', $s['inform']['acs_url'])) {
                $errors[] = 'URL ACS untuk modem harus diawali http:// atau https:// (contoh http://10.0.0.5:7547).';
            }
        }

        // Refresh info modem
        $s['refresh']['enabled']      = !empty($post['refresh_enabled']);
        $s['refresh']['interval_min'] = (int)$g('refresh_interval', 60);
        if ($s['refresh']['enabled'] && ($s['refresh']['interval_min'] < 5 || $s['refresh']['interval_min'] > 1440)) {
            $errors[] = 'Interval refresh harus 5–1440 menit.';
        }

        // Password admin modem
        $c = &$s['credentials'];
        $c['enabled']    = !empty($post['cred_enabled']);
        $c['user_name']  = $g('cred_user_name');
        $c['admin_name'] = $g('cred_admin_name');
        if ($g('cred_user_pass') !== '')  $c['user_pass']  = $g('cred_user_pass');
        if ($g('cred_admin_pass') !== '') $c['admin_pass'] = $g('cred_admin_pass');
        if (!empty($post['cred_user_pass_clear']))  $c['user_pass']  = '';
        if (!empty($post['cred_admin_pass_clear'])) $c['admin_pass'] = '';
        $c['exclude_models'] = implode(', ', self::parseModels($g('cred_exclude_models')));
        if ($c['enabled']) {
            $userPair  = $c['user_name'] !== '' && $c['user_pass'] !== '';
            $adminPair = $c['admin_name'] !== '' && $c['admin_pass'] !== '';
            if (!$userPair && !$adminPair) {
                $errors[] = 'Password admin modem aktif tapi belum ada pasangan username + password (akun user atau admin).';
            }
            foreach (self::parseModels($g('cred_exclude_models'), true) as $bad) {
                $errors[] = "Nama model tidak valid: \"{$bad}\" (hanya huruf, angka, - _ . /).";
            }
        }
        unset($c);

        return [$s, $errors];
    }

    /** @return string[] model valid (atau model TIDAK valid kalau $invalidOnly) */
    private static function parseModels(string $raw, bool $invalidOnly = false): array
    {
        $out = [];
        foreach (preg_split('/[,\n\r]+/', $raw) as $m) {
            $m = trim($m);
            if ($m === '') continue;
            $valid = (bool)preg_match('#^[A-Za-z0-9 _./-]+$#', $m);
            if ($valid !== $invalidOnly) $out[] = $m;
        }
        return array_values(array_unique($out));
    }

    /** Daftar fitur → [nama provision/preset, aktif?, args, precondition, weight]. */
    public static function plan(array $s): array
    {
        $s = self::withDefaults($s);
        $i = $s['inform'];
        $r = $s['refresh'];
        $c = $s['credentials'];

        $pre = [];
        foreach (self::parseModels($c['exclude_models']) as $m) {
            $pre[] = 'DeviceID.ProductClass <> "' . $m . '"';
        }

        return [
            [
                'name'    => self::PREFIX . 'inform',
                'label'   => 'Inform & Connection Request',
                'enabled' => (bool)$i['enabled'],
                'args'    => [(int)$i['interval'], $i['cr_user'], $i['cr_pass'], $i['acs_url']],
                'pre'     => '',
                'weight'  => 0,
            ],
            [
                'name'    => self::PREFIX . 'refresh',
                'label'   => 'Refresh info modem',
                'enabled' => (bool)$r['enabled'],
                'args'    => [(int)$r['interval_min']],
                'pre'     => '',
                'weight'  => 1,
            ],
            [
                'name'    => self::PREFIX . 'credentials',
                'label'   => 'Password admin modem',
                'enabled' => (bool)$c['enabled'],
                'args'    => [$c['user_name'], $c['user_pass'], $c['admin_name'], $c['admin_pass']],
                'pre'     => implode(' AND ', $pre),
                'weight'  => 2,
            ],
        ];
    }

    /**
     * Push ke GenieACS. Provision selalu ditulis (kode terbaru dari repo); preset dibuat
     * kalau fitur aktif, dihapus kalau mati.
     * @return array{ok: bool, log: string[]}
     */
    public static function push(AcsService $acs, array $settings): array
    {
        $ok  = true;
        $log = [];
        foreach (self::plan($settings) as $f) {
            $file = APPPATH . 'Libraries/AcsProvisions/' . $f['name'] . '.js';
            try {
                $script = @file_get_contents($file);
                if ($script === false) {
                    throw new \Exception("file provision {$f['name']}.js tidak ditemukan");
                }
                $res = $acs->putProvision($f['name'], $script);
                if (!in_array($res['status'], [200, 201, 204], true)) {
                    throw new \Exception("provision HTTP {$res['status']}: " . trim((string)$res['body']));
                }

                if ($f['enabled']) {
                    $res = $acs->putPreset($f['name'], [
                        'weight'         => $f['weight'],
                        'channel'        => $f['name'],
                        'events'         => new \stdClass(),
                        'precondition'   => $f['pre'],
                        'configurations' => [[
                            'type' => 'provision',
                            'name' => $f['name'],
                            'args' => $f['args'],
                        ]],
                    ]);
                    if (!in_array($res['status'], [200, 201, 204], true)) {
                        throw new \Exception("preset HTTP {$res['status']}: " . trim((string)$res['body']));
                    }
                    $log[] = "✔ {$f['label']}: aktif";
                } else {
                    $res = $acs->deletePreset($f['name']);
                    if (!in_array($res['status'], [200, 204, 404], true)) {
                        throw new \Exception("hapus preset HTTP {$res['status']}: " . trim((string)$res['body']));
                    }
                    $log[] = "– {$f['label']}: nonaktif";
                }
            } catch (\Throwable $e) {
                $ok    = false;
                $log[] = "✖ {$f['label']}: " . $e->getMessage();
            }
        }
        return ['ok' => $ok, 'log' => $log];
    }

    /**
     * Preset di GenieACS yang BUKAN milik gpon-manager — ditampilkan sebagai info,
     * ditandai kalau memanggil ext (layanan luar).
     * @return array<int, array{name: string, provisions: string[], ext: bool}>
     */
    public static function foreignPresets(AcsService $acs): array
    {
        $extProvisions = [];
        foreach ($acs->listProvisions() as $pv) {
            if (preg_match('/\bext\s*\(/', (string)($pv['script'] ?? ''))) {
                $extProvisions[] = (string)$pv['_id'];
            }
        }
        $out = [];
        foreach ($acs->listPresets() as $p) {
            $name = (string)($p['_id'] ?? '');
            if ($name === '' || str_starts_with($name, self::PREFIX)) continue;
            $provs = [];
            foreach (($p['configurations'] ?? []) as $cfg) {
                if (($cfg['type'] ?? '') === 'provision') $provs[] = (string)($cfg['name'] ?? '');
            }
            $out[] = [
                'name'       => $name,
                'provisions' => $provs,
                'ext'        => (bool)array_intersect($provs, $extProvisions),
            ];
        }
        return $out;
    }
}

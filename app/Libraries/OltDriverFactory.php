<?php

namespace App\Libraries;

use App\Libraries\Drivers\OltDriverInterface;
use App\Libraries\Drivers\ZteDriver;
use App\Libraries\Drivers\FiberhomeDriver;

class OltDriverFactory
{
    /**
     * Buat driver yang sesuai berdasarkan brand dan model OLT.
     * Saat ini semua ZTE pakai driver yang sama karena CLI-nya kompatibel.
     * Kalau nanti ada perbedaan signifikan antara model/versi ZTE,
     * bisa tambah kondisi di sini tanpa ubah kode lain.
     *
     * Contoh ekspansi:
     *   'c320_v21' => new ZteDriverV21($config)
     *   'c650'     => new ZteDriverC650($config)
     */
    public static function make(array $oltConfig): OltDriverInterface
    {
        $brand = strtolower($oltConfig['brand'] ?? 'zte');
        $model = strtolower($oltConfig['model'] ?? '');

        switch ($brand) {
            case 'zte':
                // Semua model ZTE (C320, C600, C650, C300) pakai driver yang sama
                // karena CLI GPON-nya kompatibel.
                // Jika nanti ada perbedaan perintah di model/versi tertentu,
                // tambah kondisi di sini: if ($model === 'c320_v2') return new ZteDriverV2(...)
                return new ZteDriver($oltConfig);

            case 'fiberhome':
            case 'fh':
                return new FiberhomeDriver($oltConfig);

            // case 'huawei':
            //     return new HuaweiDriver($oltConfig);

            default:
                throw new \Exception(
                    "Brand OLT '{$oltConfig['brand']}' belum didukung. " .
                    "Brand yang tersedia: ZTE, Fiberhome."
                );
        }
    }
}

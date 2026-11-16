<?php

namespace App\Controllers\Concerns;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Controller yang memanggil session_write_close() di constructor (supaya AJAX panjang,
 * mis. telnet ke OLT, tidak mengunci sesi tab lain) TIDAK bisa lagi menulis ke sesi:
 * redirect()->with()/withInput() diam-diam hilang. Pakai flashRedirect() untuk redirect
 * yang membawa pesan — sesi dibuka lagi sebentar, ditulis saat request selesai.
 */
trait ReopensSession
{
    protected function flashRedirect(): RedirectResponse
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        return redirect();
    }
}

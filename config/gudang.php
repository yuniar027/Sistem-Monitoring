<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akses Keuangan di panel /gudang
    |--------------------------------------------------------------------------
    |
    | Menu Keuangan (Biaya Operasional, Penjualan, Harga Acuan Origami,
    | Pembelian/Invoice, Laporan Laba Rugi) hanya boleh diakses satu akun
    | ini (Umma, admin sistem). Akun gudang_users lain -- termasuk yang
    | rolenya kebetulan 'admin' -- TIDAK otomatis dapat akses, karena
    | kolom 'role' di tabel gudang_users tidak dipakai sebagai penentu di
    | sini. Ganti lewat env GUDANG_EMAIL_AKSES_KEUANGAN kalau perlu.
    |
    */

    'email_akses_keuangan' => env('GUDANG_EMAIL_AKSES_KEUANGAN', 'sistem@ummababyshop.com'),

];

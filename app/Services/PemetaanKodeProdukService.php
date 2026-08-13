<?php

namespace App\Services;

use App\Models\ProdukMaster;

class PemetaanKodeProdukService
{
    /**
     * Kamus kata kunci di nama gudang -> kode tipe SKU.
     * Urutan penting: yang lebih spesifik/panjang dicek duluan, biar tidak salah
     * ketangkap sama kata kunci yang lebih pendek/generik (misal "TOPI SARUNG TANGAN"
     * harus dicek sebelum "TOPI" polos).
     */
    private const KAMUS_TIPE = [
        // -- Setelan 3-set (paling sering muncul di data Origami/Awan) --
        'SET BUNTUNG' => 'STBT', 'SET BT' => 'STBT', 'BUNTUNG' => 'STBT',
        'SET PENDEK' => 'STPD', 'SET PD' => 'STPD',
        'SET PANJANG' => 'STPJ', 'SET PJ' => 'STPJ', 'SET PJG' => 'STPJ',

        // -- Aksesoris kecil --
        'TOPI SARUNG TANGAN SARUNG KAKI' => 'TPS',
        'TOPI SARUNG TANGAN' => 'TPS',
        'TOPI RAJUT SARTAKI' => 'TRST',
        'TOPI RAJUT' => 'TR',
        'TOPI BULAT' => 'TPB',
        'TOPI BONNET' => 'BONNET', 'BONNET' => 'BONNET', 'BONET' => 'BONNET',
        'SARUNG TANGAN SARUNG KAKI' => 'STK', 'STK' => 'STK',
        'TOPI' => 'TP', // fallback generik, taruh paling akhir grup topi

        'POPOK' => 'PP',

        // -- Bedong --
        'BEDONG INSTAN' => 'BDIN',
        'BEDONG GULUNG' => 'BD', 'BEDONG' => 'BD',

        // -- Jumper --
        'JUMPER SEGITIGA PREMIUM' => 'JSR', 'SET BANDANA' => 'JSR',
        'JUMPER SEGITIGA' => 'JS',
        'JUMPER RAJUT' => 'JRK',

        // -- Romper --
        'ROMPER KERAH TUMPUK' => 'RPKT',
        'ROMPER SEGIEMPAT' => 'RPR',
        'ROMPER KIMONO' => 'RK',
        'ROMPER' => 'RR',

        // -- Sleepsuit --
        'SLEEPSUIT KIMONO' => 'SSKM',
        'SLEEPSUIT BUKA KAKI' => 'SSBK',
        'SLEEPSUIT TUTUP KAKI' => 'SSTK',
        'SLEEPSUIT RAMPEL TURBAN' => 'SSRT',
        'PAKET SLEEPSUIT' => 'PSS',

        // -- Kimono --
        'KIMONO PANJANG' => 'KMPJ',
        'KIMONO PENDEK' => 'KMPD',
        'KIMONO' => 'KM',

        // -- Celana --
        'CELANA PANJANG' => 'CLPJ',
        'CELANA PENDEK' => 'CLPD',
        'CELANA DALAM WANITA' => 'CDW',

        // -- Lain-lain --
        'DRESS' => 'DRS',
        'GAMIS' => 'GMS',
        'JUBAH MILO' => 'JBM',
        'JUBAH BEIGE' => 'JMG',
        'SELIMUT BAYI' => 'SB',
        'ON THE GO' => 'OTGS',
        'JAKET BULU' => 'JKB', 'JAKET' => 'JKB',
        'BABY CAPE' => 'BC',
        'BRA' => 'BRA', 'BH' => 'BRA',
        'TAS BAYI' => 'TS', 'TAS' => 'TS',
        'MSHAPE' => 'MS', 'M SHAPE' => 'MS', 'GENDONGAN' => 'MS',
        'PAKET USAHA' => 'PKU',
        'KERAH TUMPUK PENDEK' => 'KTPD',
        'BABY HAI' => null, // butuh sub-tipe, ditangani khusus di bawah
    ];

    /**
     * Kode warna/motif — HANYA yang sudah tervalidasi lewat contoh SKU nyata.
     * Sengaja tidak menebak yang belum dikonfirmasi (lebih baik masuk "tidak dikenali").
     */
    private const KAMUS_WARNA_ORIGAMI = [
        'IKAN' => 'OIK',
        'ABU' => 'OAB',
        'NAVY' => 'ONV',
        'PINK' => 'OPK',
        'SAGE' => 'OSG',
        'COKLAT' => 'OCK',
    ];

    private const KAMUS_WARNA_AWAN = [
        'KREM' => 'AKR', 'CREM' => 'AKR',
        'PEACH' => 'APC',
        'GOLD' => 'AGL',
    ];

    /**
     * Deteksi kode tipe dari teks nama gudang + variasi.
     * Return null kalau tidak ada kata kunci yang cocok sama sekali.
     */
    public function deteksiTipe(string $namaGudang, string $variasiGudang): ?string
    {
        $teks = strtoupper($namaGudang . ' ' . $variasiGudang);
        $namaTrim = strtoupper(trim($namaGudang));
        $variasiTrim = strtoupper(trim($variasiGudang));

        // Kasus khusus: "BABY HAI" + arah panjang/pendek
        if (str_contains($teks, 'BABY HAI')) {
            if (str_contains($teks, 'PANJANG')) {
                return 'BHPJ';
            }
            if (str_contains($teks, 'PENDEK')) {
                return 'BHPD';
            }
        }

        // Kasus khusus: pola "BAJU SET ... BT/PD/PJ - UM" (Setelan 3-set) —
        // kode arah (Buntung/Pendek/Panjang) muncul sebagai akhiran terpisah dari
        // kata "SET", dipisahkan nama warna, jadi tidak tertangkap kamus biasa.
        // Divalidasi lewat kolom variasi ("3S BTG"/"3S PD"/"3S PJG") sebagai sinyal kedua.
        if (str_contains($namaTrim, 'SET') || str_contains($variasiTrim, '3S')) {
            if (preg_match('/\bBTG\b/', $variasiTrim) || preg_match('/\bBT\s*-\s*UM$/', $namaTrim)) {
                return 'STBT';
            }
            if (preg_match('/\bPJG\b/', $variasiTrim) || preg_match('/\bPJ\s*-\s*UM$/', $namaTrim)) {
                return 'STPJ';
            }
            if (preg_match('/(?:^|\s)PD(?:\s|$)/', $variasiTrim) || preg_match('/\bPD\s*-\s*UM$/', $namaTrim)) {
                return 'STPD';
            }
        }

        foreach (self::KAMUS_TIPE as $katakunci => $kode) {
            if ($kode === null) {
                continue;
            }
            if (str_contains($teks, $katakunci)) {
                return $kode;
            }
        }

        return null;
    }

    /**
     * Deteksi kode warna/motif dari teks nama gudang, sesuai kamus motif sheet asalnya
     * ('ORIGAMI' atau 'AWAN'). Return null kalau tidak ketemu / belum ada di kamus tervalidasi.
     */
    public function deteksiWarna(string $namaGudang, string $namaSheet): ?string
    {
        $teks = strtoupper($namaGudang);
        $kamus = match (strtoupper($namaSheet)) {
            'ORIGAMI' => self::KAMUS_WARNA_ORIGAMI,
            'AWAN' => self::KAMUS_WARNA_AWAN,
            default => [],
        };

        foreach ($kamus as $katakunci => $kode) {
            if (str_contains($teks, $katakunci)) {
                return $kode;
            }
        }

        return null;
    }

    /**
     * Cari SKU yang cocok berdasarkan kombinasi kode tipe + kode warna.
     * Return array of ['sku', 'nama_produk'].
     */
    public function cariSkuByKode(string $kodeTipe, string $kodeWarna): array
    {
        return ProdukMaster::where('sku', 'like', "%{$kodeTipe}%")
            ->where('sku', 'like', "%{$kodeWarna}%")
            ->get(['sku', 'nama_produk'])
            ->toArray();
    }
}
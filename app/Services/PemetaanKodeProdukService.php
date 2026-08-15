<?php

namespace App\Services;

use App\Models\ProdukMaster;

class PemetaanKodeProdukService
{
    /**
     * Aturan deteksi tipe — daftar TERURUT (yang lebih spesifik/kata-lebih-banyak duluan).
     * Tiap aturan cocok kalau SEMUA grup kata di 'wajib' punya minimal 1 kata yang muncul
     * di teks (tidak harus nempel/berurutan — beda dari versi sebelumnya yang cari frasa persis).
     */
    private const ATURAN_TIPE = [
        // -- Topi & aksesoris, urutan dari paling spesifik --
        ['kode' => 'TRST', 'wajib' => [['TOPI'], ['RAJUT'], ['SARTAKI']]],
        ['kode' => 'TPS', 'wajib' => [['TOPI'], ['SARUNG']]],
        ['kode' => 'TR', 'wajib' => [['TOPI'], ['RAJUT']]],
        ['kode' => 'TPB', 'wajib' => [['TOPI'], ['BULAT']]],
        ['kode' => 'BONNET', 'wajib' => [['BONNET', 'BONET']]],
        ['kode' => 'STK', 'wajib' => [['SARUNG'], ['TANGAN']]],
        ['kode' => 'TP', 'wajib' => [['TOPI']]], // fallback topi generik, taruh terakhir grup topi

        ['kode' => 'PP', 'wajib' => [['POPOK']]],

        // -- Bedong --
        ['kode' => 'BDIN', 'wajib' => [['BEDONG'], ['INSTAN', 'INSTANT']]],
        ['kode' => 'BD', 'wajib' => [['BEDONG']]],

        // -- Setelan 3-set: cukup kata "SET" + arah, TIDAK HARUS nempel
        // (nama gudang sering "SET ORIGAMI COKLAT BT" — SET dan BT terpisah jauh)
        ['kode' => 'STBT', 'wajib' => [['SET'], ['BT', 'BTG', 'BUNTUNG']]],
        ['kode' => 'STPD', 'wajib' => [['SET'], ['PD', 'PDK', 'PENDEK']]],
        ['kode' => 'STPJ', 'wajib' => [['SET'], ['PJ', 'PJG', 'PANJANG']]],

        // -- Jumper --
        ['kode' => 'JSR', 'wajib' => [['SET'], ['BANDANA']]],
        ['kode' => 'JS', 'wajib' => [['JUMPER'], ['SEGITIGA']]],
        ['kode' => 'JRK', 'wajib' => [['JUMPER'], ['RAJUT']]],

        // -- Romper --
        ['kode' => 'RPKT', 'wajib' => [['ROMPER'], ['KERAH'], ['TUMPUK']]],
        ['kode' => 'RPR', 'wajib' => [['ROMPER'], ['SEGIEMPAT']]],
        ['kode' => 'RK', 'wajib' => [['ROMPER'], ['KIMONO']]],
        ['kode' => 'RR', 'wajib' => [['ROMPER']]],

        // -- Sleepsuit --
        ['kode' => 'SSKM', 'wajib' => [['SLEEPSUIT'], ['KIMONO']]],
        ['kode' => 'SSBK', 'wajib' => [['SLEEPSUIT'], ['BUKA'], ['KAKI']]],
        ['kode' => 'SSTK', 'wajib' => [['SLEEPSUIT'], ['TUTUP'], ['KAKI']]],
        ['kode' => 'SSRT', 'wajib' => [['SLEEPSUIT'], ['RAMPEL', 'TURBAN']]],
        ['kode' => 'PSS', 'wajib' => [['PAKET'], ['SLEEPSUIT']]],

        // -- Kimono --
        ['kode' => 'KMPJ', 'wajib' => [['KIMONO'], ['PANJANG', 'PJ']]],
        ['kode' => 'KMPD', 'wajib' => [['KIMONO'], ['PENDEK', 'PD']]],
        ['kode' => 'KM', 'wajib' => [['KIMONO']]],

        // -- Celana --
        ['kode' => 'CLPJ', 'wajib' => [['CELANA'], ['PANJANG']]],
        ['kode' => 'CLPD', 'wajib' => [['CELANA'], ['PENDEK']]],
        ['kode' => 'CDW', 'wajib' => [['CELANA'], ['DALAM'], ['WANITA']]],

        // -- Baby Hai --
        ['kode' => 'BHPJ', 'wajib' => [['BABY'], ['HAI'], ['PANJANG']]],
        ['kode' => 'BHPD', 'wajib' => [['BABY'], ['HAI'], ['PENDEK']]],

        // -- Kancing samping --
        ['kode' => 'KSPJ', 'wajib' => [['KANCING'], ['SAMPING'], ['PANJANG']]],
        ['kode' => 'KSPD', 'wajib' => [['KANCING'], ['SAMPING'], ['PENDEK']]],
        ['kode' => 'KSBT', 'wajib' => [['KANCING'], ['SAMPING'], ['BUNTUNG', 'BT']]],

        // -- Lain-lain --
        ['kode' => 'DRS', 'wajib' => [['DRESS']]],
        ['kode' => 'GMS', 'wajib' => [['GAMIS']]],
        ['kode' => 'JBM', 'wajib' => [['JUBAH'], ['MILO']]],
        ['kode' => 'JMG', 'wajib' => [['JUBAH'], ['BEIGE']]],
        ['kode' => 'SB', 'wajib' => [['SELIMUT']]],
        ['kode' => 'OTGS', 'wajib' => [['ON'], ['THE'], ['GO']]],
        ['kode' => 'JKB', 'wajib' => [['JAKET']]],
        ['kode' => 'BC', 'wajib' => [['BABY'], ['CAPE']]],
        ['kode' => 'BRA', 'wajib' => [['BRA', 'BH']]],
        ['kode' => 'TS', 'wajib' => [['TAS']]],
        ['kode' => 'MS', 'wajib' => [['MSHAPE', 'GENDONGAN']]],
        ['kode' => 'PKU', 'wajib' => [['PAKET'], ['USAHA']]],
        ['kode' => 'KTPD', 'wajib' => [['KERAH'], ['TUMPUK'], ['PENDEK']]],
        ['kode' => 'PRE', 'wajib' => [['PREMIUM']]],
        ['kode' => 'SR', 'wajib' => [['SETELAN'], ['RAJUT']]],
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

    private function tokenKata(string $teks): array
    {
        $teks = strtoupper($teks);
        $teks = preg_replace('/[^A-Z0-9\s]/', ' ', $teks);

        return array_values(array_filter(explode(' ', $teks), fn ($k) => $k !== ''));
    }

    /**
     * Deteksi kode tipe dari teks nama gudang + variasi.
     * Return null kalau tidak ada aturan yang cocok sama sekali.
     */
    public function deteksiTipe(string $namaGudang, string $variasiGudang): ?string
    {
        $kata = $this->tokenKata($namaGudang . ' ' . $variasiGudang);

        foreach (self::ATURAN_TIPE as $aturan) {
            $semuaGrupTerpenuhi = true;

            foreach ($aturan['wajib'] as $grupSinonim) {
                if (empty(array_intersect($grupSinonim, $kata))) {
                    $semuaGrupTerpenuhi = false;
                    break;
                }
            }

            if ($semuaGrupTerpenuhi) {
                return $aturan['kode'];
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
     * Kode tipe dicocokkan sebagai SEGMEN UTUH di antara tanda strip (bukan sekadar substring —
     * "TP" polos kalau dicari via LIKE '%TP%' ikut kena "STPD"/"TPS" juga, salah).
     * Return array of ['sku', 'nama_produk'].
     */
    public function cariSkuByKode(string $kodeTipe, string $kodeWarna): array
    {
        return ProdukMaster::where(function ($q) use ($kodeTipe) {
                $q->where('sku', 'like', "{$kodeTipe}-%")
                    ->orWhere('sku', 'like', "%-{$kodeTipe}-%")
                    ->orWhere('sku', 'like', "%-{$kodeTipe}");
            })
            ->where('sku', 'like', "%{$kodeWarna}%")
            ->get(['sku', 'nama_produk'])
            ->toArray();
    }
}
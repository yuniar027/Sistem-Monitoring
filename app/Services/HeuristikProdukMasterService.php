<?php

namespace App\Services;

use App\Models\ProdukMaster;

class HeuristikProdukMasterService
{
    private const SINONIM = [
        'st' => 'setelan',
        'cln' => 'celana',
        'pd' => 'pendek',
        'pj' => 'panjang',
    ];

    private const STOPWORDS = ['um', 'fbs', 'set', 'seri', 'sni'];

    /**
     * Muat seluruh katalog Produk Master sekali saja, sudah ditokenisasi.
     * Panggil ini SEKALI di luar loop, lalu pakai hasilnya berkali-kali di cariKandidat()
     * supaya tidak query + tokenisasi ulang setiap baris Excel (bisa sangat lambat kalau ratusan baris).
     *
     * Return: array of ['sku' => string, 'nama_produk' => string, 'token' => array]
     */
    public function muatKatalog(): array
    {
        return ProdukMaster::select('sku', 'nama_produk')
            ->get()
            ->map(fn (ProdukMaster $p) => [
                'sku' => $p->sku,
                'nama_produk' => $p->nama_produk,
                'token' => $this->tokenisasi($p->nama_produk),
            ])
            ->toArray();
    }

    /**
     * Cari kandidat produk paling mirip dari katalog yang sudah dimuat lewat muatKatalog().
     * Return array of ['sku', 'nama_produk', 'skor'] diurutkan skor tertinggi.
     */
    public function cariKandidat(string $namaItem, array $katalog, int $topN = 3): array
    {
        $tokenItem = $this->tokenisasi($namaItem);

        if (empty($tokenItem)) {
            return [];
        }

        $hasil = array_map(function (array $produk) use ($tokenItem) {
            return [
                'sku' => $produk['sku'],
                'nama_produk' => $produk['nama_produk'],
                'skor' => $this->hitungKemiripan($tokenItem, $produk['token']),
            ];
        }, $katalog);

        $hasil = array_values(array_filter($hasil, fn ($r) => $r['skor'] > 0));

        usort($hasil, fn ($a, $b) => $b['skor'] <=> $a['skor']);

        return array_slice($hasil, 0, $topN);
    }

    private function tokenisasi(string $teks): array
    {
        $teks = strtolower($teks);
        $teks = preg_replace('/[^a-z0-9\s]/', ' ', $teks);
        $kata = array_filter(explode(' ', $teks), fn ($k) => strlen($k) >= 2);
        $kata = array_map(fn ($k) => self::SINONIM[$k] ?? $k, $kata);

        return array_values(array_diff($kata, self::STOPWORDS));
    }

    private function hitungKemiripan(array $tokenA, array $tokenB): float
    {
        if (empty($tokenA) || empty($tokenB)) {
            return 0.0;
        }

        $irisan = array_intersect($tokenA, $tokenB);
        $gabungan = array_unique(array_merge($tokenA, $tokenB));

        return count($gabungan) > 0 ? count($irisan) / count($gabungan) : 0.0;
    }
}
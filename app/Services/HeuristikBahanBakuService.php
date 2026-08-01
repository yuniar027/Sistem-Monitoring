<?php

namespace App\Services;

use App\Models\BahanBaku;
use Illuminate\Support\Collection;

class HeuristikBahanBakuService
{
    /**
     * Cari kandidat bahan baku paling mirip berdasarkan kemiripan kata.
     * Return array of ['kode_bahan', 'nama_bahan', 'skor'] diurutkan skor tertinggi.
     * Skor 0-1, dihitung dari proporsi kata yang sama (bukan exact match string).
     */
    public function cariKandidat(string $namaItem, int $topN = 3): array
    {
        $tokenItem = $this->tokenisasi($namaItem);

        if (empty($tokenItem)) {
            return [];
        }

        $semuaBahanBaku = BahanBaku::select('kode_bahan', 'nama_bahan')->get();

        $hasil = $semuaBahanBaku->map(function (BahanBaku $bb) use ($tokenItem) {
            $tokenBb = $this->tokenisasi($bb->nama_bahan);
            $skor = $this->hitungKemiripan($tokenItem, $tokenBb);

            return [
                'kode_bahan' => $bb->kode_bahan,
                'nama_bahan' => $bb->nama_bahan,
                'skor' => $skor,
            ];
        })->filter(fn ($r) => $r['skor'] > 0)
            ->sortByDesc('skor')
            ->take($topN)
            ->values()
            ->toArray();

        return $hasil;
    }

    private const SINONIM = [
        'st' => 'setelan',
        'cln' => 'celana',
        'pd' => 'pendek',
        'pj' => 'panjang',
    ];

      private function tokenisasi(string $teks): array
    {
        $teks = strtolower($teks);
        $teks = preg_replace('/[^a-z0-9\s]/', ' ', $teks);
        $kata = array_filter(explode(' ', $teks), fn ($k) => strlen($k) >= 2);
        $kata = array_map(fn ($k) => self::SINONIM[$k] ?? $k, $kata);

        // Buang kata-kata generik yang tidak membantu identifikasi (noise umum)
        $stopwords = ['um', 'fbs', 'set', 'seri', 'sni'];

        return array_values(array_diff($kata, $stopwords));
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

<?php

namespace App\Services;

use App\Models\BahanBaku;

class PemetaanBahanBakuService
{
    private const AMBANG_YAKIN = 0.5;

    public function __construct(
        private HeuristikBahanBakuService $heuristik,
        private AiBahanBakuService $ai,
    ) {}

    /**
     * Petakan satu nama item ke kode_bahan.
     * Return: [
     *   'nama_item' => string,
     *   'kode_bahan' => string|null,
     *   'nama_bahan' => string|null,
     *   'metode' => 'heuristik'|'ai'|'tidak_ditemukan',
     *   'skor_atau_alasan' => string,
     * ]
     */
    public function petakanSatu(string $namaItem): array
    {
        $kandidat = $this->heuristik->cariKandidat($namaItem, 3);

        if (! empty($kandidat) && $kandidat[0]['skor'] >= self::AMBANG_YAKIN) {
            return [
                'nama_item' => $namaItem,
                'kode_bahan' => $kandidat[0]['kode_bahan'],
                'nama_bahan' => $kandidat[0]['nama_bahan'],
                'metode' => 'heuristik',
                'skor_atau_alasan' => 'Skor kemiripan: ' . round($kandidat[0]['skor'], 2),
            ];
        }

        // heuristik tidak yakin -> coba AI, kasih kandidat teratas heuristik + subset lain sebagai konteks
        if ($this->ai->tersedia()) {
            $daftarKandidat = ! empty($kandidat)
                ? $kandidat
                : BahanBaku::select('kode_bahan', 'nama_bahan')->limit(100)->get()->toArray();

            $hasilAi = $this->ai->carikanKecocokan($namaItem, $daftarKandidat);

            if ($hasilAi && ($hasilAi['yakin'] ?? false) && ! empty($hasilAi['kode_bahan']) && $hasilAi['kode_bahan'] !== 'null') {
                $bahanBaku = BahanBaku::where('kode_bahan', $hasilAi['kode_bahan'])->first();

                if ($bahanBaku) {
                    return [
                        'nama_item' => $namaItem,
                        'kode_bahan' => $bahanBaku->kode_bahan,
                        'nama_bahan' => $bahanBaku->nama_bahan,
                        'metode' => 'ai',
                        'skor_atau_alasan' => $hasilAi['alasan'] ?? '',
                    ];
                }
            }
        }

        return [
            'nama_item' => $namaItem,
            'kode_bahan' => null,
            'nama_bahan' => null,
            'metode' => 'tidak_ditemukan',
            'skor_atau_alasan' => ! empty($kandidat)
                ? 'Kandidat terdekat: ' . $kandidat[0]['kode_bahan'] . ' (skor rendah: ' . round($kandidat[0]['skor'], 2) . ')'
                : 'Tidak ada kandidat sama sekali',
        ];
    }

    /**
     * Petakan banyak nama item sekaligus.
     */
    public function petakanBanyak(array $namaItemList): array
    {
        return array_map(fn ($nama) => $this->petakanSatu($nama), $namaItemList);
    }
}

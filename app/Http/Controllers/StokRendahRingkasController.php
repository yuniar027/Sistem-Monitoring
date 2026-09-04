<?php

namespace App\Http\Controllers;

use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class StokRendahRingkasController extends Controller
{
    /**
     * Dipanggil n8n (Schedule Trigger jam 07:00) untuk ambil daftar barang
     * yang stok_akhir-nya sudah di/bawah stok_aman hari ini. n8n yang
     * merangkai jadi satu pesan Telegram gabungan (bukan per-barang).
     */
    public function index(): JsonResponse
    {
        $tanggal = today()->toDateString();

        // 1 query buat ambil semua alokasi khusus hari ini, bukan query
        // terpisah per barang (N+1) yang bisa timeout kalau barangnya banyak
        $alokasiPerBarang = \App\Models\StokAlokasiKhususHarian::query()
            ->whereDate('tanggal', $tanggal)
            ->selectRaw('barang_gudang_id, SUM(kuantitas) as total')
            ->groupBy('barang_gudang_id')
            ->pluck('total', 'barang_gudang_id');

        $barangKurang = StokHarianGudang::query()
            ->whereDate('tanggal', $tanggal)
            ->with('barangGudang')
            ->get()
            ->map(function (StokHarianGudang $harian) use ($alokasiPerBarang) {
                $stokSiap = (float) $harian->rak + (float) $harian->input;
                $alokasi = (float) ($alokasiPerBarang[$harian->barang_gudang_id] ?? 0);
                $stokAkhir = $stokSiap - $alokasi;

                return [
                    'stok_akhir' => $stokAkhir,
                    'barang' => $harian->barangGudang,
                ];
            })
            ->filter(fn ($item) => $item['stok_akhir'] < (float) ($item['barang']?->stok_aman ?? 0))
            ->map(function ($item) {
                $barang = $item['barang'];

                return [
                    'kategori' => $barang?->kategori === StokBarangGudang::KATEGORI_ORIGAMI ? 'Origami' : 'Awan',
                    'kode_barang' => $barang?->kode_barang,
                    'nama_barang' => $barang?->nama_barang,
                    'stok_akhir' => round($item['stok_akhir'], 2),
                    'stok_aman' => (float) ($barang?->stok_aman ?? 0),
                    'kekurangan' => round((float) ($barang?->stok_aman ?? 0) - $item['stok_akhir'], 2),
                ];
            })
            ->sortByDesc('kekurangan')
            ->values();

        return response()->json([
            'tanggal' => Carbon::parse($tanggal)->format('d-m-Y'),
            'jumlah_barang_kurang' => $barangKurang->count(),
            'barang' => $barangKurang,
        ]);
    }
}

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

        $barangKurang = StokHarianGudang::query()
            ->whereDate('tanggal', $tanggal)
            ->with('barangGudang')
            ->get()
            ->filter(fn (StokHarianGudang $harian) => $harian->stok_akhir < (float) ($harian->barangGudang?->stok_aman ?? 0))
            ->map(function (StokHarianGudang $harian) {
                $barang = $harian->barangGudang;

                return [
                    'kategori' => $barang?->kategori === StokBarangGudang::KATEGORI_ORIGAMI ? 'Origami' : 'Awan',
                    'kode_barang' => $barang?->kode_barang,
                    'nama_barang' => $barang?->nama_barang,
                    'stok_akhir' => round($harian->stok_akhir, 2),
                    'stok_aman' => (float) ($barang?->stok_aman ?? 0),
                    'kekurangan' => round((float) ($barang?->stok_aman ?? 0) - $harian->stok_akhir, 2),
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
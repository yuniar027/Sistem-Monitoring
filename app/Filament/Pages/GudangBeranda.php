<?php

namespace App\Filament\Pages;

use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Auth;

class GudangBeranda extends Dashboard
{
    protected static ?string $navigationLabel = 'Beranda';
    protected static ?string $title = 'Selamat Bekerja!';

    protected string $view = 'filament.pages.gudang-beranda';

    public function isPabrik(): bool
    {
        return Auth::guard('gudang')->user()?->isPabrik() ?? false;
    }

    public function getSnapshotHariIni()
    {
        return StokHarianGudang::query()
            ->whereDate('tanggal', today())
            ->with('barangGudang')
            ->get();
    }

    public function getJumlahStokRendah(): int
    {
        $tanggal = today()->toDateString();

        // ambil SEMUA alokasi khusus hari ini dalam 1 query, bukan query
        // terpisah per barang (sebelumnya ini bikin 561 query -> timeout)
        $alokasiPerBarang = \App\Models\StokAlokasiKhususHarian::query()
            ->whereDate('tanggal', $tanggal)
            ->selectRaw('barang_gudang_id, SUM(kuantitas) as total')
            ->groupBy('barang_gudang_id')
            ->pluck('total', 'barang_gudang_id');

        return $this->getSnapshotHariIni()
            ->filter(function (StokHarianGudang $h) use ($alokasiPerBarang) {
                $stokSiap = (float) $h->rak + (float) $h->input;
                $alokasi = (float) ($alokasiPerBarang[$h->barang_gudang_id] ?? 0);
                $stokAkhir = $stokSiap - $alokasi;

                return $stokAkhir < (float) ($h->barangGudang?->stok_aman ?? 0);
            })
            ->count();
    }

    public function getJumlahBelumDiisi(): int
    {
        // barang yang input hari ini masih 0, tanda kemungkinan belum diisi
        return $this->getSnapshotHariIni()
            ->where('input', 0)
            ->count();
    }

    public function getTotalBarangAktif(): int
    {
        return StokBarangGudang::count();
    }

    public function getTanggalHariIni(): string
    {
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $t = today();

        return $hari[$t->dayOfWeek] . ', ' . $t->day . ' ' . $bulan[$t->month] . ' ' . $t->year;
    }
}
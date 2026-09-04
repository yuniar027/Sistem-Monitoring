<?php

namespace App\Filament\Pages;

use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use Filament\Pages\Dashboard;

class GudangBeranda extends Dashboard
{
    protected static ?string $navigationLabel = 'Beranda';
    protected static ?string $title = 'Selamat Bekerja!';

    protected string $view = 'filament.pages.gudang-beranda';

    public function getSnapshotHariIni()
    {
        return StokHarianGudang::query()
            ->whereDate('tanggal', today())
            ->with('barangGudang')
            ->get();
    }

    public function getJumlahStokRendah(): int
    {
        return $this->getSnapshotHariIni()
            ->filter(fn (StokHarianGudang $h) => $h->stok_akhir < (float) ($h->barangGudang?->stok_aman ?? 0))
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
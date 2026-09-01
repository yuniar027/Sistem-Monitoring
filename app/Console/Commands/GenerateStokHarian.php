<?php

namespace App\Console\Commands;

use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateStokHarian extends Command
{
    protected $signature = 'stok:generate-harian {--tanggal= : Tanggal yang mau digenerate, default hari ini (format Y-m-d)}';

    protected $description = 'Generate snapshot stok harian gudang, rak/stok_awal otomatis lanjut dari stok_akhir/sisa hari sebelumnya';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal') ? Carbon::parse($this->option('tanggal')) : today();
        $kemarin = $tanggal->copy()->subDay()->toDateString();

        $this->info("Generate snapshot stok harian untuk tanggal: {$tanggal->toDateString()}");

        $dibuatBarang = 0;
        foreach (StokBarangGudang::all() as $barang) {
            if ($barang->harian()->whereDate('tanggal', $tanggal)->exists()) {
                continue;
            }

            $harianKemarin = $barang->harian()->whereDate('tanggal', $kemarin)->first();
            $rak = $harianKemarin?->stok_akhir ?? 0;

            $barang->harian()->create([
                'tanggal' => $tanggal->toDateString(),
                'rak' => $rak,
                'input' => 0,
            ]);
            $dibuatBarang++;
        }

        $dibuatVariasi = 0;
        foreach (StokVariasiGudang::all() as $variasi) {
            if ($variasi->harian()->whereDate('tanggal', $tanggal)->exists()) {
                continue;
            }

            $harianKemarin = $variasi->harian()->whereDate('tanggal', $kemarin)->first();
            $stokAwal = $harianKemarin?->sisa ?? 0;

            $variasi->harian()->create([
                'tanggal' => $tanggal->toDateString(),
                'stok_awal' => $stokAwal,
                'input' => 0,
                'out' => 0,
            ]);
            $dibuatVariasi++;
        }

        $this->info("Selesai. Barang: {$dibuatBarang} baris baru, Variasi: {$dibuatVariasi} baris baru.");

        return self::SUCCESS;
    }
}

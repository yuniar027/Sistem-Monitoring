<?php

namespace App\Console\Commands;

use App\Models\StokAlokasiKhususHarian;
use Illuminate\Console\Command;

class GabungkanTypoKodeAlokasi extends Command
{
    protected $signature = 'stok:gabungkan-typo-kode-alokasi
        {kode_salah : Kode apa adanya di database, mis. "k 3 set"}
        {kode_benar : Kode yang benar/dipakai proses K, mis. "K 3 SET"}
        {--dry-run : Cuma tampilkan berapa baris yang akan diubah, tanpa menyimpan}';

    protected $description = 'Perbaiki satu kode_alokasi yang salah ketik (typo huruf besar/kecil dll) di StokAlokasiKhususHarian, digabung ke kode yang benar';

    public function handle(): int
    {
        $kodeSalah = trim($this->argument('kode_salah'));
        $kodeBenar = trim($this->argument('kode_benar'));
        $dryRun = (bool) $this->option('dry-run');

        $query = StokAlokasiKhususHarian::query()->where('kode_alokasi', $kodeSalah);
        $jumlah = $query->count();

        if ($jumlah === 0) {
            $this->info("Tidak ada baris dengan kode_alokasi = \"{$kodeSalah}\". Tidak ada yang diubah.");

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$jumlah} baris dengan kode_alokasi = \"{$kodeSalah}\", akan diubah jadi \"{$kodeBenar}\".");

        if ($dryRun) {
            $query->get(['id', 'barang_gudang_id', 'tanggal', 'kuantitas'])->each(
                fn ($row) => $this->line("  #{$row->id} barang_gudang_id={$row->barang_gudang_id} tanggal={$row->tanggal->toDateString()} kuantitas={$row->kuantitas}")
            );
            $this->comment('DRY RUN, belum ada yang disimpan. Jalankan tanpa --dry-run untuk benar-benar mengubah.');

            return self::SUCCESS;
        }

        $query->get()->each(fn (StokAlokasiKhususHarian $row) => $row->update(['kode_alokasi' => $kodeBenar]));

        $this->info("Selesai. {$jumlah} baris digabung ke \"{$kodeBenar}\".");

        return self::SUCCESS;
    }
}
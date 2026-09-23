<?php

namespace App\Console\Commands;

use App\Models\ProductionProcessTarget;
use App\Models\StokVariasiGudang;
use App\Models\StokVariasiHarian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillVariasiKategoriNamaDasar extends Command
{
    protected $signature = 'stok:backfill-variasi-kategori-nama-dasar
        {--dry-run : Cuma tampilkan apa yang AKAN dilakukan, tanpa menyimpan perubahan}';

    protected $description = 'Isi kategori & nama_dasar yang masih kosong di Master Variasi Gudang dari barang induknya (bug importer lama), gabungkan duplikat kalau ketemu';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $kosong = StokVariasiGudang::query()
            ->where(function ($q) {
                $q->whereNull('kategori')->orWhereNull('nama_dasar');
            })
            ->with('barangGudang')
            ->get();

        if ($kosong->isEmpty()) {
            $this->info('Tidak ada baris Master Variasi Gudang yang kosong kategori/nama_dasar. Aman.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$kosong->count()} baris yang kategori/nama_dasar-nya kosong.");
        $this->newLine();

        $diisi = 0;
        $digabung = 0;
        $dilewati = 0;

        foreach ($kosong as $variasi) {
            $barang = $variasi->barangGudang;

            if (! $barang) {
                $this->warn("#{$variasi->id} (kode: {$variasi->kode_variasi}) -- tidak ada barang_gudang_id yang valid, dilewati. Isi kategori/nama_dasar manual lewat menu Master Variasi Gudang.");
                $dilewati++;

                continue;
            }

            $kategori = $barang->kategori;
            $namaDasar = $barang->nama_dasar;

            // Kalau sudah ada baris LAIN dengan kombinasi
            // kategori+nama_dasar+kode_variasi yang sama, ini duplikat dari
            // proses import lama -- harus DIGABUNG, bukan ditimpa, supaya
            // nggak nabrak unique constraint yang baru.
            $existing = StokVariasiGudang::query()
                ->where('kategori', $kategori)
                ->where('nama_dasar', $namaDasar)
                ->where('kode_variasi', $variasi->kode_variasi)
                ->where('id', '!=', $variasi->id)
                ->first();

            if ($existing) {
                $this->line("Gabung: #{$variasi->id} ({$barang->nama_barang}, kode {$variasi->kode_variasi}) -> jadi satu dengan #{$existing->id}");

                if (! $dryRun) {
                    DB::transaction(function () use ($variasi, $existing) {
                        StokVariasiHarian::where('variasi_gudang_id', $variasi->id)
                            ->get()
                            ->each(function (StokVariasiHarian $harian) use ($existing) {
                                $sudahAda = StokVariasiHarian::where('variasi_gudang_id', $existing->id)
                                    ->whereDate('tanggal', $harian->tanggal)
                                    ->exists();

                                $sudahAda
                                    ? $harian->delete()
                                    : $harian->update(['variasi_gudang_id' => $existing->id]);
                            });

                        ProductionProcessTarget::where('variasi_gudang_id', $variasi->id)
                            ->get()
                            ->each(function (ProductionProcessTarget $target) use ($existing) {
                                $sudahAda = ProductionProcessTarget::where('variasi_gudang_id', $existing->id)
                                    ->where('production_process_id', $target->production_process_id)
                                    ->exists();

                                $sudahAda
                                    ? $target->delete()
                                    : $target->update(['variasi_gudang_id' => $existing->id]);
                            });

                        $variasi->delete();
                    });
                }

                $digabung++;

                continue;
            }

            $this->line("Isi: #{$variasi->id} ({$barang->nama_barang}, kode {$variasi->kode_variasi}) -> kategori={$kategori}, nama_dasar={$namaDasar}");

            if (! $dryRun) {
                $variasi->update([
                    'kategori' => $kategori,
                    'nama_dasar' => $namaDasar,
                ]);
            }

            $diisi++;
        }

        $this->newLine();
        $this->info(
            'Selesai' . ($dryRun ? ' (DRY RUN, belum ada yang disimpan)' : '') .
            ". Diisi: {$diisi}, Digabung (duplikat): {$digabung}, Dilewati (perlu manual): {$dilewati}."
        );

        if ($dryRun) {
            $this->comment('Jalankan tanpa --dry-run untuk benar-benar menyimpan perubahan.');
        }

        return self::SUCCESS;
    }
}
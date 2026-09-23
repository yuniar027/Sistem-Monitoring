<?php

namespace App\Console\Commands;

use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use Illuminate\Console\Command;

class BersihkanTargetBelumTerverifikasi extends Command
{
    protected $signature = 'stok:bersihkan-target-belum-terverifikasi
        {--apply : Benar-benar hapus. Tanpa opsi ini cuma preview (dry-run).}';

    protected $description = 'Bersihkan Target Produksi K yang terlanjur dibuat command LAMA (buggy, multiplier=1) untuk kode yang sampai sekarang belum ada bukti multiplier aslinya (K 48, K 50). Juga bereskan K 39/30 supaya bisa dibuat ulang dengan multiplier terverifikasi (x4).';

    private const HAPUS_TARGET_SAJA = ['K48', 'K50'];

    private const HAPUS_PROSES_TOTAL = ['K39/30'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->info('=== Kode yang TARGET-nya (kalau ada) akan dihapus, Proses tetap ada ===');

        foreach (self::HAPUS_TARGET_SAJA as $kunci) {
            $proses = $this->cariProses($kunci);

            if (! $proses) {
                $this->line("  {$kunci}: Proses K tidak ditemukan, tidak ada apa-apa.");

                continue;
            }

            $targets = ProductionProcessTarget::where('production_process_id', $proses->id)->get();

            if ($targets->isEmpty()) {
                $this->line("  {$proses->kode_proses}: tidak ada Target sama sekali, aman, tidak ada yang dihapus.");

                continue;
            }

            $this->warn("  {$proses->kode_proses}: ditemukan {$targets->count()} Target (multiplier: " . $targets->pluck('multiplier')->unique()->implode(', ') . ').');

            foreach ($targets as $t) {
                $this->line('    - ' . ($t->variasiGudang?->nama_dasar ?? '—') . ' / ' . ($t->variasiGudang?->kode_variasi ?? '—') . " (multiplier {$t->multiplier})");
            }

            if ($apply) {
                $targets->each->delete();
                $this->info("  -> {$targets->count()} Target dihapus. Proses \"{$proses->kode_proses}\" tetap ada, sekarang kosong lagi.");
            }
        }

        $this->newLine();
        $this->info('=== Kode yang Proses K-nya SENDIRI akan dihapus total, supaya bisa dibuat ulang otomatis dengan multiplier benar ===');

        foreach (self::HAPUS_PROSES_TOTAL as $kunci) {
            $proses = $this->cariProses($kunci);

            if (! $proses) {
                $this->line("  {$kunci}: Proses K tidak ditemukan, tidak ada apa-apa.");

                continue;
            }

            $eventCount = \App\Models\ProductionEvent::whereHas(
                'productionProcessTarget',
                fn ($q) => $q->where('production_process_id', $proses->id)
            )->count();

            if ($eventCount > 0) {
                $this->error("  {$proses->kode_proses}: ada {$eventCount} ProductionEvent (input harian nyata) yang sudah pakai proses ini -- TIDAK dihapus otomatis, harus ditinjau manual dulu.");

                continue;
            }

            $targets = ProductionProcessTarget::where('production_process_id', $proses->id)->get();
            $this->line("  {$proses->kode_proses}: {$targets->count()} Target akan ikut dihapus, lalu Proses K-nya sendiri dihapus.");

            if ($apply) {
                $targets->each->delete();
                $proses->delete();
                $this->info("  -> \"{$kunci}\" dihapus total. Jalankan lagi 'php artisan stok:saran-target-produksi-k --apply-proses' supaya dibuat ulang dengan multiplier terverifikasi.");
            }
        }

        $this->newLine();

        if (! $apply) {
            $this->comment('Ini preview (dry-run). Jalankan dengan --apply untuk benar-benar menghapus.');
        }

        return self::SUCCESS;
    }

    private function cariProses(string $kunciNormal): ?ProductionProcess
    {
        return ProductionProcess::all()->first(
            fn (ProductionProcess $p) => str_replace(' ', '', strtoupper(trim($p->kode_proses))) === $kunciNormal
        );
    }
}
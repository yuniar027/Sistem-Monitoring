<?php

namespace App\Console\Commands;

use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokVariasiGudang;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PerbaikiMultiplierTargetK extends Command
{
    protected $signature = 'stok:perbaiki-multiplier-target-k
        {--apply : Benar-benar simpan perubahan. Tanpa opsi ini cuma preview (dry-run).}';

    protected $description = 'Koreksi massal kolom multiplier di Target Produksi K yang kebetulan sempat dibuat dengan angka default 1 (bug command lama), diganti angka hasil audit histori Excel';

    private const MULTIPLIER_BENAR = [
        'K3SET' => 4,
        'K12' => 6,
        'K18' => 4,
        'K27' => 6,
        'K33' => 4,
        'K39/30' => 4,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $rows = [];
        $totalDiubah = 0;

        foreach (ProductionProcess::all() as $proses) {
            $kunci = $this->normalisasi($proses->kode_proses);
            $multiplierBenar = self::MULTIPLIER_BENAR[$kunci] ?? null;

            if ($multiplierBenar === null) {
                continue;
            }

            $targets = ProductionProcessTarget::where('production_process_id', $proses->id)->get();

            foreach ($targets as $target) {
                $sekarang = (float) $target->multiplier;

                if ($sekarang === (float) $multiplierBenar) {
                    continue;
                }

                $variasi = $target->variasiGudang;
                $rows[] = [
                    $proses->kode_proses,
                    $variasi?->nama_dasar ?? '—',
                    $variasi?->kode_variasi ?? '—',
                    $sekarang,
                    $multiplierBenar,
                ];

                if ($apply) {
                    $target->update(['multiplier' => $multiplierBenar]);
                }

                $totalDiubah++;
            }
        }

        if (empty($rows)) {
            $this->info('Tidak ada target yang perlu dikoreksi. Semua multiplier sudah sesuai.');

            return self::SUCCESS;
        }

        $this->table(
            ['Kode Proses', 'Nama Dasar (Motif)', 'Kode Variasi', 'Multiplier Sekarang', 'Multiplier Benar'],
            $rows
        );

        $this->newLine();

        if ($apply) {
            $this->info("Selesai. {$totalDiubah} target dikoreksi.");
        } else {
            $this->comment("Ini preview (dry-run). {$totalDiubah} target AKAN dikoreksi kalau dijalankan dengan --apply.");
        }

        $this->newLine();
        $this->tanganiPengecualianJsCUnggu($apply);

        return self::SUCCESS;
    }

    private function tanganiPengecualianJsCUnggu(bool $apply): void
    {
        $variasi = StokVariasiGudang::query()
            ->where('nama_dasar', 'like', '%C.UNGGU%')
            ->where('kode_variasi', 'like', '%33%')
            ->get();

        if ($variasi->isEmpty()) {
            $this->warn('Tidak ketemu variasi "33 PCS" untuk motif mengandung "C.UNGGU" -- cek manual nama_dasar-nya persis apa lewat menu Master Variasi Gudang, lalu update multiplier target K33-nya jadi 6 sendiri.');

            return;
        }

        foreach ($variasi as $v) {
            $target = ProductionProcessTarget::whereHas(
                'productionProcess',
                fn ($q) => $q->where('kode_proses', 'like', 'K%33%')
            )->where('variasi_gudang_id', $v->id)->first();

            if (! $target) {
                $this->warn("Tidak ketemu target K33 untuk variasi #{$v->id} ({$v->nama_dasar} / {$v->kode_variasi}) -- cek manual.");

                continue;
            }

            $this->line("Pengecualian: {$v->nama_dasar} / {$v->kode_variasi} -> multiplier " . ($apply ? 'diubah jadi 6' : 'AKAN diubah jadi 6'));

            if ($apply) {
                $target->update(['multiplier' => 6]);
            }
        }
    }

    private function normalisasi(string $kode): string
    {
        return str_replace(' ', '', Str::of($kode)->trim()->upper()->toString());
    }
}
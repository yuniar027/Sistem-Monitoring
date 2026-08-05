<?php

namespace App\Console\Commands;

use App\Models\JurnalUmum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPurchaseJournals extends Command
{
    protected $signature = 'fix:purchase-journals {--dry-run}';

    protected $description = 'Perbaiki entri jurnal pembelian yang salah dicatat ke Kas -> ubah ke Hutang Usaha atau hapus jika duplikat.';

    public function handle(): int
    {
        $akun = config('akun');
        $kas = $akun['kas'];
        $hutang = $akun['hutang_usaha'];

        $sources = ['stok_masuk', 'bahan_baku_masuk'];

        $query = JurnalUmum::where('kode_akun', $kas)
            ->whereIn('sumber_tipe', $sources);

        $totalEntries = $query->count();
        $totalKredit = $query->sum('kredit');

        $this->info("Menemukan $totalEntries entri (total kredit: Rp " . number_format($totalKredit, 0, ',', '.') . ") pada akun Kas untuk sumber pembelian.");

        $rows = $query->get();

        // Group by sumber_tipe + sumber_id to decide per-source action
        $groups = $rows->groupBy(fn ($r) => $r->sumber_tipe . '#' . $r->sumber_id);

        $willUpdateCount = 0;
        $willDeleteCount = 0;

        $actions = [];

        foreach ($groups as $key => $groupRows) {
            $sample = $groupRows->first();
            $sumberTipe = $sample->sumber_tipe;
            $sumberId = $sample->sumber_id;
            $kreditSum = (float) $groupRows->sum('kredit');

            $hutangExists = JurnalUmum::where('kode_akun', $hutang)
                ->where('sumber_tipe', $sumberTipe)
                ->where('sumber_id', $sumberId)
                ->where('kredit', $kreditSum)
                ->exists();

            if ($hutangExists) {
                $willDeleteCount++;
                $actions[] = ['action' => 'delete', 'ids' => $groupRows->pluck('id')->toArray(), 'sumber' => $key, 'amount' => $kreditSum];
            } else {
                $willUpdateCount++;
                $actions[] = ['action' => 'update', 'ids' => $groupRows->pluck('id')->toArray(), 'sumber' => $key, 'amount' => $kreditSum];
            }
        }

        $this->info("Rencana: update $willUpdateCount grup (ubah akun -> Hutang Usaha), hapus $willDeleteCount grup (duplikat Kas).");

        if ($this->option('dry-run')) {
            $this->info('Dry-run: tidak ada perubahan yang dilakukan.');
            return 0;
        }

        DB::transaction(function () use ($actions, $hutang) {
            foreach ($actions as $act) {
                if ($act['action'] === 'update') {
                    JurnalUmum::whereIn('id', $act['ids'])->update(['kode_akun' => $hutang]);
                } elseif ($act['action'] === 'delete') {
                    JurnalUmum::whereIn('id', $act['ids'])->delete();
                }
            }
        });

        $this->info('Perbaikan selesai.');

        return 0;
    }
}

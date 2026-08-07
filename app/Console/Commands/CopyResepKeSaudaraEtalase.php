<?php

namespace App\Console\Commands;

use App\Models\ProdukMaster;
use App\Models\ResepPaketItem;
use Illuminate\Console\Command;

class CopyResepKeSaudaraEtalase extends Command
{
    protected $signature = 'resep:copy-ke-saudara-etalase {--dry-run}';

    protected $description = 'Salin resep/BOM dari 1 SKU rakitan ke SKU "saudara etalase" (nama_produk sama, SKU beda karena suffix -1/-2/-3) yang belum punya resep sama sekali.';

    public function handle(): int
    {
        $groups = ProdukMaster::where('tipe_produk', 'rakitan')->get()->groupBy('nama_produk');

        $totalSkuDisalin = 0;
        $totalBarisResep = 0;
        $totalGrupTerpakai = 0;

        foreach ($groups as $namaProduk => $items) {
            $skuSumber = null;
            $resepSumber = null;

            // Cari SKU pertama dalam grup ini yang sudah punya resep, jadi "sumber" untuk saudara-saudaranya
            foreach ($items as $item) {
                $resep = ResepPaketItem::where('sku', $item->sku)->get();
                if ($resep->isNotEmpty()) {
                    $skuSumber = $item->sku;
                    $resepSumber = $resep;
                    break;
                }
            }

            if (! $skuSumber) {
                continue; // grup ini belum ada resep sama sekali di SKU manapun, tidak bisa disalin
            }

            $adaYangDisalinDiGrupIni = false;

            foreach ($items as $item) {
                if ($item->sku === $skuSumber) {
                    continue;
                }

                if (ResepPaketItem::where('sku', $item->sku)->exists()) {
                    continue; // SKU ini sudah punya resep sendiri, jangan ditimpa
                }

                $adaYangDisalinDiGrupIni = true;
                $totalSkuDisalin++;

                foreach ($resepSumber as $baris) {
                    $this->line("  {$item->sku} <- disalin dari {$skuSumber} (bahan_baku_id={$baris->bahan_baku_id}, qty={$baris->kuantitas_per_paket})");

                    if (! $this->option('dry-run')) {
                        ResepPaketItem::create([
                            'sku' => $item->sku,
                            'bahan_baku_id' => $baris->bahan_baku_id,
                            'kuantitas_per_paket' => $baris->kuantitas_per_paket,
                        ]);
                    }

                    $totalBarisResep++;
                }
            }

            if ($adaYangDisalinDiGrupIni) {
                $totalGrupTerpakai++;
            }
        }

        $this->info("Selesai. Grup produk terpakai: {$totalGrupTerpakai}, SKU baru dapat resep: {$totalSkuDisalin}, total baris resep disalin: {$totalBarisResep}.");

        if ($this->option('dry-run')) {
            $this->info('Dry-run: tidak ada perubahan yang disimpan ke database.');
        }

        return self::SUCCESS;
    }
}
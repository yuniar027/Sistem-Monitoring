<?php

namespace Database\Seeders;

use App\Models\ProdukMaster;
use Illuminate\Database\Seeder;

class ProdukMasterSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('produk_master.csv');

        if (! file_exists($path)) {
            $this->command->warn('produk_master.csv not found at ' . $path);
            return;
        }

        if (($handle = fopen($path, 'r')) === false) {
            $this->command->warn('Unable to open produk_master.csv');
            return;
        }

        $header = null;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (! $header) {
                $header = array_map('trim', $row);
                continue;
            }

            $data = array_combine($header, $row);
            if (! $data) {
                continue;
            }

            ProdukMaster::updateOrCreate(
                ['sku' => trim($data['sku'])],
                [
                    'nama_produk' => trim($data['nama_produk'] ?? ''),
                    'satuan_jual' => trim($data['satuan_jual'] ?? ''),
                    'satuan_beli' => trim($data['satuan_beli'] ?? ''),
                    'isi_per_satuan_beli' => is_numeric($data['isi_per_satuan_beli'] ?? null) ? (int)$data['isi_per_satuan_beli'] : 1,
                    'kategori' => $data['kategori'] === '' ? null : trim($data['kategori'] ?? null),
                    'harga_modal_default' => $data['harga_modal_default'] === '' ? null : $data['harga_modal_default'],
                    'target_stok_minimum' => is_numeric($data['target_stok_minimum'] ?? null) ? (int)$data['target_stok_minimum'] : 0,
                ]
            );
        }

        fclose($handle);
    }
}

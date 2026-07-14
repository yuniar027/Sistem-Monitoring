<?php

namespace Database\Seeders;

use App\Models\ProdukMaster;
use App\Models\StokMentah;
use Illuminate\Database\Seeder;

class StokAwalSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('stok_awal.csv');

        if (! file_exists($path)) {
            $this->command->warn('stok_awal.csv not found at ' . $path);
            return;
        }

        if (($handle = fopen($path, 'r')) === false) {
            $this->command->warn('Unable to open stok_awal.csv');
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

            $sku = trim($data['sku']);
            $qty = isset($data['kuantitas_tersedia']) && is_numeric($data['kuantitas_tersedia']) ? (int)$data['kuantitas_tersedia'] : 0;

            // Ensure produk exists; if not, warn and skip
            if (! ProdukMaster::where('sku', $sku)->exists()) {
                $this->command->warn("Produk with sku {$sku} not found; skipping stok_awal entry.");
                continue;
            }

            StokMentah::updateOrCreate(
                ['sku' => $sku],
                ['kuantitas_tersedia' => $qty, 'updated_at' => now()]
            );
        }

        fclose($handle);
    }
}

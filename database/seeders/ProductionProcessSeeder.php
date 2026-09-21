<?php

namespace Database\Seeders;

use App\Models\ProductionProcess;
use Illuminate\Database\Seeder;

class ProductionProcessSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'K3 SET',
            'K18',
            'K33',
            'K12',
            'K27',
            'K12 LKP',
            'K18 LKP',
            'K48',
            'K50',
        ] as $kodeProses) {
            ProductionProcess::updateOrCreate(
                ['kode_proses' => $kodeProses],
                ['nama_proses' => $kodeProses],
            );
        }
    }
}

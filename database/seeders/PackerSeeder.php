<?php

namespace Database\Seeders;

use App\Models\Packer;
use Illuminate\Database\Seeder;

class PackerSeeder extends Seeder
{
    public function run(): void
    {
        $nama = [
            'FITRI', 'LENI', 'LILIK', 'LIA', 'VIVI', 'DINDA', 'PUTRI', 'NADIA',
            'HOFIFAH', 'SYANDIKA', 'CAHYA', 'ALMA', 'NUR', 'RIRIS', 'LIDYA', 'FIDAH',
            'LUVI', 'ANA', 'ESTI', 'RINDI', 'FELIA', 'UCI', 'RIMA', 'SORAYA',
        ];

        foreach ($nama as $n) {
            Packer::updateOrCreate(
                ['nama' => $n],
                ['status' => $n === 'ESTI' ? 'resign' : 'aktif']
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\SuratJalanPdfParser;
use Illuminate\Console\Command;

class DebugSuratJalanPdf extends Command
{
    protected $signature = 'stok:debug-surat-jalan {path : Path absolut/relatif ke file PDF surat jalan}';

    protected $description = 'Dump baris teks mentah + hasil parse dari satu PDF Surat Jalan, buat diagnosa kalau parsingnya meleset (jumlah/total nggak cocok)';

    public function handle(SuratJalanPdfParser $parser): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->info('=== BARIS TEKS MENTAH (hasil ekstraksi PDF, per baris) ===');

        foreach ($parser->debugTeksMentah($path) as $i => $baris) {
            $this->line(sprintf('%3d: %s', $i, $baris));
        }

        $hasil = $parser->parse($path);

        $this->newLine();
        $this->info('=== HASIL PARSE ===');
        $this->info("Tanggal: " . ($hasil['tanggal'] ?? '-') . ", Gudang: " . ($hasil['gudang'] ?? '-'));

        $this->table(
            ['Kode', 'Nama', 'Qty', 'Terdaftar di Master?'],
            array_map(
                fn ($i) => [$i['kode'], $i['nama'], $i['qty'], $i['terdaftar'] ? 'ya' : 'BELUM'],
                $hasil['items']
            )
        );

        $this->newLine();
        $this->info('Total item terbaca: ' . count($hasil['items']));
        $this->info("Total qty terbaca: {$hasil['total_terbaca']}");
        $this->info('Total footer PDF: ' . ($hasil['total_footer'] ?? '-'));
        $this->info('Cocok dengan footer: ' . ($hasil['cocok'] ? 'YA' : 'TIDAK'));

        if (! empty($hasil['item_tanpa_qty'])) {
            $this->newLine();
            $this->warn('Nama barang yang KETEMU tapi gak ada pasangan angkanya (dilewati):');

            foreach ($hasil['item_tanpa_qty'] as $item) {
                $this->line(" - {$item['kode']}: {$item['nama']}");
            }
        }

        return self::SUCCESS;
    }
}
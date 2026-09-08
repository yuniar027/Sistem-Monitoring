<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PreviewSheetTanggal extends Command
{
    protected $signature = 'stok:preview-sheet-tanggal
        {path : Path ke file .xlsx}
        {--tahun-akhir=2026 : Tahun sheet yang PALING TERAKHIR (paling kanan/paling baru)}
        {--bulan-akhir=8 : Bulan sheet yang PALING TERAKHIR (1-12)}
        {--output= : Path file CSV hasil preview, default sama nama file + "-preview.csv"}';

    protected $description = 'Preview (tanpa tulis data) tebakan tanggal untuk setiap sheet di file Excel, buat direview manual dulu';

    /**
     * Prefix bulan (huruf pertama yang dicek pada nama sheet) -> nomor bulan.
     * Dicek berurutan, pakai starts-with, jadi cukup 2-3 huruf pembeda.
     */
    protected array $prefixBulan = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'mei' => 5,
        'jun' => 6,
        'jul' => 7,
        'ag' => 8,   // agt, agts, agst, ags, agustus - semua diawali "ag"
        'sep' => 9,
        'okt' => 10,
        'nov' => 11,
        'des' => 12,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $tahunAkhir = (int) $this->option('tahun-akhir');
        $bulanAkhir = (int) $this->option('bulan-akhir');

        $this->info('Membaca daftar nama sheet...');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $namaSheet = $reader->listWorksheetNames($path);

        $this->info('Total sheet: ' . count($namaSheet));

        // Parse (hari, bulan) dari tiap nama sheet, tandai yang gagal dikenali
        $hasilParse = [];
        foreach ($namaSheet as $idx => $nama) {
            $parsed = $this->parseHariBulan($nama);
            $hasilParse[] = [
                'index' => $idx,
                'nama_sheet' => $nama,
                'hari' => $parsed['hari'] ?? null,
                'bulan' => $parsed['bulan'] ?? null,
                'dikenali' => $parsed !== null,
            ];
        }

        // Cari index sheet TERAKHIR yang dikenali - itu jadi acuan tahun akhir
        $indexTerakhirDikenali = null;
        foreach (array_reverse($hasilParse) as $row) {
            if ($row['dikenali']) {
                $indexTerakhirDikenali = $row['index'];
                break;
            }
        }

        if ($indexTerakhirDikenali === null) {
            $this->error('Tidak ada satupun nama sheet yang bisa dikenali polanya.');

            return self::FAILURE;
        }

        // Jalan MUNDUR dari sheet terakhir yang dikenali, tahun mulai dari
        // --tahun-akhir. Kalau bulan sheet SEBELUMNYA (lebih ke kiri) lebih
        // BESAR dari bulan sheet SEKARANG, berarti kita baru saja melompati
        // pergantian tahun (mundur), jadi tahun sheet sebelumnya = tahun-1.
        $tahun = $tahunAkhir;
        $bulanSebelumnyaYangDikenali = $bulanAkhir;

        for ($i = $indexTerakhirDikenali; $i >= 0; $i--) {
            if (! $hasilParse[$i]['dikenali']) {
                continue;
            }

            $bulanIni = $hasilParse[$i]['bulan'];

            if ($bulanIni > $bulanSebelumnyaYangDikenali) {
                $tahun--;
            }

            $hasilParse[$i]['tahun'] = $tahun;
            $bulanSebelumnyaYangDikenali = $bulanIni;
        }

        // Sheet setelah index terakhir yang dikenali (kalau ada, jarang
        // terjadi) dianggap tidak dikenali juga biar aman
        for ($i = $indexTerakhirDikenali + 1; $i < count($hasilParse); $i++) {
            $hasilParse[$i]['dikenali'] = false;
        }

        // Susun tanggal final & deteksi duplikat/anomali
        $tanggalMuncul = [];
        foreach ($hasilParse as &$row) {
            if ($row['dikenali']) {
                $row['tanggal'] = sprintf('%04d-%02d-%02d', $row['tahun'], $row['bulan'], $row['hari']);
                $tanggalMuncul[$row['tanggal']] = ($tanggalMuncul[$row['tanggal']] ?? 0) + 1;
            } else {
                $row['tanggal'] = null;
            }
        }
        unset($row);

        foreach ($hasilParse as &$row) {
            $row['duplikat'] = $row['tanggal'] && $tanggalMuncul[$row['tanggal']] > 1;
        }
        unset($row);

        // Tulis ke CSV
        $outputPath = $this->option('output') ?: (pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME) . '-preview.csv');
        $handle = fopen($outputPath, 'w');
        fputcsv($handle, ['urutan_sheet', 'nama_sheet', 'tanggal_tebakan', 'dikenali', 'duplikat']);

        $jumlahDikenali = 0;
        $jumlahTidakDikenali = 0;
        $jumlahDuplikat = 0;

        foreach ($hasilParse as $row) {
            fputcsv($handle, [
                $row['index'] + 1,
                $row['nama_sheet'],
                $row['tanggal'] ?? '',
                $row['dikenali'] ? 'YA' : 'TIDAK',
                $row['duplikat'] ? 'YA - CEK MANUAL' : '',
            ]);

            if ($row['dikenali']) {
                $jumlahDikenali++;
            } else {
                $jumlahTidakDikenali++;
            }

            if ($row['duplikat']) {
                $jumlahDuplikat++;
            }
        }

        fclose($handle);

        $this->info("Preview selesai, disimpan ke: {$outputPath}");
        $this->info("Dikenali: {$jumlahDikenali}, Tidak dikenali (dilewati): {$jumlahTidakDikenali}, Duplikat tanggal (perlu dicek manual): {$jumlahDuplikat}");

        return self::SUCCESS;
    }

    /**
     * Parse "05 AGT", "5 agustus", "31 des", dll -> ['hari' => 5, 'bulan' => 8].
     * Return null kalau polanya nggak dikenali sama sekali.
     */
    protected function parseHariBulan(string $namaSheet): ?array
    {
        if (! preg_match('/^\s*(\d{1,2})\s*([A-Za-z]+)/', trim($namaSheet), $m)) {
            return null;
        }

        $hari = (int) $m[1];
        $kataBulan = strtolower($m[2]);

        if ($hari < 1 || $hari > 31) {
            return null;
        }

        foreach ($this->prefixBulan as $prefix => $nomorBulan) {
            if (str_starts_with($kataBulan, $prefix)) {
                return ['hari' => $hari, 'bulan' => $nomorBulan];
            }
        }

        return null;
    }
}
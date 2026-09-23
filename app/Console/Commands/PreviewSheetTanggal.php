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
    /**
     * Koreksi manual untuk nama sheet yang kelihatan typo di file asli
     * (dikonfirmasi manual, BUKAN tebakan sistem). Cocokkan nama sheet
     * PERSIS (trim, case-sensitive) -> hari & bulan yang benar.
     */
    protected array $koreksiManual = [
        '25 feb' => ['hari' => 25, 'bulan' => 12],   // typo, harusnya "25 des"
        '09 JUNI' => ['hari' => 9, 'bulan' => 7],    // typo, harusnya "09 juli"
    ];

    protected array $prefixBulan = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'mei' => 5,
        'may' => 5,   // ejaan Inggris, beda dari "mei"
        'jun' => 6,
        'jul' => 7,
        'ag' => 8,    // agt, agts, agst, ags, agustus, august - semua diawali "ag"
        'sep' => 9,
        'spt' => 9,
        'okt' => 10,
        'oct' => 10,  // ejaan Inggris, beda dari "okt"
        'nov' => 11,
        'des' => 12,
        'dec' => 12,  // ejaan Inggris, beda dari "des"
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

        // Deteksi loncatan tanggal yang kegedean dibanding sheet sebelumnya
        // (sequence-nya biasanya rapat harian, jadi loncat > 25 hari itu
        // mencurigakan - tanda kemungkinan typo nama sheet di masa lalu)
        $tanggalSebelumnya = null;
        foreach ($hasilParse as &$row) {
            $row['gap_besar'] = false;

            if (! $row['dikenali'] || ! $row['tanggal']) {
                continue;
            }

            if ($tanggalSebelumnya !== null) {
                $selisihHari = (strtotime($row['tanggal']) - strtotime($tanggalSebelumnya)) / 86400;

                if ($selisihHari > 25 || $selisihHari < 0) {
                    $row['gap_besar'] = true;
                }
            }

            $tanggalSebelumnya = $row['tanggal'];
        }
        unset($row);

        // Tulis ke CSV
        $outputPath = $this->option('output') ?: (pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME) . '-preview.csv');
        $handle = fopen($outputPath, 'w');
        fputcsv($handle, ['urutan_sheet', 'nama_sheet', 'tanggal_tebakan', 'dikenali', 'duplikat', 'gap_besar']);

        $jumlahDikenali = 0;
        $jumlahTidakDikenali = 0;
        $jumlahDuplikat = 0;
        $jumlahGapBesar = 0;

        foreach ($hasilParse as $row) {
            fputcsv($handle, [
                $row['index'] + 1,
                $row['nama_sheet'],
                $row['tanggal'] ?? '',
                $row['dikenali'] ? 'YA' : 'TIDAK',
                $row['duplikat'] ? 'YA - CEK MANUAL' : '',
                $row['gap_besar'] ? 'YA - CEK MANUAL' : '',
            ]);

            if ($row['dikenali']) {
                $jumlahDikenali++;
            } else {
                $jumlahTidakDikenali++;
            }

            if ($row['duplikat']) {
                $jumlahDuplikat++;
            }

            if ($row['gap_besar']) {
                $jumlahGapBesar++;
            }
        }

        fclose($handle);

        $this->info("Preview selesai, disimpan ke: {$outputPath}");
        $this->info("Dikenali: {$jumlahDikenali}, Tidak dikenali (dilewati): {$jumlahTidakDikenali}, Duplikat tanggal: {$jumlahDuplikat}, Gap tanggal mencurigakan: {$jumlahGapBesar}");

        return self::SUCCESS;
    }

    /**
     * Parse "05 AGT", "5 agustus", "31 des", "0601" (DDMM), "25 02" (DD MM)
     * -> ['hari' => 5, 'bulan' => 8]. Return null kalau polanya nggak
     * dikenali sama sekali.
     */
    protected function parseHariBulan(string $namaSheet): ?array
    {
        $nama = trim($namaSheet);

        // Cek koreksi manual dulu (typo yang udah dikonfirmasi)
        if (isset($this->koreksiManual[$nama])) {
            return $this->koreksiManual[$nama];
        }

        // Pola 1: ada nama bulan, contoh "05 AGT", "5 agustus"
        if (preg_match('/^\s*(\d{1,2})\s*([A-Za-z]+)/', $nama, $m)) {
            $hari = (int) $m[1];
            $kataBulan = strtolower($m[2]);

            foreach ($this->prefixBulan as $prefix => $nomorBulan) {
                if (str_starts_with($kataBulan, $prefix)) {
                    return $this->validasiTanggal($hari, $nomorBulan);
                }
            }

            return null;
        }

        // Pola 2: angka murni DDMM (4 digit) atau "DD MM" (dipisah spasi)
        if (preg_match('/^\s*(\d{2})\s*(\d{2})\s*$/', $nama, $m)) {
            return $this->validasiTanggal((int) $m[1], (int) $m[2]);
        }

        return null;
    }

    protected function validasiTanggal(int $hari, int $bulan): ?array
    {
        if ($hari < 1 || $hari > 31 || $bulan < 1 || $bulan > 12) {
            return null;
        }

        return ['hari' => $hari, 'bulan' => $bulan];
    }
}
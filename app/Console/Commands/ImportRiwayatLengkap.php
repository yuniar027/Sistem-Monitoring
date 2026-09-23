<?php

namespace App\Console\Commands;

use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportRiwayatLengkap extends Command
{
    protected $signature = 'stok:import-riwayat-lengkap
        {path : Path ke file .xlsx}
        {--kategori=awan : awan atau origami}
        {--tahun-akhir=2026 : Tahun sheet PALING TERAKHIR di file}
        {--bulan-akhir=8 : Bulan sheet PALING TERAKHIR di file (1-12)}';

    protected $description = 'Import SEMUA histori (multi-bulan) dari file Excel lama, tanggal ditebak otomatis dari nama sheet';

    /**
     * Koreksi manual per kategori untuk nama sheet yang typo di file asli
     * (sudah dikonfirmasi manual lewat stok:preview-sheet-tanggal, BUKAN
     * tebakan). Cocokkan nama sheet PERSIS.
     */
    protected array $koreksiManual = [
        'awan' => [
            '25 feb' => ['hari' => 25, 'bulan' => 12],   // typo, harusnya "25 des"
        ],
        'origami' => [
            '09 JUNI' => ['hari' => 9, 'bulan' => 7],    // typo, harusnya "09 juli"
        ],
    ];

    protected array $prefixBulan = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'mei' => 5,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'ag' => 8,
        'sep' => 9,
        'spt' => 9,
        'okt' => 10,
        'oct' => 10,
        'nov' => 11,
        'des' => 12,
        'dec' => 12,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $path = $this->argument('path');
        $kategori = strtolower($this->option('kategori'));
        $tahunAkhir = (int) $this->option('tahun-akhir');
        $bulanAkhir = (int) $this->option('bulan-akhir');

        if (! in_array($kategori, [StokBarangGudang::KATEGORI_AWAN, StokBarangGudang::KATEGORI_ORIGAMI])) {
            $this->error("Kategori tidak valid: {$kategori}");

            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        // === TAHAP 1: bangun peta nama-sheet -> tanggal (sama persis logikanya dengan stok:preview-sheet-tanggal) ===
        $this->info('Membangun peta tanggal dari nama sheet...');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $semuaNamaSheet = $reader->listWorksheetNames($path);

        $petaTanggal = $this->bangunPetaTanggal($semuaNamaSheet, $kategori, $tahunAkhir, $bulanAkhir);

        $this->info('Sheet yang akan diimport: ' . count($petaTanggal) . ' dari ' . count($semuaNamaSheet) . ' total sheet.');

        if (! $this->confirm('Lanjutkan import dengan peta tanggal ini?', true)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        // === TAHAP 2 & 3: load SATU SHEET saja per iterasi, proses, lalu buang
        // dari memori sebelum lanjut ke sheet berikutnya. Ini PENTING untuk file
        // dengan ratusan sheet (mis. 314) -- memuat semuanya sekaligus ke satu
        // objek Spreadsheet bisa membengkak sampai berGB-GB dan bikin proses
        // dibunuh paksa oleh OS tanpa sempat melapor error apa pun. Tulis data
        // pakai BATCH UPSERT (bukan
        // query satu-satu per baris) supaya nggak kena ratusan ribu round-trip
        // ke database cloud ===
        $barangCache = [];
        $variasiCache = [];
        $totalBarang = 0;
        $totalVariasi = 0;
        $totalAlokasi = 0;
        $totalHarianBarang = 0;
        $sheetGagal = [];

        $progressBar = $this->output->createProgressBar(count($petaTanggal));
        $progressBar->start();

        foreach ($petaTanggal as $namaSheet => $tanggal) {
            $spreadsheet = null;

            try {
                // load HANYA sheet ini saja, bukan semuanya sekaligus
                $readerSheet = IOFactory::createReaderForFile($path);
                $readerSheet->setReadDataOnly(true);
                $readerSheet->setLoadSheetsOnly([$namaSheet]);
                $spreadsheet = $readerSheet->load($path);

                if (! $spreadsheet->sheetNameExists($namaSheet)) {
                    $sheetGagal[] = "{$namaSheet} (tidak ditemukan pas load ulang)";
                    unset($spreadsheet, $readerSheet);
                    $progressBar->advance();
                    continue;
                }

                $sheet = $spreadsheet->getSheetByName($namaSheet);
                $rows = $sheet->toArray(null, true, true, false);

                // sudah dapat array datanya, objek spreadsheet & sheet boleh
                // dibuang dari memori SEKARANG, sebelum lanjut proses baris
                unset($sheet, $spreadsheet, $readerSheet);

                if (empty($rows)) {
                    $progressBar->advance();
                    continue;
                }

                $header = array_map(fn ($h) => strtoupper(trim((string) $h)), $rows[0]);

                $idxStokAmanBarang = array_search('STOK AMAN', $header);
                if ($idxStokAmanBarang === false) {
                    $idxStokAmanBarang = 0;
                }

            $idxNama = array_search('NAMA', $header);
            $idxRak = array_search('RAK', $header);
            $idxInputBarang = array_search('INPUT', $header);
            $idxStokSiap = array_search('STOK SIAP', $header);
            $idxStokAkhir = array_search('STOK AKHIR', $header);
            $idxVariasi = array_search('VARIASI', $header);
            $idxTitipPabrik = array_search('UM TITIP PABRIK', $header);
            $idxStokMentahUmma = array_search('STOK MENTAH UMMA', $header);

            // Format lama:
            // A = STOK K2 (stok aman)
            // B = NAMA
            // C = STOK K2 (rak)
            // D = INPUT
            // E = STOK SIAP
            // F = KELUAR 1
            // G = STOK K1
            // H = KELUAR 2
            // I = STOK K2
            // O = S M UMMA
            $formatLegacy = (
                $idxNama === 1
                && $idxStokSiap === 4
                && $idxStokAkhir === false
                && isset($rows[0][2])
                && strtoupper(trim((string) $rows[0][2])) === 'STOK K2'
            );

            // Kalau bukan format baru dan bukan format lama, tetap dianggap gagal.
            if (
                ! $formatLegacy
                && ($idxNama === false || $idxStokSiap === false || $idxStokAkhir === false)
            ) {
                $sheetGagal[] = "{$namaSheet} (struktur tidak dikenali)";
                $progressBar->advance();
                continue;
            }

            $kolomK = [];

            if (! $formatLegacy) {
                for ($i = $idxStokSiap + 1; $i < $idxStokAkhir; $i++) {
                    if (! empty(trim((string) ($rows[0][$i] ?? '')))) {
                        $kolomK[$i] = trim((string) $rows[0][$i]);
                    }
                }
            }

                // kumpulkan dulu semua baris di sheet ini, upsert 1x di akhir
                $batchHarianBarang = [];
                $batchAlokasi = [];
                $batchHarianVariasi = [];
                $now = now();

                foreach (array_slice($rows, 1) as $row) {
                    $namaBarang = trim(preg_replace('/\s+/', ' ', (string) ($row[$idxNama] ?? '')));

                    if ($namaBarang === '') {
                        continue;
                    }

                    if ($formatLegacy) {
                        // Format lama:
                        // A = STOK K2 (stok aman)
                        // B = NAMA
                        // C = STOK K2 (rak)
                        // D = INPUT
                        // O = S M UMMA
                        $stokAmanBarang = $this->parseNumeric($row[0] ?? 0);
                        $rak = $this->parseNumeric($row[2] ?? 0);
                        $inputBarang = $this->parseNumeric($row[3] ?? 0);
                        $titipPabrik = null;
                        $stokMentahUmma = $this->parseNumeric($row[14] ?? 0);
                    } else {
                        // Format baru
                        $stokAmanBarang = (float) ($row[$idxStokAmanBarang] ?? 0);
                        $rak = (float) ($row[$idxRak] ?? 0);
                        $inputBarang = (float) ($row[$idxInputBarang] ?? 0);
                        $titipPabrik = $idxTitipPabrik !== false ? $row[$idxTitipPabrik] ?? null : null;
                        $stokMentahUmma = $idxStokMentahUmma !== false ? $row[$idxStokMentahUmma] ?? null : null;
                    }

                    // barang: masih firstOrCreate satu-satu, TAPI di-cache jadi
                    // total query-nya cuma sebanyak barang UNIK (ratusan),
                    // bukan sebanyak baris x sheet (ratusan ribu)
                    $cacheKey = $kategori . '|' . $namaBarang;
                    if (! isset($barangCache[$cacheKey])) {
                        $barang = StokBarangGudang::firstOrCreate(
                            ['nama_barang' => $namaBarang, 'kategori' => $kategori],
                            ['stok_aman' => $stokAmanBarang]
                        );
                        $barangCache[$cacheKey] = $barang;
                        $totalBarang++;
                    } else {
                        $barang = $barangCache[$cacheKey];
                    }

                    $keyHarianBarang = $barang->id . '|' . $tanggal;
                    $batchHarianBarang[$keyHarianBarang] = [
                        'barang_gudang_id' => $barang->id,
                        'tanggal' => $tanggal,
                        'rak' => $rak,
                        'input' => $inputBarang,
                        'um_titip_pabrik' => $titipPabrik !== null && $titipPabrik !== '' ? (float) $titipPabrik : null,
                        'stok_mentah_umma' => $stokMentahUmma !== null && $stokMentahUmma !== '' ? (float) $stokMentahUmma : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $totalHarianBarang++;

                    foreach ($kolomK as $colIdx => $kodeAlokasi) {
                        $nilai = $row[$colIdx] ?? null;
                        if ($nilai === null || $nilai === '' || (float) $nilai == 0) {
                            continue;
                        }

                        $keyAlokasi = $barang->id . '|' . $tanggal . '|' . $kodeAlokasi;
                        $batchAlokasi[$keyAlokasi] = [
                            'barang_gudang_id' => $barang->id,
                            'tanggal' => $tanggal,
                            'kode_alokasi' => $kodeAlokasi,
                            'kuantitas' => (float) $nilai,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $totalAlokasi++;
                    }

                    if ($idxVariasi !== false) {
                        $kodeVariasi = trim((string) ($row[$idxVariasi] ?? ''));

                        if ($kodeVariasi !== '') {
                            $stokAmanVariasiRaw = (string) ($row[$idxVariasi + 1] ?? '');
                            $stokAmanVariasi = (float) preg_replace('/[^0-9.]/', '', $stokAmanVariasiRaw);
                            $stokAwalVariasi = (float) ($row[$idxVariasi + 2] ?? 0);
                            $inputVariasi = (float) ($row[$idxVariasi + 3] ?? 0);
                            $outVariasi = (float) ($row[$idxVariasi + 5] ?? 0);

                            // variasi: sama, di-cache biar cuma query sekali
                            // per kombinasi (barang, kode_variasi) yang UNIK
                            $variasiCacheKey = $barang->id . '|' . $kodeVariasi;
                            if (! isset($variasiCache[$variasiCacheKey])) {
                                // Cocokkan pakai kategori+nama_dasar+kode_variasi
                                // (unique key sebenarnya sejak migration
                                // scope_stok_variasi_gudang_by_kategori_nama_dasar),
                                // bukan barang_gudang_id.
                                $variasi = StokVariasiGudang::firstOrCreate(
                                    [
                                        'kategori' => $barang->kategori,
                                        'nama_dasar' => $barang->nama_dasar,
                                        'kode_variasi' => $kodeVariasi,
                                    ],
                                    [
                                        'barang_gudang_id' => $barang->id,
                                        'stok_aman' => $stokAmanVariasi,
                                    ]
                                );
                                $variasiCache[$variasiCacheKey] = $variasi;
                                $totalVariasi++;
                            } else {
                                $variasi = $variasiCache[$variasiCacheKey];
                            }

                            $keyHarianVariasi = $variasi->id . '|' . $tanggal;
                            $batchHarianVariasi[$keyHarianVariasi] = [
                                'variasi_gudang_id' => $variasi->id,
                                'tanggal' => $tanggal,
                                'stok_awal' => $stokAwalVariasi,
                                'input' => $inputVariasi,
                                'out' => $outVariasi,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }

                // === UPSERT SEKALIGUS, bukan satu-satu ===
                if (! empty($batchHarianBarang)) {
                    \App\Models\StokHarianGudang::upsert(
                        array_values($batchHarianBarang),
                        ['barang_gudang_id', 'tanggal'],
                        ['rak', 'input', 'um_titip_pabrik', 'stok_mentah_umma', 'updated_at']
                    );
                }

                if (! empty($batchAlokasi)) {
                    StokAlokasiKhususHarian::upsert(
                        array_values($batchAlokasi),
                        ['barang_gudang_id', 'tanggal', 'kode_alokasi'],
                        ['kuantitas', 'updated_at']
                    );
                }

                if (! empty($batchHarianVariasi)) {
                    \App\Models\StokVariasiHarian::upsert(
                        array_values($batchHarianVariasi),
                        ['variasi_gudang_id', 'tanggal'],
                        ['stok_awal', 'input', 'out', 'updated_at']
                    );
                }
            } catch (\Throwable $e) {
                $sheetGagal[] = "{$namaSheet} (error: {$e->getMessage()})";
            }

            // paksa buang sisa objek besar dari memori sebelum lanjut ke
            // sheet berikutnya -- ini kunci supaya proses tetap ringan
            // walau jumlah sheet-nya ratusan
            gc_collect_cycles();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Selesai. Total barang unik: {$totalBarang}, snapshot harian barang: {$totalHarianBarang}, variasi unik: {$totalVariasi}, alokasi khusus: {$totalAlokasi}.");

        if (! empty($sheetGagal)) {
            $this->warn('Sheet yang GAGAL diproses (' . count($sheetGagal) . '):');
            foreach ($sheetGagal as $gagal) {
                $this->warn("  - {$gagal}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Bangun peta [nama_sheet => 'Y-m-d'] dengan jalan MUNDUR dari sheet
     * paling akhir (anchor: tahun-akhir/bulan-akhir), sama persis logikanya
     * dengan stok:preview-sheet-tanggal supaya hasilnya konsisten dengan
     * yang sudah divalidasi manual.
     */
    protected function bangunPetaTanggal(array $namaSheet, string $kategori, int $tahunAkhir, int $bulanAkhir): array
    {
        $koreksi = $this->koreksiManual[$kategori] ?? [];

        $hasilParse = [];
        foreach ($namaSheet as $idx => $nama) {
            $parsed = $this->parseHariBulan($nama, $koreksi);
            $hasilParse[] = [
                'index' => $idx,
                'nama_sheet' => $nama,
                'hari' => $parsed['hari'] ?? null,
                'bulan' => $parsed['bulan'] ?? null,
                'dikenali' => $parsed !== null,
            ];
        }

        $indexTerakhirDikenali = null;
        foreach (array_reverse($hasilParse) as $row) {
            if ($row['dikenali']) {
                $indexTerakhirDikenali = $row['index'];
                break;
            }
        }

        if ($indexTerakhirDikenali === null) {
            return [];
        }

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

        $peta = [];
        foreach ($hasilParse as $row) {
            if ($row['dikenali'] && $row['index'] <= $indexTerakhirDikenali) {
                $peta[$row['nama_sheet']] = sprintf('%04d-%02d-%02d', $row['tahun'], $row['bulan'], $row['hari']);
            }
        }

        return $peta;
    }

    protected function parseHariBulan(string $namaSheet, array $koreksi): ?array
    {
        $nama = trim($namaSheet);

        if (isset($koreksi[$nama])) {
            return $koreksi[$nama];
        }

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

    private function parseNumeric(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}

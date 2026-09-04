<?php

namespace App\Console\Commands;

use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportStokHarianExcel extends Command
{
    protected $signature = 'stok:import-harian-excel
        {path : Path ke file .xlsx}
        {--kategori=awan : awan atau origami}
        {--tahun=2026}';

    protected $description = 'Import data historis stok harian dari file Excel lama (sheet per tanggal)';

    /**
     * Peta nama sheet PERSIS -> tanggal (hari) di bulan Agustus, per kategori.
     * Sheet tanpa nama tanggal jelas sengaja tidak dimasukkan sesuai
     * keputusan bisnis (dilewati).
     */
    protected array $petaSheetTanggalAwan = [
        '01 agts' => 1,
        '04 agts' => 4,
        '05 AGT' => 5,
        '07 agt' => 7,
        '08 agt' => 8,
        '10 AGT' => 10,
        '11 agt' => 11,
        '13 agt' => 13,
        '15 agt' => 15,
        '20 agt' => 20,
        '21 agts' => 21,
        '22 agts' => 22,
        '26 agt' => 26,
        '27 agt' => 27,
        '28 agts' => 28,
        '29 agt' => 29,
        '31 agts' => 31,
    ];

    protected array $petaSheetTanggalOrigami = [
        '01 ags' => 1,
        '05 agst' => 5,
        '06 AGTS' => 6,
        '07 AGST' => 7,
        '08 agts' => 8,
        '10 agts' => 10,
        '11 agts' => 11,
        '12 agst' => 12,
        '13 agts' => 13,
        '14 agts' => 14,
        '15 agts' => 15,
        '18 agst' => 18,
        '20 agts' => 20,
        '21 agts' => 21,
        '22 agts' => 22,
        '24 agts' => 24,
        '26 agts' => 26,
        '28 AGTS' => 28,
        '29 agts' => 29,
        '31 agts' => 31,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $path = $this->argument('path');
        $tahun = (int) $this->option('tahun');
        $kategori = strtolower($this->option('kategori'));

        if (! in_array($kategori, [StokBarangGudang::KATEGORI_AWAN, StokBarangGudang::KATEGORI_ORIGAMI])) {
            $this->error("Kategori tidak valid: {$kategori}. Gunakan 'awan' atau 'origami'.");

            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $petaSheetTanggal = $kategori === StokBarangGudang::KATEGORI_ORIGAMI
            ? $this->petaSheetTanggalOrigami
            : $this->petaSheetTanggalAwan;

        $this->info("Membaca file Excel (kategori: {$kategori})...");
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_keys($petaSheetTanggal));
        $spreadsheet = $reader->load($path);

        $barangCache = [];
        $totalBarang = 0;
        $totalVariasi = 0;
        $totalAlokasi = 0;

        foreach ($petaSheetTanggal as $namaSheet => $tanggalHari) {
            if (! $spreadsheet->sheetNameExists($namaSheet)) {
                $this->warn("Sheet '{$namaSheet}' tidak ditemukan di file, dilewati.");
                continue;
            }

            $tanggal = Carbon::create($tahun, 8, $tanggalHari)->toDateString();
            $sheet = $spreadsheet->getSheetByName($namaSheet);
            $rows = $sheet->toArray(null, true, true, false);

            $header = array_map(fn ($h) => strtoupper(trim((string) $h)), $rows[0]);

            $idxStokAmanBarang = array_search('STOK AMAN', $header);
            // fallback: beberapa sheet header kolom pertamanya kosong,
            // tapi posisinya tetap kolom ke-0 = stok aman barang
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

            if ($idxNama === false || $idxStokSiap === false || $idxStokAkhir === false) {
                $this->warn("Sheet '{$namaSheet}' strukturnya tidak dikenali, dilewati.");
                continue;
            }

            // kolom K = semua kolom antara STOK SIAP dan STOK AKHIR
            $kolomK = [];
            for ($i = $idxStokSiap + 1; $i < $idxStokAkhir; $i++) {
                if (! empty(trim((string) ($rows[0][$i] ?? '')))) {
                    $kolomK[$i] = trim((string) $rows[0][$i]);
                }
            }

            $jumlahBarisSheet = 0;

            foreach (array_slice($rows, 1) as $row) {
                $namaBarang = trim(preg_replace('/\s+/', ' ', (string) ($row[$idxNama] ?? '')));

                if ($namaBarang === '') {
                    continue; // baris kosong/pemisah
                }

                $stokAmanBarang = (float) ($row[$idxStokAmanBarang] ?? 0);
                $rak = (float) ($row[$idxRak] ?? 0);
                $inputBarang = (float) ($row[$idxInputBarang] ?? 0);
                $titipPabrik = $idxTitipPabrik !== false ? $row[$idxTitipPabrik] ?? null : null;
                $stokMentahUmma = $idxStokMentahUmma !== false ? $row[$idxStokMentahUmma] ?? null : null;

                // cari/buat barang, cache biar nggak query berulang tiap sheet
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

                // snapshot harian barang
                $barang->harian()->updateOrCreate(
                    ['tanggal' => $tanggal],
                    [
                        'rak' => $rak,
                        'input' => $inputBarang,
                        'um_titip_pabrik' => $titipPabrik !== null && $titipPabrik !== '' ? (float) $titipPabrik : null,
                        'stok_mentah_umma' => $stokMentahUmma !== null && $stokMentahUmma !== '' ? (float) $stokMentahUmma : null,
                    ]
                );

                // alokasi khusus (kolom K yang keisi)
                foreach ($kolomK as $colIdx => $kodeAlokasi) {
                    $nilai = $row[$colIdx] ?? null;
                    if ($nilai === null || $nilai === '' || (float) $nilai == 0) {
                        continue;
                    }

                    StokAlokasiKhususHarian::updateOrCreate(
                        [
                            'barang_gudang_id' => $barang->id,
                            'tanggal' => $tanggal,
                            'kode_alokasi' => $kodeAlokasi,
                        ],
                        ['kuantitas' => (float) $nilai]
                    );
                    $totalAlokasi++;
                }

                // variasi (Table 2) - independen, hanya diisi kalau ada kode variasi
                if ($idxVariasi !== false) {
                    $kodeVariasi = trim((string) ($row[$idxVariasi] ?? ''));

                    if ($kodeVariasi !== '') {
                        $stokAmanVariasiRaw = (string) ($row[$idxVariasi + 1] ?? '');
                        $stokAmanVariasi = (float) preg_replace('/[^0-9.]/', '', $stokAmanVariasiRaw);
                        $stokAwalVariasi = (float) ($row[$idxVariasi + 2] ?? 0);
                        $inputVariasi = (float) ($row[$idxVariasi + 3] ?? 0);
                        $outVariasi = (float) ($row[$idxVariasi + 5] ?? 0);

                        $variasi = StokVariasiGudang::firstOrCreate(
                            [
                                'barang_gudang_id' => $barang->id,
                                'kode_variasi' => $kodeVariasi,
                            ],
                            ['stok_aman' => $stokAmanVariasi]
                        );

                        $variasi->harian()->updateOrCreate(
                            ['tanggal' => $tanggal],
                            [
                                'stok_awal' => $stokAwalVariasi,
                                'input' => $inputVariasi,
                                'out' => $outVariasi,
                            ]
                        );
                        $totalVariasi++;
                    }
                }

                $jumlahBarisSheet++;
            }

            $this->info("Tanggal {$tanggal} (sheet '{$namaSheet}'): {$jumlahBarisSheet} baris barang diproses.");
        }

        $this->info("Selesai. Total barang unik: {$totalBarang}, snapshot variasi: {$totalVariasi}, alokasi khusus: {$totalAlokasi}.");

        return self::SUCCESS;
    }
}
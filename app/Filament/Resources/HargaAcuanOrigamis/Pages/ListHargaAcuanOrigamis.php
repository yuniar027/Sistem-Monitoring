<?php

namespace App\Filament\Resources\HargaAcuanOrigamis\Pages;

use App\Filament\Resources\HargaAcuanOrigamis\HargaAcuanOrigamiResource;
use App\Models\HargaAcuanOrigami;
use App\Models\StokBarangGudang;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ListHargaAcuanOrigamis extends ListRecords
{
    protected static string $resource = HargaAcuanOrigamiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('importHargaAcuanAwan')
                ->label('Import Harga Acuan Awan')
                ->icon('heroicon-o-document-arrow-up')
                ->form([
                    DatePicker::make('berlaku_mulai')
                        ->label('Berlaku Mulai')
                        ->required()
                        ->default(now()),

                    FileUpload::make('file')
                        ->label('File Harga Acuan Awan')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText(
                            'Format Excel .xlsx. Kolom yang dibaca: NAMA dan HARGA.'
                        ),
                ])
                ->action(function (array $data): void {
                    $this->prosesImportHargaAcuanAwan(
                        $data['file'],
                        $data['berlaku_mulai'],
                    );
                }),
        ];
    }

    private function prosesImportHargaAcuanAwan(
        string $filePath,
        string $berlakuMulai,
    ): void {
        try {
            $fullPath = Storage::disk('local')->path($filePath);

            $spreadsheet = IOFactory::load($fullPath);

            $sheet = $spreadsheet->getActiveSheet();

            $rows = $sheet->toArray(
                null,
                true,
                false,
                false
            );

            if (empty($rows)) {
                throw new \RuntimeException(
                    'File Excel tidak memiliki data.'
                );
            }

            /*
             * Catatan: baris pertama TIDAK selalu berupa header tabel.
             * Deteksi header dilakukan per-blok di dalam loop di bawah,
             * lewat cariPetaanHeader(), supaya mendukung file yang berisi
             * banyak blok kecil dengan header "NAMA"/"HARGA" berulang.
             */

            $berhasil = 0;
            $gagal = [];

            DB::transaction(function () use (
                $rows,
                $berlakuMulai,
                &$berhasil,
                &$gagal,
            ): void {
                /*
                 * File Awan tidak selalu berupa satu tabel datar dengan
                 * satu baris header di paling atas. Sering kali isinya
                 * berupa blok-blok kecil per kategori/warna, masing-
                 * masing diawali baris header sendiri ("NAMA" | "HARGA"),
                 * dipisah baris kosong, contoh:
                 *
                 *   STOK HARIAN
                 *        NAMA          HARGA
                 *        SET TUPAI PJ  95000
                 *        SET TUPAI PD  85000
                 *   (baris kosong)
                 *        NAMA          HARGA
                 *        SET IKAN PJ   95000
                 *        ...
                 *
                 * Jadi kita tidak bisa ambil baris pertama sebagai
                 * satu-satunya header. Sebagai gantinya, kita pindai
                 * setiap baris: kalau polanya cocok sebagai header baru,
                 * simpan posisi kolom NAMA & HARGA-nya dan pakai untuk
                 * baris-baris sesudahnya, sampai ketemu header baru lagi.
                 * Baris judul (mis. "STOK HARIAN") dan baris kosong
                 * dilewati tanpa dianggap gagal.
                 */
                $kolom = null;

                foreach ($rows as $index => $row) {
                    $baris = $index + 1;

                    $baginData = array_filter(
                        $row,
                        fn ($nilai) => trim((string) $nilai) !== ''
                    );

                    if (empty($baginData)) {
                        continue;
                    }

                    $kolomBaru = $this->cariPetaanHeader($row);

                    if ($kolomBaru !== null) {
                        $kolom = $kolomBaru;
                        continue;
                    }

                    if ($kolom === null) {
                        // Belum ketemu header sama sekali, misal ini
                        // baris judul di atas header pertama.
                        continue;
                    }

                    $nama = trim((string) ($row[$kolom['nama']] ?? ''));
                    $harga = $row[$kolom['harga']] ?? null;

                    /*
                     * Lewati baris kosong.
                     */
                    if (
                        $nama === ''
                        && ($harga === null || $harga === '')
                    ) {
                        continue;
                    }

                    /*
                     * Validasi nama barang.
                     */
                    if ($nama === '') {
                        $gagal[] = [
                            'baris' => $baris,
                            'nama' => null,
                            'harga' => $harga,
                            'alasan' => 'Nama barang kosong.',
                        ];
                        continue;
                    }

                    /*
                     * Validasi harga.
                     */
                    if (
                        $harga === null
                        || $harga === ''
                        || ! is_numeric($harga)
                        || (float) $harga < 0
                    ) {
                        $gagal[] = [
                            'baris' => $baris,
                            'nama' => $nama,
                            'harga' => $harga,
                            'alasan' => 'Harga tidak valid.',
                        ];
                        continue;
                    }

                    $hargaBaru = (float) $harga;

                    /*
                     * Normalisasi nama dari Excel.
                     */
                    $namaNormal = $this->normalisasiNama($nama);

                    /*
                     * Cari barang berdasarkan nama dan kategori Awan.
                     */
                    $barangDitemukan = StokBarangGudang::query()
                        ->where('kategori', 'awan')
                        ->get()
                        ->filter(function (
                            StokBarangGudang $item
                        ) use ($namaNormal): bool {
                            return $this->normalisasiNama(
                                $item->nama_barang
                            ) === $namaNormal;
                        });

                    /*
                     * Pastikan nama barang tidak ambigu.
                     */
                    if ($barangDitemukan->count() > 1) {
                        $gagal[] = [
                            'baris' => $baris,
                            'nama' => $nama,
                            'harga' => $harga,
                            'alasan' => 'Nama barang Awan tidak unik (cocok dengan lebih dari satu barang master).',
                        ];

                        continue;
                    }

                    $barang = $barangDitemukan->first();

                    /*
                     * Barang tidak ditemukan.
                     */
                    if (! $barang) {
                        $gagal[] = [
                            'baris' => $baris,
                            'nama' => $nama,
                            'harga' => $harga,
                            'alasan' => 'Barang Awan tidak ditemukan di master barang gudang (nama tidak cocok atau kategori bukan Awan).',
                        ];

                        continue;
                    }

                    /*
                     * Ambil harga acuan aktif sebelumnya.
                     */
                    $acuanLama = HargaAcuanOrigami::query()
                        ->where(
                            'barang_gudang_id',
                            $barang->id
                        )
                        ->where('is_active', true)
                        ->orderByDesc('berlaku_mulai')
                        ->lockForUpdate()
                        ->first();

                    $tanggalMulaiBaru = Carbon::parse($berlakuMulai);

                    /*
                     * Jika harga dan tanggal sama, tidak perlu membuat
                     * data harga acuan baru.
                     */
                    $sudahSama = false;

                    if ($acuanLama) {
                        $tanggalLama = $acuanLama->berlaku_mulai
                            ? Carbon::parse($acuanLama->berlaku_mulai)
                            : null;

                        $sudahSama = (
                            abs(
                                (float) $acuanLama->harga_acuan
                                - $hargaBaru
                            ) < 0.01
                            && $tanggalLama
                            && $tanggalLama->isSameDay(
                                $tanggalMulaiBaru
                            )
                        );
                    }

                    if ($sudahSama) {
                        $berhasil++;
                        continue;
                    }

                    /*
                     * Nonaktifkan harga acuan lama.
                     */
                    if ($acuanLama) {
                        $acuanLama->update([
                            'is_active' => false,
                            'berlaku_sampai' => $tanggalMulaiBaru
                                ->copy()
                                ->subDay(),
                        ]);
                    }

                    /*
                     * Simpan harga acuan baru.
                     *
                     * Tabel yang digunakan tetap:
                     * harga_acuan_origami
                     *
                     * Nama model tetap HargaAcuanOrigami karena
                     * struktur tabel saat ini menggunakannya.
                     */
                    HargaAcuanOrigami::create([
                        'barang_gudang_id' => $barang->id,
                        'harga_acuan' => $hargaBaru,
                        'berlaku_mulai' => $tanggalMulaiBaru,
                        'berlaku_sampai' => null,
                        'is_active' => true,
                        'catatan' => 'Import Harga Acuan Awan',
                    ]);

                    $berhasil++;
                }
            });

            /*
             * Buat notifikasi hasil import.
             *
             * Semua baris gagal (bukan cuma 10 pertama) disimpan ke file
             * CSV supaya bisa diperiksa dan diperbaiki satu-satu, karena
             * body notifikasi tidak realistis untuk menampilkan ratusan
             * baris sekaligus.
             */
            $totalGagal = count($gagal);

            $body = "Berhasil memproses {$berhasil} baris.";

            $actions = [];

            if ($totalGagal > 0) {
                $body .= ' ' . number_format($totalGagal) . ' baris gagal.'
                    . ' Unduh detailnya lewat tombol di bawah.';

                $pathLaporan = $this->simpanLaporanBarisGagal($gagal);

                $urlUnduh = Storage::disk('local')->temporaryUrl(
                    $pathLaporan,
                    now()->addMinutes(60),
                );

                $actions[] = Action::make('unduhLaporanGagal')
                    ->label('Unduh Detail Baris Gagal (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($urlUnduh)
                    ->openUrlInNewTab();
            }

            Notification::make()
                ->title('Import Harga Acuan Awan selesai')
                ->body($body)
                ->status(
                    $totalGagal > 0
                        ? 'warning'
                        : 'success'
                )
                ->actions($actions)
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Import Harga Acuan Awan gagal')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Simpan seluruh baris gagal (bukan cuma sebagian) ke file CSV di
     * disk 'local', supaya bisa diunduh dan diperiksa lewat notifikasi.
     *
     * @param  array<int, array{baris: int, nama: ?string, harga: mixed, alasan: string}>  $gagal
     * @return string Path file (relatif terhadap disk 'local').
     */
    private function simpanLaporanBarisGagal(array $gagal): string
    {
        $direktori = 'laporan-import-gagal';

        Storage::disk('local')->makeDirectory($direktori);

        $path = sprintf(
            '%s/harga-acuan-awan-gagal-%s.csv',
            $direktori,
            now()->format('Ymd-His')
        );

        $handle = fopen('php://temp', 'w+');

        // Tambahkan BOM UTF-8 supaya nama barang beraksen tampil benar
        // saat file dibuka lewat Excel.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Baris', 'Nama Barang', 'Harga', 'Alasan Gagal']);

        foreach ($gagal as $baris) {
            fputcsv($handle, [
                $baris['baris'],
                $baris['nama'] ?? '(tidak terbaca)',
                $baris['harga'] ?? '',
                $baris['alasan'],
            ]);
        }

        rewind($handle);
        $isiCsv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $isiCsv);

        return $path;
    }

    /**
     * Cek apakah satu baris Excel adalah baris header blok ("NAMA" /
     * "HARGA"), lalu kembalikan posisi kolomnya. Dipakai supaya file
     * yang isinya banyak blok kecil (tiap blok punya header sendiri,
     * dipisah baris kosong) tetap bisa dibaca, bukan cuma file dengan
     * satu header di baris paling atas.
     *
     * @param  array<int, mixed>  $row
     * @return array{nama: int, harga: int}|null
     */
    private function cariPetaanHeader(array $row): ?array
    {
        $kolomNama = null;
        $kolomHarga = null;

        $labelNama = ['NAMA', 'NAMA BARANG', 'NAMA PRODUK', 'BARANG'];
        $labelHarga = ['HARGA', 'HARGA ACUAN'];

        foreach ($row as $indeks => $nilai) {
            $nilaiBersih = Str::of((string) $nilai)
                ->trim()
                ->squish()
                ->upper()
                ->toString();

            if ($nilaiBersih === '') {
                continue;
            }

            if (in_array($nilaiBersih, $labelNama, true)) {
                $kolomNama = $indeks;
            }

            if (in_array($nilaiBersih, $labelHarga, true)) {
                $kolomHarga = $indeks;
            }
        }

        if ($kolomNama === null || $kolomHarga === null) {
            return null;
        }

        return ['nama' => $kolomNama, 'harga' => $kolomHarga];
    }

    private function normalisasiNama(?string $nama): string
    {
        return Str::of((string) $nama)
            ->upper()
            ->replaceMatches('/\s*-\s*UM$/i', '')
            ->squish()
            ->toString();
    }
}
<?php

namespace App\Services;

use App\Models\StokBarangGudang;
use Illuminate\Support\Collection;
use Smalot\PdfParser\Parser as PdfParserLib;

/**
 * Parser buat PDF "Surat Jalan" (format sama kayak invoice, tapi tanpa
 * nominal -- cuma kode barang, nama barang, dan qty per kolom rak).
 *
 * Kode barang dicocokkan LANGSUNG ke daftar kode_barang asli yang sudah
 * terdaftar di Master Barang Gudang (bukan ditebak lewat pola/panjang
 * karakter) -- soalnya smalot/pdfparser kadang nggak nyisipin spasi
 * antara kode dan nama barang (mis. "SLBJ0287BAJU SET..." padahal
 * aslinya "SLBJ0287 BAJU SET..."), yang bikin regex tebak-tebakan gampang
 * salah potong. Kalau kode di PDF ternyata belum ada di master (barang
 * baru), baris itu jatuh ke fallback pola generik dan otomatis ditandai
 * "belum terdaftar" -- tetap aman, cuma perlu didaftarin dulu manual
 * sebelum bisa disimpan.
 *
 * CATATAN: parser ini ditulis berdasarkan struktur teks dari 2 contoh PDF
 * asli yang dikasih. WAJIB dites dulu pakai PDF asli sebelum dipakai
 * produksi -- kasih tau aku screenshot preview-nya kalau ada yang meleset.
 */
class SuratJalanPdfParser
{
    private const BULAN = [
        'JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4,
        'MEI' => 5, 'JUNI' => 6, 'JULI' => 7, 'AGUSTUS' => 8,
        'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12,
    ];

    /** @var array<int, string>|null kode_barang asli, diurutkan terpanjang dulu */
    private ?array $kodeDikenal = null;

    /**
     * @return array{
     *   tanggal: ?string,
     *   gudang: ?string,
     *   items: array<int, array{kode: string, nama: string, qty: float, terdaftar: bool}>,
     *   item_tanpa_qty: array<int, array{kode: string, nama: string}>,
     *   total_terbaca: float,
     *   total_footer: ?float,
     *   cocok: bool,
     * }
     */
    public function parse(string $path): array
    {
        $baris = $this->ambilBarisTeks($path);

        return $this->parseBaris($baris);
    }

    /**
     * Ambil teks mentah per baris (buat debugging kalau parsing meleset).
     *
     * @return Collection<int, string>
     */
    public function debugTeksMentah(string $path): Collection
    {
        return $this->ambilBarisTeks($path);
    }

    /**
     * @return array<int, string>
     */
    private function daftarKodeDikenal(): array
    {
        if ($this->kodeDikenal === null) {
            $this->kodeDikenal = StokBarangGudang::query()
                ->whereNotNull('kode_barang')
                ->where('kode_barang', '!=', '')
                ->pluck('kode_barang')
                ->unique()
                ->sortByDesc(fn ($k) => strlen($k)) // cocokkan yang paling spesifik/panjang dulu
                ->values()
                ->all();
        }

        return $this->kodeDikenal;
    }

    /**
     * Cari apakah suatu baris DIAWALI oleh salah satu kode_barang yang
     * sudah terdaftar. Return [kode, sisa_teks_setelah_kode] atau null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function cocokkanKodeTerdaftar(string $baris): ?array
    {
        foreach ($this->daftarKodeDikenal() as $kode) {
            if (str_starts_with($baris, $kode)) {
                $sisa = trim(substr($baris, strlen($kode)));

                if ($sisa !== '') {
                    return [$kode, $sisa];
                }
            }
        }

        return null;
    }

    /**
     * Fallback kalau kode belum terdaftar di master (barang baru) --
     * tebak pola generik: 2 huruf + opsional 1 digit + 2 huruf + 3-4
     * digit (mis. SLBJ0287, CM1RM068). WAJIB ada spasi setelah kode di
     * jalur ini, biar nggak salah potong nama kayak kasus BAJU yang
     * nempel tanpa spasi.
     *
     * @return array{0: string, 1: string}|null
     */
    private function tebakKodeBelumTerdaftar(string $baris): ?array
    {
        if (! preg_match('/^([A-Z]{2}[0-9]?[A-Z]{2}[0-9]{3,4})(.+)$/', $baris, $m)) {
            return null;
        }

        $sisa = trim($m[2]);

        return $sisa !== '' ? [$m[1], $sisa] : null;
    }

    /**
     * @return Collection<int, string>
     */
    private function ambilBarisTeks(string $path): Collection
    {
        $parser = new PdfParserLib();
        $pdf = $parser->parseFile($path);
        $teks = $pdf->getText();

        return collect(preg_split('/\r\n|\r|\n/', $teks))
            ->map(fn ($b) => trim(preg_replace('/\s+/', ' ', (string) $b)))
            ->filter(fn ($b) => $b !== '')
            ->values();
    }

    /**
     * @param  Collection<int, string>  $baris
     */
    private function parseBaris(Collection $baris): array
    {
        $tanggal = $this->cariTanggal($baris);
        $gudang = $this->cariGudang($baris);

        $items = [];
        /** @var array<int, array{kode: string, nama: string, terdaftar: bool}> $pending */
        $pending = [];
        $totalFooter = null;

        foreach ($baris as $b) {
            if ($this->adalahBarisSampah($b)) {
                continue;
            }

            $cocokTerdaftar = $this->cocokkanKodeTerdaftar($b);

            if ($cocokTerdaftar !== null) {
                $pending[] = ['kode' => $cocokTerdaftar[0], 'nama' => $cocokTerdaftar[1], 'terdaftar' => true];

                continue;
            }

            $cocokTebakan = $this->tebakKodeBelumTerdaftar($b);

            if ($cocokTebakan !== null) {
                $pending[] = ['kode' => $cocokTebakan[0], 'nama' => $cocokTebakan[1], 'terdaftar' => false];

                continue;
            }

            $token = explode(' ', $b);

            // Baris angka murni (semua token numerik).
            if ($this->semuaAngka($token)) {
                $angkaTerakhir = (float) str_replace(',', '.', end($token));

                // Baris dengan banyak token (>= 10) = baris TOTAL
                // footer per kolom rak (1..N + TOTAL), bukan baris
                // per-item.
                if (count($token) >= 10) {
                    $totalFooter = $angkaTerakhir;

                    continue;
                }

                // Belum ada barang yang "nunggu" dipasangkan -- ini
                // baris angka nyasar (biasanya footer yang keekstrak
                // duluan karena quirk urutan PDF). Simpan sebagai
                // kandidat total, jangan dipasangkan ke item manapun.
                if (empty($pending)) {
                    $totalFooter = $angkaTerakhir;

                    continue;
                }

                $item = array_shift($pending);
                $items[] = [
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'qty' => $angkaTerakhir,
                    'terdaftar' => $item['terdaftar'],
                ];
            }
        }

        $totalTerbaca = round(array_sum(array_column($items, 'qty')), 2);

        return [
            'tanggal' => $tanggal,
            'gudang' => $gudang,
            'items' => $items,
            'item_tanpa_qty' => $pending,
            'total_terbaca' => $totalTerbaca,
            'total_footer' => $totalFooter,
            'cocok' => $totalFooter === null ? true : abs($totalFooter - $totalTerbaca) < 0.01,
        ];
    }

    private function adalahBarisSampah(string $baris): bool
    {
        $atas = mb_strtoupper($baris);

        if (str_contains($atas, 'SURAT JALAN')) {
            return true;
        }

        if (str_contains($atas, 'UMMA BABY SHOP')) {
            return true;
        }

        if (preg_match('/^GUDANG\s*\d+$/', $atas)) {
            return true;
        }

        if (in_array($atas, ['KODE', 'NAMA BARANG', 'BARANG', 'KODE NAMA BARANG'], true)) {
            return true;
        }

        // Baris header kolom rak, mis. "1 2 3 ... 18 TOTAL G1".
        if (str_contains($atas, 'TOTAL G')) {
            return true;
        }

        // Baris tanggal, mis. "24 SEPTEMBER 2026".
        if (preg_match('/^\d{1,2}\s+[A-Z]+\s+\d{4}$/', $atas)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $token
     */
    private function semuaAngka(array $token): bool
    {
        if (empty($token)) {
            return false;
        }

        foreach ($token as $t) {
            if (! preg_match('/^\d+([.,]\d+)?$/', $t)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, string>  $baris
     */
    private function cariTanggal(Collection $baris): ?string
    {
        foreach ($baris as $b) {
            if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/', $b, $m)) {
                $bulan = self::BULAN[mb_strtoupper($m[2])] ?? null;

                if ($bulan !== null) {
                    return sprintf('%04d-%02d-%02d', (int) $m[3], $bulan, (int) $m[1]);
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, string>  $baris
     */
    private function cariGudang(Collection $baris): ?string
    {
        foreach ($baris as $b) {
            if (preg_match('/^GUDANG\s*(\d+)$/i', trim($b), $m)) {
                return 'Gudang ' . $m[1];
            }
        }

        return null;
    }
}
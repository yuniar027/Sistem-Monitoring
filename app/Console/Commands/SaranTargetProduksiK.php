<?php

namespace App\Console\Commands;

use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokAlokasiKhususHarian;
use App\Models\StokVariasiGudang;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SaranTargetProduksiK extends Command
{
    protected $signature = 'stok:saran-target-produksi-k
        {--apply-proses : Buat Proses K + Target Variasi untuk kode yang multiplier-nya SUDAH terverifikasi dari audit histori Excel (lihat MULTIPLIER_TERVERIFIKASI). Kode lain (belum ada bukti) cuma dibuatkan Proses K-nya, target+multiplier tetap manual lewat menu Target Produksi K.}';

    protected $description = 'Cari kandidat pasangan Proses K <-> Variasi Target dari histori Alokasi Khusus, buat mempercepat isi menu Target Produksi K';

    private const MULTIPLIER_TERVERIFIKASI = [
        '12' => 6,
        '18' => 4,
        '27' => 6,
        '33' => 4,
        '39/30' => 4,
    ];

    private const KODE_GENERIK_KHUSUS = [
        '3SET' => [
            'pola' => ['3S BTG', '3S PD', '3S PJG'],
            'multiplier' => 4,
        ],
    ];

    public function handle(): int
    {
        $applyProses = (bool) $this->option('apply-proses');

        $riwayatAlokasi = StokAlokasiKhususHarian::query()
            ->selectRaw('kode_alokasi, COUNT(DISTINCT tanggal) as jumlah_hari, SUM(kuantitas) as total_kuantitas')
            ->groupBy('kode_alokasi')
            ->orderByDesc('jumlah_hari')
            ->get();

        if ($riwayatAlokasi->isEmpty()) {
            $this->info('Tidak ada histori Alokasi Khusus sama sekali, tidak ada yang bisa disarankan.');

            return self::SUCCESS;
        }

        $semuaVariasi = StokVariasiGudang::query()
            ->whereNotNull('kode_variasi')
            ->get()
            ->groupBy(fn (StokVariasiGudang $v) => $this->normalisasi($v->kode_variasi));

        $prosesTerdaftar = ProductionProcess::query()
            ->pluck('kode_proses')
            ->map(fn ($kode) => $this->normalisasi($kode))
            ->flip()
            ->all();

        $rows = [];
        $dibuat = 0;

        foreach ($riwayatAlokasi as $alokasi) {
            $kodeAsli = trim((string) $alokasi->kode_alokasi);
            $tanpaPrefixK = $this->normalisasi(preg_replace('/^\s*K\s+/i', '', $kodeAsli));
            $normalPenuh = $this->normalisasi($kodeAsli);

            if (isset($prosesTerdaftar[$normalPenuh]) || isset($prosesTerdaftar[$tanpaPrefixK])) {
                $rows[] = [$kodeAsli, $alokasi->jumlah_hari, number_format((float) $alokasi->total_kuantitas, 2), '-', '-', 'Sudah ada Proses K'];

                continue;
            }

            $kodeTanpaSpasi = str_replace(' ', '', $tanpaPrefixK);
            $generikKhusus = self::KODE_GENERIK_KHUSUS[$kodeTanpaSpasi] ?? null;

            if ($generikKhusus) {
                $variasiGenerik = $semuaVariasi->flatten(1)
                    ->filter(fn (StokVariasiGudang $v) => in_array($this->normalisasi($v->kode_variasi), $generikKhusus['pola'], true))
                    ->values();

                $namaVariasiCocok = $variasiGenerik->count() . ' variasi lintas motif (' . implode('/', $generikKhusus['pola']) . ')';
                $status = 'Kandidat generik khusus, multiplier terverifikasi (' . $generikKhusus['multiplier'] . ')';

                if ($applyProses) {
                    $proses = ProductionProcess::firstOrCreate(
                        ['kode_proses' => $kodeAsli],
                        ['nama_proses' => $kodeAsli]
                    );
                    $dibuat++;

                    $dibuatTarget = 0;
                    foreach ($variasiGenerik as $variasi) {
                        $target = ProductionProcessTarget::firstOrCreate(
                            [
                                'production_process_id' => $proses->id,
                                'variasi_gudang_id' => $variasi->id,
                            ],
                            ['multiplier' => $generikKhusus['multiplier']]
                        );

                        if ($target->wasRecentlyCreated) {
                            $dibuatTarget++;
                        }
                    }

                    $status = "Proses K + {$dibuatTarget} Target dibuat otomatis (multiplier {$generikKhusus['multiplier']}, terverifikasi)";
                }

                $rows[] = [$kodeAsli, $alokasi->jumlah_hari, number_format((float) $alokasi->total_kuantitas, 2), $namaVariasiCocok, 'Cocok generik khusus', $status];

                continue;
            }

            $cocok = $semuaVariasi[$tanpaPrefixK] ?? null;
            $tingkat = 'Cocok pas';

            if (! $cocok) {
                $tanpaSpasi = str_replace(' ', '', $tanpaPrefixK);

                foreach ($semuaVariasi as $kunci => $grup) {
                    if (str_replace(' ', '', $kunci) === $tanpaSpasi && $tanpaSpasi !== '') {
                        $cocok = $grup;
                        $tingkat = 'Cocok longgar (beda spasi)';
                        break;
                    }
                }
            }

            $variasiGenerik = null;
            $multiplierGenerik = null;
            if (! $cocok) {
                $angka = $this->angkaDariKode($tanpaPrefixK);

                if ($angka !== null) {
                    $variasiGenerik = $semuaVariasi->flatten(1)
                        ->filter(fn (StokVariasiGudang $v) => $this->angkaDariKode($v->kode_variasi) === $angka)
                        ->values();

                    if ($variasiGenerik->isNotEmpty()) {
                        $multiplierGenerik = self::MULTIPLIER_TERVERIFIKASI[$angka] ?? null;
                        $tingkat = $multiplierGenerik !== null
                            ? 'Cocok generik (semua motif, angka sama, multiplier terverifikasi)'
                            : 'Cocok generik TAPI multiplier BELUM ada bukti dari histori Excel';
                    }
                }
            }

            if (! $cocok && ($variasiGenerik === null || $variasiGenerik->isEmpty())) {
                $rows[] = [$kodeAsli, $alokasi->jumlah_hari, number_format((float) $alokasi->total_kuantitas, 2), '-', '-', 'Gak ada yang cocok, tinjau manual'];

                continue;
            }

            if ($variasiGenerik !== null && $variasiGenerik->isNotEmpty()) {
                $namaVariasiCocok = $variasiGenerik->count() . ' variasi lintas motif (kode angka sama)';
                $status = $multiplierGenerik !== null
                    ? 'Kandidat baru (generik, multiplier terverifikasi)'
                    : 'Kandidat baru (generik, TAPI multiplier belum terverifikasi -- HANYA Proses K dibuat)';

                if ($applyProses) {
                    $proses = ProductionProcess::firstOrCreate(
                        ['kode_proses' => $kodeAsli],
                        ['nama_proses' => $kodeAsli]
                    );
                    $dibuat++;

                    if ($multiplierGenerik !== null) {
                        $dibuatTarget = 0;
                        foreach ($variasiGenerik as $variasi) {
                            $target = ProductionProcessTarget::firstOrCreate(
                                [
                                    'production_process_id' => $proses->id,
                                    'variasi_gudang_id' => $variasi->id,
                                ],
                                ['multiplier' => $multiplierGenerik]
                            );

                            if ($target->wasRecentlyCreated) {
                                $dibuatTarget++;
                            }
                        }

                        $status = "Proses K + {$dibuatTarget} Target dibuat otomatis (multiplier {$multiplierGenerik}, terverifikasi)";
                    } else {
                        $status = 'Proses K dibuat. Multiplier BELUM ada bukti dari histori Excel -- isi Target+multiplier manual lewat menu Target Produksi K, JANGAN asal isi 1.';
                    }
                }

                $rows[] = [$kodeAsli, $alokasi->jumlah_hari, number_format((float) $alokasi->total_kuantitas, 2), $namaVariasiCocok, $tingkat, $status];

                continue;
            }

            $namaVariasiCocok = $cocok->pluck('kode_variasi')->unique()->implode(', ');
            $multiplierCocok = self::MULTIPLIER_TERVERIFIKASI[$tanpaPrefixK] ?? null;
            $status = $multiplierCocok !== null
                ? 'Kandidat baru (cocok pas, multiplier terverifikasi)'
                : 'Kandidat baru';

            if ($applyProses) {
                $proses = ProductionProcess::firstOrCreate(
                    ['kode_proses' => $kodeAsli],
                    ['nama_proses' => $kodeAsli]
                );
                $dibuat++;

                if ($multiplierCocok !== null) {
                    $dibuatTarget = 0;
                    foreach ($cocok as $variasi) {
                        $target = ProductionProcessTarget::firstOrCreate(
                            [
                                'production_process_id' => $proses->id,
                                'variasi_gudang_id' => $variasi->id,
                            ],
                            ['multiplier' => $multiplierCocok]
                        );

                        if ($target->wasRecentlyCreated) {
                            $dibuatTarget++;
                        }
                    }

                    $status = "Proses K + {$dibuatTarget} Target dibuat otomatis (multiplier {$multiplierCocok}, terverifikasi)";
                } else {
                    $status = 'Proses K dibuat, tinggal set target + multiplier';
                }
            }

            $rows[] = [$kodeAsli, $alokasi->jumlah_hari, number_format((float) $alokasi->total_kuantitas, 2), $namaVariasiCocok, $tingkat, $status];
        }

        $this->table(
            ['Kode Alokasi (Excel)', 'Jumlah Hari Dipakai', 'Total Kuantitas', 'Kode Variasi Cocok', 'Tingkat Cocok', 'Status'],
            $rows
        );

        $this->newLine();

        if ($applyProses) {
            $this->info("Selesai. {$dibuat} Proses K baru dibuat/ditemukan dari kandidat yang cocok.");
            $this->comment('Kode dengan multiplier TERVERIFIKASI: Target Variasi sudah otomatis lengkap, langsung bisa dipakai di Input Stok Harian.');
            $this->comment('Kode LAIN (termasuk K 48/K 50 -- tidak ada bukti formula historisnya sama sekali): cuma Proses K yang dibuat. Buka menu Target Produksi K, cek dulu ke Umma/koordinator apa angka pengalinya sebelum diisi -- JANGAN diisi 1 asal-asalan.');
            $this->warn('Ingat pengecualian K 33 motif "J.S C.UNGGU" (harusnya x6, bukan x4) -- cari target itu di menu Target Produksi K dan ubah manual.');
        } else {
            $this->info('Ini laporan saran saja, belum ada yang dibuat/diubah.');
            $this->comment('Jalankan ulang dengan --apply-proses untuk otomatis membuat Proses K (dan Target+multiplier untuk yang sudah terverifikasi) pada baris berstatus "Kandidat baru"/"Cocok generik khusus". Baris "Gak ada yang cocok" tetap perlu ditinjau manual satu-satu.');
        }

        return self::SUCCESS;
    }

    private function normalisasi(string $teks): string
    {
        return Str::of($teks)->trim()->upper()->squish()->toString();
    }

    private function angkaDariKode(string $kode): ?string
    {
        $bersih = strtoupper(str_replace(' ', '', trim($kode)));

        if (preg_match('/^(\d+)(PCS)?$/', $bersih, $cocok)) {
            return $cocok[1];
        }

        if (preg_match('/^(\d+\/\d+)$/', $bersih, $cocok)) {
            return $cocok[1];
        }

        return null;
    }
}
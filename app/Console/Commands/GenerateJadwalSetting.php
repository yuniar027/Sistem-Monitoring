<?php

namespace App\Console\Commands;

use App\Models\BahanBakuStok;
use App\Models\Packer;
use App\Models\ProdukMaster;
use App\Models\ResepPaketItem;
use App\Models\StokPaket;
use App\Models\TugasPacking;
use Illuminate\Console\Command;

class GenerateJadwalSetting extends Command
{
    protected $signature = 'jadwal:generate';
    protected $description = 'Generate Tugas Packing otomatis, dibagi rata (bernomor urut) sesuai jumlah packer aktif';

    public function handle(): int
    {
        $jumlahPacker = Packer::where('status', 'aktif')->count();

        if ($jumlahPacker === 0) {
            $this->error('Tidak ada packer aktif.');
            return self::FAILURE;
        }

        $daftarKebutuhan = [];

        $produkList = ProdukMaster::where('tipe_produk', 'rakitan')
            ->whereNotNull('target_stok_minimum')
            ->where('target_stok_minimum', '>', 0)
            ->get();

        foreach ($produkList as $produk) {
            $resepItems = ResepPaketItem::where('sku', $produk->sku)->get();
            if ($resepItems->isEmpty()) {
                continue;
            }

            $stokSekarang = (int) StokPaket::where('sku', $produk->sku)
                ->where('status', 'belum_distribusi')
                ->sum('jumlah_paket');

            if ($stokSekarang >= $produk->target_stok_minimum) {
                continue;
            }

            $kekurangan = $produk->target_stok_minimum - $stokSekarang;

            $maksBisaDirakit = null;
            foreach ($resepItems as $item) {
                $stokBahan = BahanBakuStok::where('bahan_baku_id', $item->bahan_baku_id)->first();
                $tersedia = $stokBahan ? $stokBahan->kuantitas_tersedia : 0;
                $bisaDariItemIni = (int) floor($tersedia / $item->kuantitas_per_paket);

                $maksBisaDirakit = is_null($maksBisaDirakit)
                    ? $bisaDariItemIni
                    : min($maksBisaDirakit, $bisaDariItemIni);
            }

            $target = min($kekurangan, $maksBisaDirakit ?? 0);

            if ($target > 0) {
                $daftarKebutuhan[] = ['sku' => $produk->sku, 'jumlah' => $target];
            }
        }

        if (empty($daftarKebutuhan)) {
            $this->info('Tidak ada SKU yang butuh restock hari ini.');
            return self::SUCCESS;
        }

        // Bersihkan tugas auto-generate hari ini yang lama (biar generate ulang nggak numpuk)
        TugasPacking::where('tanggal_dibuat', now()->toDateString())
            ->where('status', 'belum_dikerjakan')
            ->where('dari_urutan_kedatangan', true)
            ->delete();

        $totalUnit = array_sum(array_column($daftarKebutuhan, 'jumlah'));
        $jatahDasar = intdiv($totalUnit, $jumlahPacker);
        $sisa = $totalUnit % $jumlahPacker;

        $indexSku = 0;
        $sisaSkuSaatIni = $daftarKebutuhan[0]['jumlah'];
        $dibuat = 0;

        for ($nomor = 1; $nomor <= $jumlahPacker; $nomor++) {
            $jatah = $jatahDasar + ($nomor <= $sisa ? 1 : 0);

            while ($jatah > 0 && $indexSku < count($daftarKebutuhan)) {
                $ambil = min($jatah, $sisaSkuSaatIni);

                if ($ambil > 0) {
                    TugasPacking::create([
                        'sku' => $daftarKebutuhan[$indexSku]['sku'],
                        'channel_tujuan' => 'shopee',
                        'kuantitas' => $ambil,
                        'status' => 'belum_dikerjakan',
                        'ditugaskan_ke' => $nomor,
                        'tanggal_dibuat' => now()->toDateString(),
                        'dari_urutan_kedatangan' => true,
                    ]);
                    $dibuat++;
                }

                $jatah -= $ambil;
                $sisaSkuSaatIni -= $ambil;

                if ($sisaSkuSaatIni <= 0) {
                    $indexSku++;
                    $sisaSkuSaatIni = $daftarKebutuhan[$indexSku]['jumlah'] ?? 0;
                }
            }
        }

        $this->info("Jadwal setting selesai: {$dibuat} tugas dibuat, bernomor 1 sampai {$jumlahPacker}.");

        return self::SUCCESS;
    }
}
<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\BahanBakuMasuk;
use App\Models\ProdukMaster;
use App\Models\ResepPaketItem;

class HppService
{
    /**
     * Hitung HPP satu SKU dari resep (BOM) x rata-rata harga beli bahan baku.
     * Return null kalau resep belum lengkap ATAU ada bahan baku yang belum
     * pernah dicatat pembeliannya sama sekali (harga tidak diketahui).
     */
    public function hitungHpp(string $sku): ?float
    {
        $resepItems = ResepPaketItem::where('sku', $sku)->get();

        if ($resepItems->isEmpty()) {
            return null;
        }

        $totalHpp = 0;

        foreach ($resepItems as $item) {
            $bahanBaku = BahanBaku::find($item->bahan_baku_id);

            if (! $bahanBaku || ! $bahanBaku->isi_per_satuan_beli) {
                return null;
            }

            $rataRataHargaBeli = BahanBakuMasuk::where('bahan_baku_id', $item->bahan_baku_id)->avg('harga_satuan');

            if ($rataRataHargaBeli === null) {
                return null;
            }

            $hargaPerPcs = $rataRataHargaBeli / $bahanBaku->isi_per_satuan_beli;
            $totalHpp += $hargaPerPcs * $item->kuantitas_per_paket;
        }

        return round($totalHpp, 2);
    }

    /**
     * Hitung dan simpan HPP ke produk_master.harga_modal_default.
     * Return true kalau berhasil update, false kalau di-skip (data belum lengkap).
     */
    public function updateHppProduk(string $sku): bool
    {
        $hpp = $this->hitungHpp($sku);

        if ($hpp === null) {
            return false;
        }

        ProdukMaster::where('sku', $sku)->update(['harga_modal_default' => $hpp]);

        return true;
    }

    /**
     * Hitung ulang HPP untuk semua SKU yang punya resep (tipe_produk = rakitan).
     * Return ringkasan: berapa berhasil, berapa di-skip beserta alasannya.
     */
    public function updateHppSemuaProdukRakitan(): array
    {
        $skuList = ResepPaketItem::distinct()->pluck('sku');

        $berhasil = [];
        $dilewati = [];

        foreach ($skuList as $sku) {
            if ($this->updateHppProduk($sku)) {
                $berhasil[] = $sku;
            } else {
                $dilewati[] = $sku;
            }
        }

        return [
            'berhasil' => $berhasil,
            'dilewati' => $dilewati,
        ];
    }
}

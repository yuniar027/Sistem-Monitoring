<?php

namespace App\Http\Controllers;

use App\Models\ProdukMaster;
use App\Models\StokPaket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokRendahController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->header('X-Webhook-Token') !== config('services.n8n.webhook_token')) {
            abort(401, 'Unauthorized');
        }

        // Total stok siap distribusi per SKU (belum dialokasikan ke etalase manapun)
        $stokPerSku = StokPaket::query()
            ->selectRaw('sku, SUM(kuantitas_per_paket * jumlah_paket) as stok_tersedia')
            ->where('status', 'belum_distribusi')
            ->groupBy('sku')
            ->pluck('stok_tersedia', 'sku');

        $stokRendah = ProdukMaster::query()
            ->where('target_stok_minimum', '>', 0)
            ->get()
            ->map(function (ProdukMaster $produk) use ($stokPerSku) {
                return [
                    'sku' => $produk->sku,
                    'nama_produk' => $produk->nama_produk,
                    'stok_tersedia' => (int) ($stokPerSku[$produk->sku] ?? 0),
                    'target_stok_minimum' => $produk->target_stok_minimum,
                ];
            })
            ->filter(fn ($item) => $item['stok_tersedia'] < $item['target_stok_minimum'])
            ->values();

        return response()->json([
            'jumlah_sku_rendah' => $stokRendah->count(),
            'data' => $stokRendah,
        ]);
    }
}
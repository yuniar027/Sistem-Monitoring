<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenjualanWebhookRequest;
use App\Http\Requests\StokMasukWebhookRequest;
use App\Http\Requests\AlokasiAiWebhookRequest;
use App\Http\Requests\UpdateEtalaseWebhookRequest;
use App\Jobs\ProcessPenjualanWebhook;
use App\Jobs\ProcessStokMasukWebhook;
use App\Jobs\ProcessAlokasiAiWebhook;
use App\Jobs\ProcessUpdateEtalaseWebhook;
use App\Models\WebhookLog;
use App\Models\ProdukMaster;
use App\Models\StokMentah;
use App\Models\StokPaket;
use App\Models\AlokasiEtalase;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function stokMasuk(StokMasukWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $externalId = $validated['external_id'];

        $existingLog = WebhookLog::where('external_id', $externalId)->first();

        if ($existingLog && $existingLog->processed_at !== null) {
            return Response::json(['message' => 'Already processed'], 202);
        }

        if (! $existingLog) {
            try {
                WebhookLog::create([
                    'source' => 'n8n',
                    'event_type' => 'stok_masuk',
                    'external_id' => $externalId,
                    'payload' => $validated,
                    'processed_at' => null,
                ]);
            } catch (QueryException $exception) {
                $existingLog = WebhookLog::where('external_id', $externalId)->first();

                if ($existingLog && $existingLog->processed_at !== null) {
                    return Response::json(['message' => 'Already processed'], 202);
                }
            }
        }

        ProcessStokMasukWebhook::dispatch($validated);

        return Response::json(['message' => 'Accepted'], 202);
    }

    public function penjualan(PenjualanWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $externalId = $validated['external_id'];

        $existingLog = WebhookLog::where('external_id', $externalId)->first();

        if ($existingLog && $existingLog->processed_at !== null) {
            return Response::json(['message' => 'Already processed'], 202);
        }

        if (! $existingLog) {
            try {
                WebhookLog::create([
                    'source' => 'n8n',
                    'event_type' => 'penjualan',
                    'external_id' => $externalId,
                    'payload' => $validated,
                    'processed_at' => null,
                ]);
            } catch (QueryException $exception) {
                $existingLog = WebhookLog::where('external_id', $externalId)->first();

                if ($existingLog && $existingLog->processed_at !== null) {
                    return Response::json(['message' => 'Already processed'], 202);
                }
            }
        }

        ProcessPenjualanWebhook::dispatch($validated);

        return Response::json(['message' => 'Accepted'], 202);
    }

    public function stok(Request $request, string $sku): JsonResponse
    {
        $produk = ProdukMaster::where('sku', $sku)->first();

        if (! $produk) {
            return Response::json(['message' => 'SKU not found: ' . $sku], 404);
        }

        $stokMentah = StokMentah::where('sku', $sku)->first();
        $stokMentahQty = $stokMentah ? (int) $stokMentah->kuantitas_tersedia : 0;

        $paketTotals = StokPaket::where('sku', $sku)
            ->selectRaw("status, COALESCE(SUM(kuantitas_per_paket * jumlah_paket), 0) as total")
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $paketBelum = isset($paketTotals['belum_distribusi']) ? (int) $paketTotals['belum_distribusi'] : 0;
        $paketSudah = isset($paketTotals['sudah_distribusi']) ? (int) $paketTotals['sudah_distribusi'] : 0;

        $alokasiRows = AlokasiEtalase::where('sku', $sku)
            ->where('status', 'aktif')
            ->selectRaw('channel, SUM(kuantitas_dialokasikan) as kuantitas_dialokasikan, SUM(kuantitas_terjual) as kuantitas_terjual, SUM(kuantitas_sisa) as kuantitas_sisa')
            ->groupBy('channel')
            ->get();

        $alokasiByChannel = [];

        foreach ($alokasiRows as $row) {
            $alokasiByChannel[$row->channel] = [
                'kuantitas_dialokasikan' => (int) $row->kuantitas_dialokasikan,
                'kuantitas_terjual' => (int) $row->kuantitas_terjual,
                'kuantitas_sisa' => (int) $row->kuantitas_sisa,
            ];
        }

        return Response::json([
            'sku' => $sku,
            'stok_mentah' => $stokMentahQty,
            'stok_paket' => [
                'belum_distribusi' => $paketBelum,
                'sudah_distribusi' => $paketSudah,
            ],
            'alokasi_etalase' => $alokasiByChannel,
        ]);
    }

        public function alokasiAi(AlokasiAiWebhookRequest $request): JsonResponse
        {
            $validated = $request->validated();
            $externalId = $validated['external_id'];

            $existingLog = WebhookLog::where('external_id', $externalId)->first();

            if ($existingLog && $existingLog->processed_at !== null) {
                return Response::json(['message' => 'Already processed'], 202);
            }

            if (! $existingLog) {
                try {
                    WebhookLog::create([
                        'source' => 'n8n',
                        'event_type' => 'alokasi_ai',
                        'external_id' => $externalId,
                        'payload' => $validated,
                        'processed_at' => null,
                    ]);
                } catch (QueryException $exception) {
                    $existingLog = WebhookLog::where('external_id', $externalId)->first();

                    if ($existingLog && $existingLog->processed_at !== null) {
                        return Response::json(['message' => 'Already processed'], 202);
                    }
                }
            }

            ProcessAlokasiAiWebhook::dispatch($validated);

            return Response::json(['message' => 'Accepted'], 202);
        }

        public function updateEtalase(UpdateEtalaseWebhookRequest $request): JsonResponse
        {
            $validated = $request->validated();
            $externalId = $validated['external_id'];

            $existingLog = WebhookLog::where('external_id', $externalId)->first();

            if ($existingLog && $existingLog->processed_at !== null) {
                return Response::json(['message' => 'Already processed'], 202);
            }

            if (! $existingLog) {
                try {
                    WebhookLog::create([
                        'source' => 'n8n',
                        'event_type' => 'update_etalase',
                        'external_id' => $externalId,
                        'payload' => $validated,
                        'processed_at' => null,
                    ]);
                } catch (QueryException $exception) {
                    $existingLog = WebhookLog::where('external_id', $externalId)->first();

                    if ($existingLog && $existingLog->processed_at !== null) {
                        return Response::json(['message' => 'Already processed'], 202);
                    }
                }
            }

            ProcessUpdateEtalaseWebhook::dispatch($validated);

            return Response::json(['message' => 'Accepted'], 202);
        }
}

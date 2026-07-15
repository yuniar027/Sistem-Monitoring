<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenjualanWebhookRequest;
use App\Http\Requests\StokMasukWebhookRequest;
use App\Jobs\ProcessPenjualanWebhook;
use App\Jobs\ProcessStokMasukWebhook;
use App\Models\WebhookLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

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
}

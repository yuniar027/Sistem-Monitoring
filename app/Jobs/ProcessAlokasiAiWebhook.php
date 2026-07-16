<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use App\Models\WebhookLog;
use App\Services\StokPaketService;
use App\Services\AlokasiEtalaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessAlokasiAiWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(StokPaketService $paketService, AlokasiEtalaseService $alokasiService): void
    {
        // create paket
        try {
            $paketService->buatPaket([
                'sku' => $this->data['sku'],
                'kuantitas_per_paket' => $this->data['kuantitas_per_paket'],
                'jumlah_paket' => $this->data['jumlah_paket'],
                'tanggal_dibuat' => $this->data['tanggal_dibuat'] ?? now()->toDateString(),
            ]);
        } catch (ValidationException $e) {
            ErrorLog::create([
                'source' => 'webhook.alokasi_ai',
                'payload' => $this->data,
                'error_message' => $e->getMessage(),
                'resolved' => false,
                'created_at' => now(),
            ]);
        }

        // create allocations
        foreach ($this->data['alokasi'] as $item) {
            try {
                $alokasiService->buatAlokasi([
                    'sku' => $this->data['sku'],
                    'channel' => $item['channel'],
                    'nama_toko' => $item['nama_toko'],
                    'kuantitas_dialokasikan' => $item['kuantitas_dialokasikan'],
                    'tanggal_alokasi' => $item['tanggal_alokasi'] ?? now()->toDateString(),
                ]);
            } catch (ValidationException $e) {
                ErrorLog::create([
                    'source' => 'webhook.alokasi_ai',
                    'payload' => array_merge($this->data, ['failed_allocation' => $item]),
                    'error_message' => $e->getMessage(),
                    'resolved' => false,
                    'created_at' => now(),
                ]);
            }
        }

        WebhookLog::where('external_id', $this->data['external_id'])
            ->update(['processed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ErrorLog::create([
            'source' => 'webhook.alokasi_ai',
            'payload' => $this->data,
            'error_message' => $exception->getMessage(),
            'resolved' => false,
            'created_at' => now(),
        ]);
    }
}

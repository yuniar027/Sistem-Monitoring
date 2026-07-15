<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use App\Models\WebhookLog;
use App\Services\StokMasukService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessStokMasukWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(StokMasukService $service): void
    {
        $service->catatStokMasuk($this->data);

        WebhookLog::where('external_id', $this->data['external_id'])
            ->update(['processed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ErrorLog::create([
            'source' => 'webhook.stok_masuk',
            'payload' => $this->data,
            'error_message' => $exception->getMessage(),
            'resolved' => false,
            'created_at' => now(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessUpdateEtalaseWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        if (isset($this->data['status']) && $this->data['status'] === 'gagal') {
            ErrorLog::create([
                'source' => 'webhook.update_etalase',
                'payload' => $this->data,
                'error_message' => $this->data['pesan'] ?? 'update etalase gagal',
                'resolved' => false,
                'created_at' => now(),
            ]);
        }

        WebhookLog::where('external_id', $this->data['external_id'])
            ->update(['processed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ErrorLog::create([
            'source' => 'webhook.update_etalase',
            'payload' => $this->data,
            'error_message' => $exception->getMessage(),
            'resolved' => false,
            'created_at' => now(),
        ]);
    }
}

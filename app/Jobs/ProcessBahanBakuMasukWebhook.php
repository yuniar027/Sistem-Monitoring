<?php

namespace App\Jobs;

use App\Models\BahanBaku;
use App\Models\ErrorLog;
use App\Models\WebhookLog;
use App\Services\BahanBakuMasukService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBahanBakuMasukWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(BahanBakuMasukService $service): void
    {
        $bahanBaku = BahanBaku::where('kode_bahan', $this->data['kode_bahan'])->first();

        if (! $bahanBaku) {
            ErrorLog::create([
                'source' => 'webhook.bahan_baku_masuk',
                'payload' => $this->data,
                'error_message' => 'kode_bahan tidak ditemukan: ' . $this->data['kode_bahan'],
                'resolved' => false,
                'created_at' => now(),
            ]);

            WebhookLog::where('external_id', $this->data['external_id'])
                ->update(['processed_at' => now()]);

            return;
        }

        $kuantitas = (int) $this->data['kuantitas'];
        $hargaSatuan = (float) $this->data['harga_satuan'];
        $biayaKirim = (float) ($this->data['biaya_kirim'] ?? 0);

        $service->catatBahanBakuMasuk([
            'bahan_baku_id' => $bahanBaku->id,
            'tanggal' => $this->data['tanggal'],
            'vendor' => $this->data['vendor'],
            'kuantitas' => $kuantitas,
            'harga_satuan' => $hargaSatuan,
            'biaya_kirim' => $biayaKirim,
            'total_nominal' => ($kuantitas * $hargaSatuan) + $biayaKirim,
        ]);

        WebhookLog::where('external_id', $this->data['external_id'])
            ->update(['processed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        ErrorLog::create([
            'source' => 'webhook.bahan_baku_masuk',
            'payload' => $this->data,
            'error_message' => $exception->getMessage(),
            'resolved' => false,
            'created_at' => now(),
        ]);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiBahanBakuService
{
    public function tersedia(): bool
    {
        return ! empty(config('services.anthropic.api_key'));
    }

    /**
     * Tanya AI untuk pilih kandidat paling cocok, atau bilang "tidak yakin".
     * $daftarBahanBaku: array of ['kode_bahan' => ..., 'nama_bahan' => ...] (semua 488, atau subset relevan)
     * Return: ['kode_bahan' => string|null, 'alasan' => string, 'yakin' => bool] atau null kalau AI tidak tersedia/gagal.
     */
    public function carikanKecocokan(string $namaItem, array $daftarBahanBaku): ?array
    {
        if (! $this->tersedia()) {
            return null;
        }

        $daftarText = collect($daftarBahanBaku)
            ->map(fn ($bb) => "{$bb['kode_bahan']}: {$bb['nama_bahan']}")
            ->implode("\n");

        $prompt = <<<PROMPT
Kamu membantu mencocokkan nama barang dari invoice pabrik (bahasa informal/singkatan)
ke kode bahan baku resmi di katalog. Nama invoice sering pakai singkatan berbeda dari
katalog resmi (contoh: "ST CLN POP ABU" mungkin cocok ke bahan dengan nama berbeda
tapi maksud sama).

Nama item dari invoice pabrik: "{$namaItem}"

Daftar kandidat bahan baku (kode: nama):
{$daftarText}

Tugas kamu: cari SATU kode_bahan yang paling mungkin cocok, atau bilang tidak yakin
kalau memang tidak ada yang jelas cocok. JANGAN memaksakan jawaban kalau ragu.

Jawab HANYA dalam format JSON persis seperti ini, tanpa teks lain:
{"kode_bahan": "KODE atau null", "alasan": "penjelasan singkat", "yakin": true atau false}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 300,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('AiBahanBakuService: API call gagal', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $teksJawaban = $response->json('content.0.text');
            $parsed = json_decode(trim($teksJawaban), true);

            if (! is_array($parsed) || ! array_key_exists('kode_bahan', $parsed)) {
                Log::warning('AiBahanBakuService: respons tidak sesuai format', ['raw' => $teksJawaban]);

                return null;
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('AiBahanBakuService: exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}

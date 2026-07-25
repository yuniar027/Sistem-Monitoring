<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlokasiAiWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string'],
            'sku' => ['required', 'string'],
            'jumlah_paket' => ['required', 'integer', 'min:1'],
            'tanggal_dibuat' => ['nullable', 'date'],
            'alokasi' => ['required', 'array', 'min:1'],
            'alokasi.*.channel' => ['required', 'string', 'in:shopee,tiktok'],
            'alokasi.*.nama_toko' => ['required', 'string'],
            'alokasi.*.kuantitas_dialokasikan' => ['required', 'integer', 'min:1'],
            'alokasi.*.tanggal_alokasi' => ['nullable', 'date'],
        ];
    }
}

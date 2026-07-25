<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BahanBakuMasukWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string'],
            'kode_bahan' => ['required', 'string', 'exists:bahan_baku,kode_bahan'],
            'tanggal' => ['required', 'date'],
            'vendor' => ['required', 'string'],
            'kuantitas' => ['required', 'integer', 'min:1'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'biaya_kirim' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

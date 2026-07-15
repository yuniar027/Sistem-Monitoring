<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StokMasukWebhookRequest extends FormRequest
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
            'tanggal' => ['required', 'date'],
            'vendor' => ['required', 'string'],
            'kuantitas' => ['required', 'integer', 'min:1'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'biaya_kirim' => ['required', 'numeric', 'min:0'],
            'total_nominal' => ['required', 'numeric', 'min:0'],
        ];
    }
}

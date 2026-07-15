<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenjualanWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string'],
            'channel' => ['required', 'string', 'in:shopee,tiktok'],
            'no_pesanan' => ['required', 'string'],
            'no_resi' => ['nullable', 'string'],
            'sku' => ['required', 'string'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date'],
            'status_order' => ['required', 'string'],
        ];
    }
}

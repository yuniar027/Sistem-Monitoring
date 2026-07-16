<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEtalaseWebhookRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:shopee,tiktok'],
            'status' => ['required', 'string', 'in:berhasil,gagal'],
            'pesan' => ['nullable', 'string'],
        ];
    }
}

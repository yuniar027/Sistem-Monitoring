<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PetakanBahanBakuWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_item' => ['required', 'string'],
        ];
    }
}

<?php

namespace App\Filament\Resources\ResepPaketItemResource\Pages;

use App\Filament\Resources\ResepPaketItemResource;
use App\Models\ResepPaketItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateResepPaketItem extends CreateRecord
{
    protected static string $resource = ResepPaketItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $sku = $data['sku'];
        $items = $data['items'] ?? [];

        return DB::transaction(function () use ($sku, $items) {
            $created = [];

            foreach ($items as $item) {
                if (! empty($item['bahan_baku_id'])) {
                    $created[] = ResepPaketItem::create([
                        'sku' => $sku,
                        'bahan_baku_id' => $item['bahan_baku_id'],
                        'kuantitas_per_paket' => $item['kuantitas_per_paket'] ?? 1,
                    ]);
                }
            }

            return $created[0];
        });
    }
}
<?php

namespace App\Services\Shopee;

use RuntimeException;

class ShopeeService
{
    public function getOrders(): array
    {
        $mode = config('services.shopee.mode', 'mock');

        if ($mode === 'mock') {
            return app(MockShopeeClient::class)->getOrders();
        }

        if ($mode === 'real') {
            throw new RuntimeException(
                'Shopee real API belum dikonfigurasi.'
            );
        }

        throw new RuntimeException(
            "Mode Shopee tidak dikenal: {$mode}"
        );
    }
}

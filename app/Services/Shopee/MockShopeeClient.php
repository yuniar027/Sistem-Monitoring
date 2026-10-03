<?php

namespace App\Services\Shopee;

class MockShopeeClient
{
    public function getOrders(): array
    {
        return [
            [
                'order_sn' => '250829TEST001',
                'status' => 'READY_TO_SHIP',
                'buyer_username' => 'buyer_demo',
                'total_amount' => 125000,
                'currency' => 'IDR',
                'created_at' => now()->subHours(2)->toDateTimeString(),
            ],
            [
                'order_sn' => '250829TEST002',
                'status' => 'PROCESSED',
                'buyer_username' => 'customer_demo',
                'total_amount' => 87500,
                'currency' => 'IDR',
                'created_at' => now()->subHours(5)->toDateTimeString(),
            ],
            [
                'order_sn' => '250829TEST003',
                'status' => 'COMPLETED',
                'buyer_username' => 'shopper_demo',
                'total_amount' => 210000,
                'currency' => 'IDR',
                'created_at' => now()->subDay()->toDateTimeString(),
            ],
        ];
    }
}

<div class="overflow-x-auto">
    <div class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ count($items) }} barang ditemukan
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-3 py-2 text-left">Kode</th>
                <th class="px-3 py-2 text-left">Barang</th>
                <th class="px-3 py-2 text-right">Qty</th>
                <th class="px-3 py-2 text-left">Satuan</th>
                <th class="px-3 py-2 text-right">Harga</th>
                <th class="px-3 py-2 text-right">Diskon</th>
                <th class="px-3 py-2 text-right">Subtotal</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($items as $item)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2 font-mono">
                        {{ $item['kode'] ?? '-' }}
                    </td>

                    <td class="px-3 py-2">
                        {{ $item['item'] ?? '-' }}
                    </td>

                    <td class="px-3 py-2 text-right">
                        {{ number_format((float) ($item['qty'] ?? 0), 2, ',', '.') }}
                    </td>

                    <td class="px-3 py-2">
                        {{ $item['unit'] ?? '-' }}
                    </td>

                    <td class="px-3 py-2 text-right">
                        Rp {{ number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.') }}
                    </td>

                    <td class="px-3 py-2 text-right">
                        Rp {{ number_format((float) ($item['discount'] ?? 0), 0, ',', '.') }}
                    </td>

                    <td class="px-3 py-2 text-right font-medium">
                        Rp {{ number_format((float) ($item['amount'] ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
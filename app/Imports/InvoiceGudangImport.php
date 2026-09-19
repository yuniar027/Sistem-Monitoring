<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InvoiceGudangImport implements ToCollection, WithHeadingRow
{
    /**
     * Data hasil pembacaan Excel.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $item = trim((string) ($row['item'] ?? ''));

            // Abaikan baris kosong.
            if ($item === '') {
                continue;
            }

            // Abaikan baris TOTAL.
            if (strtoupper($item) === 'TOTAL') {
                continue;
            }

            $qty = $this->parseNumber($row['qty'] ?? null);
            $unitPrice = $this->parseNumber($row['unit_price'] ?? null);
            $amount = $this->parseNumber($row['amount'] ?? null);

            // Abaikan baris yang bukan detail barang.
            if ($qty === null && $unitPrice === null && $amount === null) {
                continue;
            }

            $this->items[] = [
                'item' => $item,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];
        }
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Hilangkan simbol mata uang dan spasi.
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        if ($value === '') {
            return null;
        }

        // Format Indonesia: 42.000,50
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // Format internasional: 42,000.50
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            // Untuk harga invoice seperti 42,000 → 42000.
            $value = str_replace(',', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            // Untuk format seperti 1.250.000 → 1250000.
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
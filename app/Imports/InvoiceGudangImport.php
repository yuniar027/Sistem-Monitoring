<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class InvoiceGudangImport implements ToCollection
{
    public array $items = [];

    public function collection(Collection $rows): void
    {
        $headerRowIndex = $this->findHeaderRow($rows);

        if ($headerRowIndex === null) {
            return;
        }

        foreach ($rows->slice($headerRowIndex + 1) as $row) {
            $values = array_values($row->toArray());

            $kode = trim((string) ($values[1] ?? ''));
            $item = trim((string) ($values[2] ?? ''));

            if ($item === '' && $kode === '') {
                continue;
            }

            if (strtoupper($item) === 'TOTAL') {
                continue;
            }

            $qty = $this->parseNumber($values[3] ?? null);
            $satuan = trim((string) ($values[5] ?? ''));
            $unitPrice = $this->parseNumber($values[7] ?? null);
            $discount = $this->parseNumber($values[9] ?? null);
            $amount = $this->parseNumber($values[10] ?? null);

            if ($qty === null && $unitPrice === null && $amount === null) {
                continue;
            }

            $this->items[] = [
                'kode' => $kode !== '' ? $kode : null,
                'item' => $item,
                'qty' => $qty,
                'unit' => $satuan !== '' ? $satuan : null,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'amount' => $amount,
            ];
        }
    }

    public function getItems(): array
    {
        return $this->items;
    }

    private function findHeaderRow(Collection $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $values = array_map(
                fn ($value) => strtoupper(trim((string) $value)),
                array_values($row->toArray())
            );

            $hasNo = in_array('NO', $values, true);
            $hasKode = in_array('KODE', $values, true);
            $hasBarang = in_array('BARANG', $values, true);
            $hasJumlah = in_array('JUMLAH', $values, true);
            $hasHarga = in_array('HARGA', $values, true);
            $hasSubtotal = in_array('SUBTOTAL', $values, true);

            if (
                $hasNo &&
                $hasKode &&
                $hasBarang &&
                $hasJumlah &&
                $hasHarga &&
                $hasSubtotal
            ) {
                return $index;
            }
        }

        return null;
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

        $value = preg_replace('/[^\d,.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        /*
         * Format Indonesia:
         * 74.000      -> 74000
         * 1.508.000   -> 1508000
         * 2,5         -> 2.5
         */
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);

            if (
                count($parts) === 2 &&
                strlen($parts[1]) <= 2
            ) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, '.')) {
            $parts = explode('.', $value);

            if (
                count($parts) > 2 ||
                (count($parts) === 2 && strlen($parts[1]) === 3)
            ) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
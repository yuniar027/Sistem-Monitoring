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

            /*
             * Struktur invoice Origami:
             *
             * 0  = NO
             * 1  = KODE
             * 2  = BARANG
             * 3  = JUMLAH
             * 4  = kolom kosong
             * 5  = SATUAN
             * 6  = kolom kosong
             * 7  = HARGA
             * 8  = kolom kosong
             * 9  = DISKON
             * 10 = SUBTOTAL
             * 11 = kolom kosong
             */

            $kode = trim((string) ($values[1] ?? ''));
            $item = trim((string) ($values[2] ?? ''));

            /*
             * Lewati baris kosong.
             */
            if ($item === '' && $kode === '') {
                continue;
            }

            /*
             * Lewati baris TOTAL.
             */
            if (strtoupper($item) === 'TOTAL') {
                continue;
            }

            $qty = $this->parseNumber($values[3] ?? null);

            $satuan = trim((string) ($values[5] ?? ''));

            $unitPrice = $this->parseNumber($values[7] ?? null);

            $discount = $this->parseNumber($values[9] ?? null);

            $amount = $this->parseNumber($values[10] ?? null);

            /*
             * Kalau baris tidak memiliki data angka sama sekali,
             * anggap bukan detail barang.
             */
            if (
                $qty === null &&
                $unitPrice === null &&
                $amount === null
            ) {
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

    /**
     * Cari baris header invoice.
     */
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

    /**
     * Parse angka dari Excel/invoice.
     *
     * Contoh:
     * 74.000      -> 74000
     * 1.508.000   -> 1508000
     * 2,5         -> 2.5
     * 74000       -> 74000
     */
    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        /*
         * Excel sering memberikan angka sebagai integer/float
         * sehingga langsung kembalikan sebagai float.
         */
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        /*
         * Buang simbol mata uang, spasi, dan karakter lain.
         * Tetap pertahankan angka, koma, titik, dan minus.
         */
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        /*
         * Format Indonesia dengan koma dan titik.
         *
         * 1.508.000,50 -> 1508000.50
         * 1,508,000.50 -> 1508000.50
         */
        if (
            str_contains($value, ',') &&
            str_contains($value, '.')
        ) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                /*
                 * Contoh:
                 * 1.508.000,50
                 */
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                /*
                 * Contoh:
                 * 1,508,000.50
                 */
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);

            if (
                count($parts) === 2 &&
                strlen($parts[1]) <= 2
            ) {
                /*
                 * Contoh:
                 * 2,5 -> 2.5
                 */
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                /*
                 * Contoh:
                 * 1,508,000 -> 1508000
                 */
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, '.')) {
            $parts = explode('.', $value);

            if (
                count($parts) > 2 ||
                (
                    count($parts) === 2 &&
                    strlen($parts[1]) === 3
                )
            ) {
                /*
                 * Contoh:
                 * 74.000 -> 74000
                 * 1.508.000 -> 1508000
                 */
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value)
            ? (float) $value
            : null;
    }
}
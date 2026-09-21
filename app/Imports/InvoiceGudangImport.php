<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class InvoiceGudangImport implements ToCollection
{
    public array $items = [];

    public function collection(Collection $rows): void
    {
        $headerMap = $this->findHeaderMap($rows);

        if ($headerMap === null) {
            return;
        }

        foreach ($rows->slice($headerMap['row_index'] + 1) as $row) {
            $values = array_values($row->toArray());

            $kode = trim((string) $this->valueFromColumn(
                $values,
                $headerMap['columns']['kode'] ?? null
            ));

            $item = trim((string) $this->valueFromColumn(
                $values,
                $headerMap['columns']['barang'] ?? null
            ));

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

            /*
             * Ambil nilai berdasarkan posisi header yang ditemukan,
             * bukan berdasarkan index kolom yang tetap.
             *
             * Ini menangani invoice yang memiliki kolom kosong
             * di antara JUMLAH, SATUAN, HARGA, DISKON, dan SUBTOTAL.
             */
            $qty = $this->parseNumber(
                $this->valueFromColumn(
                    $values,
                    $headerMap['columns']['jumlah'] ?? null
                )
            );

            $satuan = trim((string) $this->valueFromColumn(
                $values,
                $headerMap['columns']['satuan'] ?? null
            ));

            $unitPrice = $this->parseNumber(
                $this->valueFromColumn(
                    $values,
                    $headerMap['columns']['harga'] ?? null
                )
            );

            $discount = $this->parseNumber(
                $this->valueFromColumn(
                    $values,
                    $headerMap['columns']['diskon'] ?? null
                )
            );

            $amount = $this->parseNumber(
                $this->valueFromColumn(
                    $values,
                    $headerMap['columns']['subtotal'] ?? null
                )
            );

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
     * Cari baris header dan posisi setiap kolom berdasarkan nama header.
     *
     * Hasil:
     * [
     *     'row_index' => 7,
     *     'columns' => [
     *         'kode' => 1,
     *         'barang' => 2,
     *         'jumlah' => 4,
     *         'satuan' => 5,
     *         'harga' => 7,
     *         'diskon' => 9,
     *         'subtotal' => 10,
     *     ],
     * ]
     */
    private function findHeaderMap(Collection $rows): ?array
    {
        foreach ($rows as $index => $row) {
            $values = array_values($row->toArray());
            $columns = [];

            foreach ($values as $columnIndex => $value) {
                $header = $this->normalizeHeader($value);

                if ($header === '') {
                    continue;
                }

                $columnKey = match ($header) {
                    'NO' => 'no',
                    'KODE' => 'kode',
                    'BARANG' => 'barang',
                    'JUMLAH' => 'jumlah',
                    'SATUAN' => 'satuan',
                    'HARGA' => 'harga',
                    'DISKON' => 'diskon',
                    'SUBTOTAL' => 'subtotal',
                    default => null,
                };

                if ($columnKey !== null) {
                    $columns[$columnKey] = $columnIndex;
                }
            }

            $requiredColumns = [
                'kode',
                'barang',
                'jumlah',
                'harga',
                'subtotal',
            ];

            $hasRequiredColumns = collect($requiredColumns)
                ->every(fn (string $column) => array_key_exists($column, $columns));

            if ($hasRequiredColumns) {
                return [
                    'row_index' => $index,
                    'columns' => $columns,
                ];
            }
        }

        return null;
    }

    /**
     * Normalisasi header agar tahan terhadap spasi tambahan
     * dan perbedaan huruf besar/kecil.
     */
    private function normalizeHeader(mixed $value): string
    {
        return strtoupper(
            preg_replace('/\s+/', ' ', trim((string) $value))
        );
    }

    /**
     * Ambil nilai dari posisi kolom yang ditemukan.
     */
    private function valueFromColumn(array $values, ?int $columnIndex): mixed
    {
        if ($columnIndex === null) {
            return null;
        }

        return $values[$columnIndex] ?? null;
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

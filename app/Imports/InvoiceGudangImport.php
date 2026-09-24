<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class InvoiceGudangImport implements ToCollection
{
    public array $items = [];

    private ?string $nomorInvoice = null;

    private ?string $tanggal = null;

    private ?string $supplier = null;

    public function collection(Collection $rows): void
    {
        $this->extractHeaderInfo($rows);

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

            if ($item === '' && $kode === '') {
                continue;
            }

            if (strtoupper($item) === 'TOTAL') {
                continue;
            }

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

    public function getNomorInvoice(): ?string
    {
        return $this->nomorInvoice;
    }

    public function getTanggal(): ?string
    {
        return $this->tanggal;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    /**
     * Blok info invoice ada di satu sel (biasanya A1) berisi beberapa
     * baris teks, contoh:
     *
     *   018/09/BS/2026
     *   TGL. 19-09-2026
     *   JATUH TEMPO 26-09-2026
     *   SJ-2609-000497
     *   BUDDI SANTOSO
     *   ...
     *
     * Baris pertama = nomor invoice, baris "TGL." = tanggal, dan baris
     * pertama SETELAH baris-baris yang dikenali (nomor/TGL/JATUH TEMPO/SJ)
     * dianggap nama supplier. Kalau sel ini tidak ditemukan atau polanya
     * tidak cocok, semua tetap null -- pemanggil (CreatePembelianGudang)
     * yang menyediakan nilai fallback.
     */
    private function extractHeaderInfo(Collection $rows): void
    {
        $teksHeader = null;

        foreach ($rows->take(5) as $row) {
            foreach ($row->toArray() as $value) {
                if (is_string($value) && str_contains($value, "\n") && trim($value) !== '') {
                    $teksHeader = $value;
                    break 2;
                }
            }
        }

        if ($teksHeader === null) {
            return;
        }

        $baris = collect(preg_split('/\r\n|\r|\n/', $teksHeader))
            ->map(fn ($b) => trim((string) $b))
            ->filter(fn ($b) => $b !== '')
            ->values();

        if ($baris->isEmpty()) {
            return;
        }

        $this->nomorInvoice = $baris->first();

        $sisaBaris = $baris->slice(1)->values();
        $barisTerpakai = [0];

        foreach ($sisaBaris as $i => $b) {
            if (preg_match('/^TGL\.?\s*(\d{1,2}[\-\/]\d{1,2}[\-\/]\d{2,4})/i', $b, $cocok)) {
                $this->tanggal = $this->parseTanggalIndo($cocok[1]);
                $barisTerpakai[] = $i;
            } elseif (preg_match('/^JATUH\s*TEMPO/i', $b)) {
                $barisTerpakai[] = $i;
            } elseif (preg_match('/^SJ[\-\s]/i', $b)) {
                $barisTerpakai[] = $i;
            }
        }

        foreach ($sisaBaris as $i => $b) {
            if (! in_array($i, $barisTerpakai, true)) {
                $this->supplier = $b;
                break;
            }
        }
    }

    /**
     * "19-09-2026" atau "19/09/2026" -> "2026-09-19".
     */
    private function parseTanggalIndo(string $tanggal): ?string
    {
        $tanggal = str_replace('/', '-', $tanggal);
        $bagian = explode('-', $tanggal);

        if (count($bagian) !== 3) {
            return null;
        }

        [$hari, $bulan, $tahun] = $bagian;

        if (strlen($tahun) === 2) {
            $tahun = '20' . $tahun;
        }

        if (! checkdate((int) $bulan, (int) $hari, (int) $tahun)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
    }

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

    private function normalizeHeader(mixed $value): string
    {
        return strtoupper(
            preg_replace('/\s+/', ' ', trim((string) $value))
        );
    }

    private function valueFromColumn(array $values, ?int $columnIndex): mixed
    {
        if ($columnIndex === null) {
            return null;
        }

        return $values[$columnIndex] ?? null;
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
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
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
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value)
            ? (float) $value
            : null;
    }
}
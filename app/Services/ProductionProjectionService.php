<?php

namespace App\Services;

use App\Models\ProductionEvent;
use App\Models\StokHarianGudang;
use App\Models\StokVariasiHarian;
use App\Models\StokAlokasiKhususHarian;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class ProductionProjectionService
{
    public function rawConsumptionByDate(Carbon|string $tanggal): Collection
    {
        $date = $this->normalizeDate($tanggal);

        return ProductionEvent::query()
            ->whereDate('tanggal', $date)
            ->select('tanggal', 'barang_gudang_id')
            ->selectRaw('SUM(source_quantity) as total_source_quantity')
            ->groupBy('tanggal', 'barang_gudang_id')
            ->get()
            ->map(fn (ProductionEvent $event): array => [
                'tanggal' => $event->tanggal->toDateString(),
                'barang_gudang_id' => (int) $event->barang_gudang_id,
                'total_source_quantity' => $this->normalizeDecimal($event->total_source_quantity),
            ]);
    }

    public function rawConsumption(Carbon|string $tanggal, int $barangGudangId): string
    {
        $date = $this->normalizeDate($tanggal);

        $total = ProductionEvent::query()
            ->whereDate('tanggal', $date)
            ->where('barang_gudang_id', $barangGudangId)
            ->sum('source_quantity');

        return $this->normalizeDecimal($total);
    }

    public function variationOutputByDate(Carbon|string $tanggal): Collection
    {
        $date = $this->normalizeDate($tanggal);

        return ProductionEvent::query()
            ->join('production_process_targets', 'production_process_targets.id', '=', 'production_events.production_process_target_id')
            ->whereDate('production_events.tanggal', $date)
            ->select('production_events.tanggal', 'production_process_targets.variasi_gudang_id')
            ->selectRaw('SUM(production_events.output_quantity) as total_output_quantity')
            ->groupBy('production_events.tanggal', 'production_process_targets.variasi_gudang_id')
            ->get()
            ->map(fn (object $row): array => [
                'tanggal' => Carbon::parse($row->tanggal)->toDateString(),
                'variasi_gudang_id' => (int) $row->variasi_gudang_id,
                'total_output_quantity' => $this->normalizeDecimal($row->total_output_quantity),
            ]);
    }

    public function variationOutput(Carbon|string $tanggal, int $variasiGudangId): string
    {
        $date = $this->normalizeDate($tanggal);

        $total = ProductionEvent::query()
            ->join('production_process_targets', 'production_process_targets.id', '=', 'production_events.production_process_target_id')
            ->whereDate('production_events.tanggal', $date)
            ->where('production_process_targets.variasi_gudang_id', $variasiGudangId)
            ->sum('production_events.output_quantity');

        return $this->normalizeDecimal($total);
    }

    public function rawStockProjection(StokHarianGudang $snapshot): string
    {
        $stokSiap = $this->addDecimal($snapshot->rak, $snapshot->input);
        $historicalAllocation = StokAlokasiKhususHarian::query()
            ->where('barang_gudang_id', $snapshot->barang_gudang_id)
            ->whereDate('tanggal', $snapshot->tanggal)
            ->sum('kuantitas');
        $productionConsumption = $this->rawConsumption($snapshot->tanggal, (int) $snapshot->barang_gudang_id);

        return $this->subtractDecimal(
            $this->subtractDecimal($stokSiap, $historicalAllocation),
            $productionConsumption,
        );
    }

    public function variationProjection(
        StokVariasiHarian $snapshot,
        int|float|string $nonProductionInput = '0.00',
    ): array {
        $productionOutput = $this->variationOutput($snapshot->tanggal, (int) $snapshot->variasi_gudang_id);
        $stock = $this->addDecimal($snapshot->stok_awal, $productionOutput);
        $stock = $this->addDecimal($stock, $nonProductionInput);
        $sisa = $this->subtractDecimal($stock, $snapshot->out);

        return [
            'stok_awal' => $this->normalizeDecimal($snapshot->stok_awal),
            'production_output' => $productionOutput,
            'non_production_input' => $this->normalizeDecimal($nonProductionInput),
            'out' => $this->normalizeDecimal($snapshot->out),
            'stok' => $stock,
            'sisa' => $sisa,
        ];
    }

    private function normalizeDate(Carbon|string $tanggal): string
    {
        try {
            return ($tanggal instanceof Carbon ? $tanggal->copy() : Carbon::parse($tanggal))
                ->toDateString();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid projection date.', 0, $exception);
        }
    }

    private function normalizeDecimal(int|float|string|null $value): string
    {
        if (! function_exists('bcadd') || ! function_exists('bcsub')) {
            throw new \LogicException('BCMath extension is required for production projection arithmetic.');
        }

        $value = $value === null ? '0' : trim((string) $value);

        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Projection quantity must be a valid decimal.');
        }

        return bcadd($value, '0', 2);
    }

    private function addDecimal(int|float|string|null $left, int|float|string|null $right): string
    {
        return bcadd($this->normalizeDecimal($left), $this->normalizeDecimal($right), 2);
    }

    private function subtractDecimal(int|float|string|null $left, int|float|string|null $right): string
    {
        return bcsub($this->normalizeDecimal($left), $this->normalizeDecimal($right), 2);
    }
}

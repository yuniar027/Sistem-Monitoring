<?php

namespace App\Services;

use App\Models\ProductionEvent;
use App\Models\ProductionProcessTarget;
use App\Models\StokBarangGudang;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ProductionService
{
    public function create(
        Carbon|string $tanggal,
        int $barangGudangId,
        int $productionProcessTargetId,
        int|float|string $sourceQuantity,
    ): ProductionEvent {
        return DB::transaction(function () use (
            $tanggal,
            $barangGudangId,
            $productionProcessTargetId,
            $sourceQuantity,
        ): ProductionEvent {
            $date = $this->normalizeDate($tanggal);
            $this->findSourceProduct($barangGudangId);
            $target = $this->findProcessTarget($productionProcessTargetId);
            $source = $this->normalizeQuantity($sourceQuantity, 'source quantity');
            $multiplier = $this->normalizeQuantity($target->multiplier, 'multiplier');

            return ProductionEvent::create([
                'tanggal' => $date,
                'barang_gudang_id' => $barangGudangId,
                'production_process_target_id' => $target->id,
                'source_quantity' => $source,
                'multiplier_snapshot' => $multiplier,
                'output_quantity' => $this->multiplyDecimal($source, $multiplier),
            ]);
        });
    }

    public function update(
        ProductionEvent $event,
        Carbon|string $tanggal,
        int $barangGudangId,
        int $productionProcessTargetId,
        int|float|string $sourceQuantity,
    ): ProductionEvent {
        return DB::transaction(function () use (
            $event,
            $tanggal,
            $barangGudangId,
            $productionProcessTargetId,
            $sourceQuantity,
        ): ProductionEvent {
            if (! $event->exists) {
                throw new InvalidArgumentException('Production event does not exist.');
            }

            $date = $this->normalizeDate($tanggal);
            $this->findSourceProduct($barangGudangId);
            $target = $this->findProcessTarget($productionProcessTargetId);
            $source = $this->normalizeQuantity($sourceQuantity, 'source quantity');
            $multiplier = $this->normalizeQuantity($target->multiplier, 'multiplier');

            $event->update([
                'tanggal' => $date,
                'barang_gudang_id' => $barangGudangId,
                'production_process_target_id' => $target->id,
                'source_quantity' => $source,
                'multiplier_snapshot' => $multiplier,
                'output_quantity' => $this->multiplyDecimal($source, $multiplier),
            ]);

            return $event->refresh();
        });
    }

    public function delete(ProductionEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            if (! $event->exists) {
                throw new InvalidArgumentException('Production event does not exist.');
            }

            $event->delete();
        });
    }

    private function normalizeDate(Carbon|string $tanggal): string
    {
        try {
            return ($tanggal instanceof Carbon ? $tanggal->copy() : Carbon::parse($tanggal))
                ->toDateString();
        } catch (InvalidFormatException|Throwable $exception) {
            throw new InvalidArgumentException('Invalid production event date.', 0, $exception);
        }
    }

    private function findSourceProduct(int $barangGudangId): StokBarangGudang
    {
        $source = StokBarangGudang::find($barangGudangId);

        if (! $source) {
            throw new InvalidArgumentException("Source product {$barangGudangId} was not found.");
        }

        return $source;
    }

    private function findProcessTarget(int $productionProcessTargetId): ProductionProcessTarget
    {
        $target = ProductionProcessTarget::query()
            ->with(['productionProcess', 'variasiGudang'])
            ->find($productionProcessTargetId);

        if (! $target) {
            throw new InvalidArgumentException("Production process target {$productionProcessTargetId} was not found.");
        }

        if (! $target->productionProcess) {
            throw new InvalidArgumentException("Production process for target {$productionProcessTargetId} was not found.");
        }

        if (! $target->variasiGudang) {
            throw new InvalidArgumentException("Variation for target {$productionProcessTargetId} was not found.");
        }

        return $target;
    }

    private function normalizeQuantity(int|float|string $quantity, string $label): string
    {
        if (is_float($quantity) && ! is_finite($quantity)) {
            throw new InvalidArgumentException("{$label} must be a finite number.");
        }

        $value = trim((string) $quantity);

        if (! preg_match('/^\+?(?:\d+)(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a valid decimal with at most two fraction digits.");
        }

        $value = ltrim($value, '+');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $normalized = ltrim($whole, '0') ?: '0';
        $normalized .= '.' . str_pad($fraction, 2, '0');

        if ((int) str_replace('.', '', $normalized) <= 0) {
            throw new InvalidArgumentException("{$label} must be greater than zero.");
        }

        return $normalized;
    }

    private function multiplyDecimal(string $left, string $right): string
    {
        if (! function_exists('bcmul') || ! function_exists('bcadd')) {
            throw new \LogicException('BCMath extension is required for production decimal arithmetic.');
        }

        $product = bcmul($left, $right, 4);
        [$whole, $fraction] = array_pad(explode('.', $product, 2), 2, '');
        $fraction = str_pad($fraction, 4, '0');
        $cents = substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents = bcadd($cents, '1', 0);
        }

        if (strlen($cents) > 2) {
            $whole = bcadd($whole, '1', 0);
            $cents = '00';
        }

        return $whole . '.' . str_pad($cents, 2, '0', STR_PAD_LEFT);
    }
}

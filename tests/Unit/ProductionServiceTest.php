<?php

namespace Tests\Unit;

use App\Models\ProductionEvent;
use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use App\Services\ProductionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionServiceTest extends TestCase
{
    private ProductionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        $this->service = new ProductionService();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'production_events',
            'production_process_targets',
            'stok_variasi_harian',
            'stok_harian_gudang',
            'stok_alokasi_khusus_harian',
            'stok_variasi_gudang',
            'production_processes',
            'stok_barang_gudang',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    public function test_create_calculates_integer_output(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');

        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->assertSame('2026-09-12', $event->tanggal->toDateString());
        $this->assertSame('5.00', $event->source_quantity);
        $this->assertSame('4.00', $event->multiplier_snapshot);
        $this->assertSame('20.00', $event->output_quantity);
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_create_calculates_decimal_output(): void
    {
        [$source, $target] = $this->makeProductionData('6.00');

        $event = $this->service->create('2026-09-12', $source->id, $target->id, '2.5');

        $this->assertSame('2.50', $event->source_quantity);
        $this->assertSame('15.00', $event->output_quantity);
    }

    public function test_create_rounds_decimal_product_explicitly_to_two_places(): void
    {
        [$source, $target] = $this->makeProductionData('1.23');

        $event = $this->service->create('2026-09-12', $source->id, $target->id, '1.23');

        $this->assertSame('1.51', $event->output_quantity);
    }

    public function test_create_rejects_non_positive_source_quantity(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source quantity must be greater than zero');

        $this->service->create('2026-09-12', $source->id, $target->id, 0);
    }

    public function test_create_rejects_non_positive_multiplier(): void
    {
        [$source, $target] = $this->makeProductionData('0.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiplier must be greater than zero');

        $this->service->create('2026-09-12', $source->id, $target->id, 5);
    }

    public function test_create_rejects_missing_source_product(): void
    {
        [, $target] = $this->makeProductionData('4.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source product 999 was not found');

        $this->service->create('2026-09-12', 999, $target->id, 5);
    }

    public function test_create_rejects_missing_process_target(): void
    {
        $source = StokBarangGudang::create(['nama_barang' => 'Source']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production process target 999 was not found');

        $this->service->create('2026-09-12', $source->id, 999, 5);
    }

    public function test_create_rejects_invalid_date(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid production event date');

        $this->service->create('not-a-date', $source->id, $target->id, 5);
    }

    public function test_update_changes_the_same_event_and_recalculates_output(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);
        $eventId = $event->id;

        $updated = $this->service->update($event, '2026-09-13', $source->id, $target->id, 7);

        $this->assertSame($eventId, $updated->id);
        $this->assertSame('2026-09-13', $updated->tanggal->toDateString());
        $this->assertSame('7.00', $updated->source_quantity);
        $this->assertSame('28.00', $updated->output_quantity);
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_update_can_replace_process_target(): void
    {
        [$source, $target] = $this->makeProductionData('4.00', 'K33', '33 PCS');
        [, $newTarget] = $this->makeProductionData('6.00', 'K27', '27 PCS', $source);
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $updated = $this->service->update($event, '2026-09-12', $source->id, $newTarget->id, 5);

        $this->assertSame($newTarget->id, $updated->production_process_target_id);
        $this->assertSame('6.00', $updated->multiplier_snapshot);
        $this->assertSame('30.00', $updated->output_quantity);
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_update_can_replace_date(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $updated = $this->service->update($event, '2026-09-20', $source->id, $target->id, 5);

        $this->assertSame('2026-09-20', $updated->tanggal->toDateString());
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_update_can_replace_source_product(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $newSource = StokBarangGudang::create(['nama_barang' => 'New Source']);
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);
        $eventId = $event->id;

        $updated = $this->service->update($event, '2026-09-12', $newSource->id, $target->id, 5);

        $this->assertSame($eventId, $updated->id);
        $this->assertSame($newSource->id, $updated->barang_gudang_id);
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_update_preserves_event_multiplier_snapshot_after_master_changes(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $target->update(['multiplier' => '6.00']);
        $event->refresh();

        $this->assertSame('4.00', $event->multiplier_snapshot);
        $this->assertSame('20.00', $event->output_quantity);
    }

    public function test_update_rejects_non_positive_source_without_changing_event(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source quantity must be greater than zero');

        try {
            $this->service->update($event, '2026-09-13', $source->id, $target->id, 0);
        } finally {
            $event->refresh();
            $this->assertSame('2026-09-12', $event->tanggal->toDateString());
            $this->assertSame('5.00', $event->source_quantity);
            $this->assertSame('20.00', $event->output_quantity);
        }
    }

    public function test_update_rejects_invalid_date_without_changing_event(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid production event date');

        try {
            $this->service->update($event, 'not-a-date', $source->id, $target->id, 7);
        } finally {
            $event->refresh();
            $this->assertSame('2026-09-12', $event->tanggal->toDateString());
            $this->assertSame('5.00', $event->source_quantity);
            $this->assertSame('20.00', $event->output_quantity);
        }
    }

    public function test_update_rejects_missing_target_without_changing_event(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production process target 999 was not found');

        try {
            $this->service->update($event, '2026-09-13', $source->id, 999, 7);
        } finally {
            $event->refresh();
            $this->assertSame('2026-09-12', $event->tanggal->toDateString());
            $this->assertSame($target->id, $event->production_process_target_id);
            $this->assertSame('5.00', $event->source_quantity);
            $this->assertSame('20.00', $event->output_quantity);
        }
    }

    public function test_create_rejects_missing_process_relation(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        Schema::disableForeignKeyConstraints();
        $target->productionProcess()->delete();
        Schema::enableForeignKeyConstraints();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production process for target');

        $this->service->create('2026-09-12', $source->id, $target->id, 5);
    }

    public function test_create_rejects_missing_variation_relation(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        Schema::disableForeignKeyConstraints();
        $target->variasiGudang()->delete();
        Schema::enableForeignKeyConstraints();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variation for target');

        $this->service->create('2026-09-12', $source->id, $target->id, 5);
    }

    public function test_delete_removes_event(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');
        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->service->delete($event);

        $this->assertDatabaseCount('production_events', 0);
    }

    public function test_k3_set_event_uses_only_the_selected_target(): void
    {
        [$source, $target] = $this->makeProductionData('1.00', 'K3 SET', '3S BTG');
        $process = $target->productionProcess;
        $this->makeTarget($process, $source, '3S PD', '1.00');
        $this->makeTarget($process, $source, '3S PJ', '1.00');

        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);

        $this->assertSame($target->id, $event->production_process_target_id);
        $this->assertSame('5.00', $event->output_quantity);
        $this->assertDatabaseCount('production_events', 1);
    }

    public function test_crud_does_not_write_stock_or_allocation_tables(): void
    {
        [$source, $target] = $this->makeProductionData('4.00');

        $event = $this->service->create('2026-09-12', $source->id, $target->id, 5);
        $this->service->update($event, '2026-09-13', $source->id, $target->id, 6);
        $this->service->delete($event);

        $this->assertDatabaseCount('production_events', 0);
        $this->assertDatabaseCount('stok_alokasi_khusus_harian', 0);
        $this->assertDatabaseCount('stok_harian_gudang', 0);
        $this->assertDatabaseCount('stok_variasi_harian', 0);
    }

    private function makeProductionData(
        string $multiplier,
        string $processCode = 'K33',
        string $variationCode = '33 PCS',
        ?StokBarangGudang $source = null,
    ): array {
        $source ??= StokBarangGudang::create(['nama_barang' => 'Source']);
        $process = ProductionProcess::create(['kode_proses' => $processCode]);
        $target = $this->makeTarget($process, $source, $variationCode, $multiplier);

        return [$source, $target];
    }

    private function makeTarget(
        ProductionProcess $process,
        StokBarangGudang $source,
        string $variationCode,
        string $multiplier,
    ): ProductionProcessTarget {
        $variation = StokVariasiGudang::create([
            'barang_gudang_id' => $source->id,
            'kode_variasi' => $variationCode,
        ]);

        return ProductionProcessTarget::create([
            'production_process_id' => $process->id,
            'variasi_gudang_id' => $variation->id,
            'multiplier' => $multiplier,
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('stok_barang_gudang', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_barang')->nullable()->unique();
            $table->string('nama_barang');
            $table->decimal('stok_aman', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_proses', 50)->unique();
            $table->string('nama_proses', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('stok_variasi_gudang', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barang_gudang_id')->constrained('stok_barang_gudang');
            $table->string('kode_variasi');
            $table->decimal('stok_aman', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['barang_gudang_id', 'kode_variasi']);
        });

        Schema::create('production_process_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_process_id')->constrained('production_processes');
            $table->foreignId('variasi_gudang_id')->constrained('stok_variasi_gudang');
            $table->decimal('multiplier', 10, 2);
            $table->timestamps();
            $table->unique(['production_process_id', 'variasi_gudang_id']);
        });

        Schema::create('production_events', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('barang_gudang_id')->constrained('stok_barang_gudang');
            $table->foreignId('production_process_target_id')->constrained('production_process_targets');
            $table->decimal('source_quantity', 10, 2);
            $table->decimal('multiplier_snapshot', 10, 2);
            $table->decimal('output_quantity', 10, 2);
            $table->timestamps();
        });

        Schema::create('stok_alokasi_khusus_harian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barang_gudang_id')->constrained('stok_barang_gudang');
            $table->date('tanggal');
            $table->string('kode_alokasi');
            $table->decimal('kuantitas', 10, 2);
            $table->timestamps();
        });

        Schema::create('stok_harian_gudang', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barang_gudang_id')->constrained('stok_barang_gudang');
            $table->date('tanggal');
            $table->decimal('rak', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stok_variasi_harian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variasi_gudang_id')->constrained('stok_variasi_gudang');
            $table->date('tanggal');
            $table->decimal('stok_awal', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
            $table->decimal('out', 10, 2)->default(0);
            $table->timestamps();
        });
    }
}

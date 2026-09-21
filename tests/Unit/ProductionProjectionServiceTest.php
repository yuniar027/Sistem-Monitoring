<?php

namespace Tests\Unit;

use App\Models\ProductionEvent;
use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use App\Models\StokVariasiGudang;
use App\Models\StokVariasiHarian;
use App\Services\ProductionProjectionService;
use App\Services\ProductionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionProjectionServiceTest extends TestCase
{
    private ProductionService $productionService;

    private ProductionProjectionService $projectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        $this->productionService = new ProductionService();
        $this->projectionService = new ProductionProjectionService();
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

    public function test_single_event_projects_raw_consumption_and_variation_output(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $this->productionService->create('2026-09-16', $source->id, $target->id, 5);

        $this->assertSame('5.00', $this->projectionService->rawConsumption('2026-09-16', $source->id));
        $this->assertSame('20.00', $this->projectionService->variationOutput('2026-09-16', $target->variasi_gudang_id));
    }

    public function test_multi_process_events_aggregate_raw_consumption(): void
    {
        [$source, $k3] = $this->makeTarget('K3 SET', '3S BTG', '1.00');
        [, $k18] = $this->makeTarget('K18', '18 PCS', '4.00', $source);
        [, $k33] = $this->makeTarget('K33', '33 PCS', '4.00', $source);

        $this->productionService->create('2026-09-16', $source->id, $k3->id, 5);
        $this->productionService->create('2026-09-16', $source->id, $k18->id, 10);
        $this->productionService->create('2026-09-16', $source->id, $k33->id, 3);

        $this->assertSame('18.00', $this->projectionService->rawConsumption('2026-09-16', $source->id));
    }

    public function test_multiple_events_same_target_aggregate_output(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $this->productionService->create('2026-09-16', $source->id, $target->id, 5);
        $this->productionService->create('2026-09-16', $source->id, $target->id, 3);

        $this->assertSame('32.00', $this->projectionService->variationOutput('2026-09-16', $target->variasi_gudang_id));
    }

    public function test_variation_projection_uses_opening_production_output_and_out(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $this->productionService->create('2026-09-16', $source->id, $target->id, 5);
        $snapshot = StokVariasiHarian::create([
            'variasi_gudang_id' => $target->variasi_gudang_id,
            'tanggal' => '2026-09-16',
            'stok_awal' => '40.00',
            'input' => '0.00',
            'out' => '3.00',
        ]);

        $projection = $this->projectionService->variationProjection($snapshot);

        $this->assertSame('40.00', $projection['stok_awal']);
        $this->assertSame('20.00', $projection['production_output']);
        $this->assertSame('60.00', $projection['stok']);
        $this->assertSame('57.00', $projection['sisa']);
    }

    public function test_variation_snapshot_accessors_follow_table_two_formula(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $target->variasiGudang->update(['stok_aman' => '999.00']);

        $snapshot = StokVariasiHarian::create([
            'variasi_gudang_id' => $target->variasi_gudang_id,
            'tanggal' => '2026-09-16',
            'stok_awal' => '40.00',
            'input' => '15.00',
            'out' => '12.00',
        ]);

        $this->assertSame(55.0, $snapshot->stok_hasil);
        $this->assertSame(43.0, $snapshot->sisa);
        $this->assertSame(25.0, $snapshot->s_m_umma);
    }

    public function test_raw_projection_adds_production_consumption_without_creating_allocation(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $snapshot = StokHarianGudang::create([
            'barang_gudang_id' => $source->id,
            'tanggal' => '2026-09-16',
            'rak' => '100.00',
            'input' => '0.00',
        ]);
        $this->productionService->create('2026-09-16', $source->id, $target->id, 20);

        $this->assertSame('80.00', $this->projectionService->rawStockProjection($snapshot));
        $this->assertDatabaseCount('stok_alokasi_khusus_harian', 0);
    }

    public function test_projection_does_not_write_variation_input(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $this->productionService->create('2026-09-16', $source->id, $target->id, 5);

        $this->assertDatabaseCount('stok_variasi_harian', 0);
        $this->assertSame('20.00', $this->projectionService->variationOutput('2026-09-16', $target->variasi_gudang_id));
    }

    public function test_event_date_does_not_change_historical_snapshot(): void
    {
        [$source, $target] = $this->makeTarget('K33', '33 PCS', '4.00');
        $historical = StokHarianGudang::create([
            'barang_gudang_id' => $source->id,
            'tanggal' => '2026-09-15',
            'rak' => '100.00',
            'input' => '0.00',
        ]);
        $this->productionService->create('2026-09-16', $source->id, $target->id, 20);

        $this->assertSame('100.00', (string) $historical->fresh()->rak);
        $this->assertSame('0.00', $this->projectionService->rawConsumption('2026-09-15', $source->id));
        $this->assertSame('20.00', $this->projectionService->rawConsumption('2026-09-16', $source->id));
    }

    public function test_different_targets_remain_separate(): void
    {
        [$source, $first] = $this->makeTarget('K33', '33 PCS', '4.00');
        [, $second] = $this->makeTarget('K18', '18 PCS', '4.00', $source);
        $this->productionService->create('2026-09-16', $source->id, $first->id, 5);
        $this->productionService->create('2026-09-16', $source->id, $second->id, 2);

        $this->assertSame('20.00', $this->projectionService->variationOutput('2026-09-16', $first->variasi_gudang_id));
        $this->assertSame('8.00', $this->projectionService->variationOutput('2026-09-16', $second->variasi_gudang_id));
    }

    private function makeTarget(
        string $processCode,
        string $variationCode,
        string $multiplier,
        ?StokBarangGudang $source = null,
    ): array {
        $source ??= StokBarangGudang::create(['nama_barang' => 'Source']);
        $process = ProductionProcess::create(['kode_proses' => $processCode]);
        $variation = StokVariasiGudang::create([
            'barang_gudang_id' => $source->id,
            'kode_variasi' => $variationCode,
        ]);
        $target = ProductionProcessTarget::create([
            'production_process_id' => $process->id,
            'variasi_gudang_id' => $variation->id,
            'multiplier' => $multiplier,
        ]);

        return [$source, $target];
    }

    private function createTestSchema(): void
    {
        Schema::create('stok_barang_gudang', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_barang');
            $table->decimal('stok_aman', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_proses', 50)->unique();
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

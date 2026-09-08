<?php

namespace App\Filament\Pages;

use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StokMati extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box-x-mark';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Stok Mati';
    protected static ?string $title = 'Stok Mati';

    protected string $view = 'filament.pages.stok-mati';

    protected const HARI_TIDAK_BERGERAK = 60;

    /**
     * Cari id barang yang selama 60 hari terakhir:
     * - Tidak ada INPUT sama sekali di level barang mentah, DAN
     * - Tidak ada INPUT maupun OUT sama sekali di level variasi/kemasan
     * - Sudah punya histori minimal 60 hari (biar barang baru nggak
     *   ke-anggap mati padahal cuma belum sempat gerak)
     */
    public static function idBarangStokMati(): array
    {
        $batasTanggal = today()->subDays(self::HARI_TIDAK_BERGERAK)->toDateString();

        return DB::table('stok_barang_gudang as b')
            ->select('b.id')
            ->whereExists(function ($q) use ($batasTanggal) {
                $q->select(DB::raw(1))
                    ->from('stok_harian_gudang as h0')
                    ->whereColumn('h0.barang_gudang_id', 'b.id')
                    ->whereDate('h0.tanggal', '<=', $batasTanggal);
            })
            ->whereRaw('COALESCE((
                SELECT SUM(h.input) FROM stok_harian_gudang h
                WHERE h.barang_gudang_id = b.id AND h.tanggal >= ?
            ), 0) = 0', [$batasTanggal])
            ->whereRaw('COALESCE((
                SELECT SUM(vh.input) + SUM(vh.out)
                FROM stok_variasi_harian vh
                JOIN stok_variasi_gudang v ON v.id = vh.variasi_gudang_id
                WHERE v.barang_gudang_id = b.id AND vh.tanggal >= ?
            ), 0) = 0', [$batasTanggal])
            ->pluck('id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StokBarangGudang::query()->whereIn('id', static::idBarangStokMati()))
            ->columns([
                TextColumn::make('kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === StokBarangGudang::KATEGORI_ORIGAMI ? 'Origami' : 'Awan')
                    ->color(fn (string $state): string => $state === StokBarangGudang::KATEGORI_ORIGAMI ? 'warning' : 'info'),
                TextColumn::make('kode_barang')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama_barang')->label('Nama Barang')->searchable()->sortable(),
                TextColumn::make('stok_aman')->label('Stok Aman')->sortable(),
                TextColumn::make('rak_terkini')
                    ->label('Stok Akhir Terkini')
                    ->state(function (StokBarangGudang $record) {
                        $harian = $record->harianPadaTanggal(today()->toDateString());

                        return $harian?->stok_akhir ?? '-';
                    }),
            ])
            ->filters([
                SelectFilter::make('kategori')->options([
                    StokBarangGudang::KATEGORI_AWAN => 'Awan',
                    StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
                ]),
            ])
            ->defaultSort('nama_barang')
            ->emptyStateHeading('Tidak ada stok mati')
            ->emptyStateDescription('Semua barang masih bergerak dalam ' . self::HARI_TIDAK_BERGERAK . ' hari terakhir.');
    }
}
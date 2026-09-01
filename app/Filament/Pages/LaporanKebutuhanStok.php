<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiHarian;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LaporanKebutuhanStok extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Laporan Kebutuhan Stok';
    protected static ?string $title = 'Laporan Kebutuhan Stok';

    protected string $view = 'filament.pages.laporan-kebutuhan-stok';

    public function table(Table $table): Table
    {
        return $table
            ->query(StokVariasiHarian::query()->with(['variasiGudang.barangGudang']))
            ->columns([
                TextColumn::make('variasiGudang.barangGudang.kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StokBarangGudangResource::kategoriOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === StokBarangGudang::KATEGORI_ORIGAMI ? 'warning' : 'info'),
                TextColumn::make('variasiGudang.barangGudang.kode_barang')->label('Kode Barang'),
                TextColumn::make('variasiGudang.barangGudang.nama_barang')
                    ->label('Nama Barang')
                    ->searchable(),
                TextColumn::make('tanggal')->date(),
                TextColumn::make('permintaan_h')
                    ->label('Permintaan H')
                    ->state(fn (StokVariasiHarian $record) => $record->permintaan_h)
                    ->color(fn (StokVariasiHarian $record) => ($record->permintaan_h ?? 0) < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                TextColumn::make('variasiGudang.kode_variasi')->label('Kode Variasi'),
                TextColumn::make('s_m_umma')
                    ->label('S M Umma')
                    ->state(fn (StokVariasiHarian $record) => $record->s_m_umma),
            ])
            ->filters([
                Filter::make('kategori')
                    ->form([
                        Select::make('kategori')
                            ->options(StokBarangGudangResource::kategoriOptions())
                            ->placeholder('Semua kategori'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['kategori'] ?? null,
                            fn (Builder $q, $kategori) => $q->whereHas(
                                'variasiGudang.barangGudang',
                                fn (Builder $q2) => $q2->where('kategori', $kategori)
                            )
                        );
                    }),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari Tanggal')->default(today()),
                        DatePicker::make('sampai')->label('Sampai Tanggal')->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '>=', Carbon::parse($tanggal)))
                            ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '<=', Carbon::parse($tanggal)));
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }
}
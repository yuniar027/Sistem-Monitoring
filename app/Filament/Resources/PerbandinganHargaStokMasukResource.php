<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\PerbandinganHargaStokMasukResource\Pages;
use App\Models\PerbandinganHargaStokMasuk;
use App\Services\HargaAcuanOrigamiService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PerbandinganHargaStokMasukResource extends Resource
{
    protected static ?string $model = PerbandinganHargaStokMasuk::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Perbandingan Harga Stok Masuk';
    protected static ?string $modelLabel = 'Perbandingan Harga Stok Masuk';
    protected static ?string $pluralModelLabel = 'Perbandingan Harga Stok Masuk';

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status_approval', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        // Baris di sini dibuat otomatis oleh StokMasukService::bandingkanHarga()
        // untuk SETIAP stok masuk (manual maupun import/webhook) — tidak
        // boleh diinput manual.
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y')->sortable(),
                TextColumn::make('sku')->sortable()->searchable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk')->limit(40)->searchable(),
                TextColumn::make('stokMasuk.vendor')->label('Vendor'),
                TextColumn::make('kuantitas')->label('Qty')->sortable(),
                TextColumn::make('harga_acuan')->label('Harga Acuan')->money('IDR')->placeholder('— (belum ada acuan)'),
                TextColumn::make('harga_invoice')->label('Harga Invoice (Pabrik)')->money('IDR'),
                TextColumn::make('nilai_acuan')->label('Nilai Acuan')->money('IDR')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nilai_invoice')->label('Nilai Invoice')->money('IDR')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('selisih_nominal')->label('Selisih')->money('IDR')->placeholder('—')
                    ->color(fn ($state) => $state === null ? 'gray' : ($state < 0 ? 'success' : ($state > 0 ? 'danger' : 'gray'))),
                TextColumn::make('persen_selisih')
                    ->label('Persen')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2) . '%')
                    ->badge()
                    ->color(fn (PerbandinganHargaStokMasuk $record) => match ($record->kategori) {
                        'naik' => 'danger',
                        'turun' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'naik' => 'Naik',
                        'turun' => 'Turun',
                        'tetap' => 'Tetap',
                        'baru' => 'Baru',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'naik' => 'danger',
                        'turun' => 'success',
                        'tetap' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('status_approval')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'tidak_perlu' => 'Tidak Perlu',
                        'pending' => 'Menunggu',
                        'approved' => 'Diterima',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'gray',
                        'tidak_perlu' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status_approval')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu',
                        'approved' => 'Diterima',
                        'rejected' => 'Ditolak',
                        'tidak_perlu' => 'Tidak Perlu (harga tetap)',
                    ])
                    ->default('pending'),
                SelectFilter::make('kategori')
                    ->options([
                        'naik' => 'Naik',
                        'turun' => 'Turun',
                        'tetap' => 'Tetap',
                        'baru' => 'Baru',
                    ]),
            ])
            ->recordActions([
                Action::make('terima')
                    ->label('Terima')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (PerbandinganHargaStokMasuk $record) => $record->status_approval === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Terima harga invoice sebagai Harga Acuan baru?')
                    ->modalDescription('Harga Acuan Origami lama untuk SKU ini akan diakhiri (tetap tersimpan sebagai histori) dan digantikan Harga Acuan baru mulai dari tanggal stok masuk ini.')
                    ->action(function (PerbandinganHargaStokMasuk $record) {
                        app(HargaAcuanOrigamiService::class)->tetapkanHargaAktif(
                            sku: $record->sku,
                            hargaAcuan: (float) $record->harga_invoice,
                            berlakuMulai: $record->stokMasuk->tanggal,
                            catatan: "Disetujui dari perbandingan_harga_stok_masuk #{$record->id} (stok masuk #{$record->stok_masuk_id})",
                        );

                        $record->update([
                            'status_approval' => 'approved',
                            'diproses_pada' => now(),
                        ]);

                        Notification::make()
                            ->title('Harga Acuan diperbarui')
                            ->body("{$record->sku} sekarang mengacu ke harga baru mulai " . $record->stokMasuk->tanggal->format('d M Y') . '.')
                            ->success()
                            ->send();
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PerbandinganHargaStokMasuk $record) => $record->status_approval === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak harga invoice ini?')
                    ->modalDescription('Harga Acuan Origami tidak berubah. Baris ini tetap tercatat sebagai ditolak.')
                    ->action(function (PerbandinganHargaStokMasuk $record) {
                        $record->update([
                            'status_approval' => 'rejected',
                            'diproses_pada' => now(),
                        ]);

                        Notification::make()
                            ->title('Perbandingan harga ditolak')
                            ->body('Harga Acuan Origami tidak berubah.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerbandinganHargaStokMasuks::route('/'),
        ];
    }
}

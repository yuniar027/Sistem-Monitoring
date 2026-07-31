<?php

namespace App\Filament\Resources\PembayaranHutangs;

use App\Filament\Resources\PembayaranHutangs\Pages\ManagePembayaranHutangs;
use App\Models\PembayaranHutang;
use App\Models\BahanBakuMasuk;
use App\Models\StokMasuk;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Services\PembayaranHutangService;
use Filament\Actions\DeleteAction;

class PembayaranHutangResource extends Resource
{
    protected static ?string $model = PembayaranHutang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Pembayaran Hutang';

    protected static ?string $modelLabel = 'Pembayaran Hutang';

    protected static ?string $pluralModelLabel = 'Pembayaran Hutang';

    protected static ?string $recordTitleAttribute = 'keterangan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal')->required()->default(now()),

            Radio::make('sumber_tipe')
                ->label('Jenis Transaksi')
                ->options([
                    'bahan_baku_masuk' => 'Bahan Baku Masuk',
                    'stok_masuk' => 'Stok Masuk',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('sumber_id', null)),

            Select::make('sumber_id')
                ->label('Pilih Transaksi yang Belum Lunas')
                ->options(function ($get) {
                    $tipe = $get('sumber_tipe');

                    if ($tipe === 'bahan_baku_masuk') {
                        return BahanBakuMasuk::with('bahanBaku')
                            ->where('status_pembayaran', 'belum_lunas')
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => $item->tanggal->format('d M Y') . ' — ' . ($item->bahanBaku->nama_bahan ?? '-') . ' — Rp ' . number_format($item->total_nominal, 0, ',', '.'),
                            ]);
                    }

                    if ($tipe === 'stok_masuk') {
                        return StokMasuk::where('status_pembayaran', 'belum_lunas')
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => $item->tanggal->format('d M Y') . ' — ' . $item->sku . ' — Rp ' . number_format($item->total_nominal, 0, ',', '.'),
                            ]);
                    }

                    return [];
                })
                ->searchable()
                ->required()
                ->visible(fn ($get) => filled($get('sumber_tipe'))),

            TextInput::make('nominal')->required()->numeric()->minValue(0),
            TextInput::make('keterangan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->date(),
                TextColumn::make('sumber_tipe')->label('Jenis'),
                TextColumn::make('sumber_id')->label('ID Transaksi'),
                TextColumn::make('nominal')->money('IDR'),
                TextColumn::make('keterangan'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Batalkan')
                    ->modalHeading('Batalkan Pembayaran')
                    ->modalDescription('Transaksi ini akan dikembalikan ke status "belum lunas" dan jurnalnya akan dihapus. Yakin?')
                    ->action(function (PembayaranHutang $record) {
                        app(PembayaranHutangService::class)->batalkanPembayaran($record);
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePembayaranHutangs::route('/'),
        ];
    }
}
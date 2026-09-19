<?php

namespace App\Filament\Resources\PembelianGudangs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PembelianGudangInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Invoice')
                    ->schema([
                        TextEntry::make('nomor_invoice')
                            ->label('Nomor Invoice'),

                        TextEntry::make('tanggal')
                            ->label('Tanggal')
                            ->date('d/m/Y'),

                        TextEntry::make('supplier')
                            ->label('Supplier / Pabrik')
                            ->placeholder('—'),

                        TextEntry::make('catatan')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Detail Barang')
                    ->schema([
                        RepeatableEntry::make('detail')
                            ->label('')
                            ->getStateUsing(fn ($record) => $record
                                ->load('detail.barangGudang')
                                ->detail
                                ->map(fn ($detail) => [
                                    'barang' => $detail->barangGudang?->nama_barang ?? '—',
                                    'kode' => $detail->barangGudang?->kode_barang ?? '—',
                                    'kuantitas' => $detail->kuantitas,
                                    'harga_acuan' => $detail->harga_acuan_snapshot,
                                    'harga_invoice' => $detail->harga_invoice,
                                    'selisih_nominal' => $detail->selisih_nominal,
                                    'persen_selisih' => $detail->persen_selisih,
                                    'kategori_perbandingan' => $detail->kategori_perbandingan,
                                    'status_approval' => $detail->status_approval,
                                ])
                                ->toArray()
                            )
                            ->schema([
                                TextEntry::make('barang')
                                    ->label('Barang'),

                                TextEntry::make('kode')
                                    ->label('Kode'),

                                TextEntry::make('kuantitas')
                                    ->label('Kuantitas')
                                    ->numeric(decimalPlaces: 2),

                                TextEntry::make('harga_acuan')
                                    ->label('Harga Acuan')
                                    ->money('IDR'),

                                TextEntry::make('harga_invoice')
                                    ->label('Harga Invoice')
                                    ->money('IDR'),

                                TextEntry::make('selisih_nominal')
                                    ->label('Selisih')
                                    ->money('IDR'),

                                TextEntry::make('persen_selisih')
                                    ->label('Perubahan')
                                    ->formatStateUsing(fn ($state) => $state === null
                                        ? '—'
                                        : number_format(
                                            abs((float) $state),
                                            2,
                                            ',',
                                            '.'
                                        ) . '%'
                                    ),

                                TextEntry::make('kategori_perbandingan')
                                    ->label('Status Harga')
                                    ->formatStateUsing(
                                        fn ($state) => strtoupper((string) $state)
                                    )
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'naik' => 'danger',
                                        'turun' => 'success',
                                        'sama' => 'gray',
                                        default => 'gray',
                                    }),

                                TextEntry::make('status_approval')
                                    ->label('Approval')
                                    ->formatStateUsing(
                                        fn ($state) => match ($state) {
                                            'menunggu' => 'MENUNGGU',
                                            'disetujui' => 'DISETUJUI',
                                            'ditolak' => 'DITOLAK',
                                            'tidak_perlu' => 'TIDAK PERLU',
                                            default => strtoupper((string) $state),
                                        }
                                    )
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'menunggu' => 'warning',
                                        'disetujui' => 'success',
                                        'ditolak' => 'danger',
                                        'tidak_perlu' => 'gray',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(3)
                            ->contained(true),
                    ]),
            ]);
    }
}
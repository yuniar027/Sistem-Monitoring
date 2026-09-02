<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use App\Models\StokVariasiHarian;
use BackedEnum;
use Filament\Actions\Action;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanKebutuhanStok extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Laporan Kebutuhan Stok';
    protected static ?string $title = 'Laporan Kebutuhan Stok';

    protected string $view = 'filament.pages.laporan-kebutuhan-stok';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return $this->exportKeExcel();
                }),
        ];
    }

    protected function queryLaporanExport(): Builder
    {
        $filters = $this->tableFilters ?? [];
        $tanggalDari = $filters['tanggal']['dari'] ?? today()->toDateString();
        $tanggalSampai = $filters['tanggal']['sampai'] ?? today()->toDateString();
        $kategori = $filters['kategori']['kategori'] ?? null;

        return StokHarianGudang::query()
            ->with('barangGudang')
            ->whereDate('tanggal', '>=', Carbon::parse($tanggalDari))
            ->whereDate('tanggal', '<=', Carbon::parse($tanggalSampai))
            ->when($kategori, fn (Builder $q, $kat) => $q->whereHas(
                'barangGudang',
                fn (Builder $q2) => $q2->where('kategori', $kat)
            ))
            ->orderBy('tanggal');
    }

    protected function exportKeExcel()
    {
        $filters = $this->tableFilters ?? [];
        $tanggalDari = Carbon::parse($filters['tanggal']['dari'] ?? today()->toDateString());
        $tanggalSampai = Carbon::parse($filters['tanggal']['sampai'] ?? today()->toDateString());
        $kategori = $filters['kategori']['kategori'] ?? null;

        $labelKategori = match ($kategori) {
            StokBarangGudang::KATEGORI_AWAN => 'Awan',
            StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
            default => 'Semua Kategori',
        };

        $labelTanggal = $tanggalDari->equalTo($tanggalSampai)
            ? $tanggalDari->format('d-m-Y')
            : $tanggalDari->format('d-m-Y') . ' s/d ' . $tanggalSampai->format('d-m-Y');

        $rows = $this->queryLaporanExport()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Permintaan Stok');

        $sheet->setCellValue('A1', 'Laporan Permintaan Stok');
        $sheet->setCellValue('A2', 'Kategori: ' . $labelKategori);
        $sheet->setCellValue('A3', 'Tanggal: ' . $labelTanggal);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);

        $baris = 5;
        $sheet->setCellValue("A{$baris}", 'Nama Barang');
        $sheet->setCellValue("B{$baris}", 'Permintaan H');
        $sheet->getStyle("A{$baris}:B{$baris}")->getFont()->setBold(true);
        $baris++;

        foreach ($rows as $row) {
            $sheet->setCellValue("A{$baris}", $row->barangGudang?->nama_barang);
            $sheet->setCellValue("B{$baris}", (float) $row->permintaan_h);
            $baris++;
        }

        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(15);

        $namaFile = 'laporan-permintaan-stok-' . now()->format('Y-m-d_His') . '.xlsx';
        $pathSementara = storage_path('app/temp/' . $namaFile);

        if (! is_dir(dirname($pathSementara))) {
            mkdir(dirname($pathSementara), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($pathSementara);

        return response()->download($pathSementara, $namaFile)->deleteFileAfterSend(true);
    }

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
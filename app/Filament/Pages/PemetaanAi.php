<?php

namespace App\Filament\Pages;

use App\Models\SaranPemetaanBahanBaku;
use App\Services\PemetaanBahanBakuService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;

class PemetaanAi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'AI Mapping';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $title = 'AI Mapping Bahan Baku';

    protected string $view = 'filament.pages.pemetaan-ai';

    public ?string $daftarNama = '';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('daftarNama')
                ->label('Tempel nama item dari invoice pabrik (1 baris = 1 item)')
                ->rows(8)
                ->placeholder("ST CLN POP ABU\nJUMPER S4 CREM\nBEDONG ABU"),
        ])->statePath('');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function petakan(): void
    {
        $namaList = array_filter(array_map('trim', explode("\n", $this->daftarNama ?? '')));

        if (empty($namaList)) {
            return;
        }

        $service = App::make(PemetaanBahanBakuService::class);
        $hasil = $service->petakanBanyak($namaList);

        foreach ($hasil as $baris) {
            SaranPemetaanBahanBaku::create([
                'nama_item' => $baris['nama_item'],
                'kode_bahan_disarankan' => $baris['kode_bahan'],
                'nama_bahan' => $baris['nama_bahan'],
                'metode' => $baris['metode'],
                'catatan' => $baris['skor_atau_alasan'],
            ]);
        }

        $this->daftarNama = '';
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SaranPemetaanBahanBaku::query())
            ->columns([
                TextColumn::make('nama_item')->label('Nama Item (Invoice)')->searchable(),
                TextColumn::make('kode_bahan_disarankan')->label('Kode Bahan')->placeholder('—')->fontFamily('mono'),
                TextColumn::make('nama_bahan')->label('Nama Bahan')->placeholder('—')->wrap(),
                TextColumn::make('metode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'heuristik' => 'success',
                        'ai' => 'info',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'heuristik' => 'Heuristik',
                        'ai' => 'AI',
                        default => 'Tidak ditemukan',
                    }),
                TextColumn::make('catatan')->label('Catatan')->limit(60)->wrap()->color('gray'),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

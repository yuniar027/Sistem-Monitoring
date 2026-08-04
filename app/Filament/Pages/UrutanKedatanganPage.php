<?php

namespace App\Filament\Pages;

use App\Models\Packer;
use App\Models\UrutanKedatangan;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class UrutanKedatanganPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Urutan Kedatangan';
    protected static string|\UnitEnum|null $navigationGroup = 'Stok & Gudang';
    protected static ?string $title = 'Urutan Kedatangan Packer Hari Ini';

    protected string $view = 'filament.pages.urutan-kedatangan-page';

    public ?array $data = [];

    public function mount(): void
    {
        $existing = UrutanKedatangan::where('tanggal', now()->toDateString())
            ->orderBy('urutan')
            ->pluck('packer_id')
            ->map(fn ($id) => ['packer_id' => $id])
            ->toArray();

        $this->form->fill(['urutan' => $existing]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Repeater::make('urutan')
                ->label('Urutan Datang (dari atas = paling pagi)')
                ->schema([
                    Select::make('packer_id')
                        ->label('Nama Packer')
                        ->options(fn () => Packer::where('status', 'aktif')->orderBy('nama')->pluck('nama', 'id')->toArray())
                        ->searchable()
                        ->required(),
                ])
                ->reorderable()
                ->addActionLabel('+ Tambah Packer')
                ->defaultItems(0),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        UrutanKedatangan::where('tanggal', now()->toDateString())->delete();

        foreach ($data['urutan'] as $i => $row) {
            UrutanKedatangan::create([
                'tanggal' => now()->toDateString(),
                'packer_id' => $row['packer_id'],
                'urutan' => $i + 1,
            ]);
        }

        Notification::make()->title('Urutan kedatangan hari ini tersimpan')->success()->send();
    }
}
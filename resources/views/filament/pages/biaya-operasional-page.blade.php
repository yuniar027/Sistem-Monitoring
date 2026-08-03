<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Simpan
        </x-filament::button>
    </form>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
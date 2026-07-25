<x-filament-panels::page>
    <form wire:submit.prevent="petakan">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Petakan Sekarang
        </x-filament::button>
    </form>

    <p class="mt-4 text-xs text-gray-500">
        Hasil pemetaan tersimpan di tabel di bawah. Selalu cek ulang hasil "AI" dan "Tidak ditemukan"
        secara manual sebelum dipakai. Jangan langsung dipercaya 100% terutama untuk data yang
        mempengaruhi stok/keuangan.
    </p>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
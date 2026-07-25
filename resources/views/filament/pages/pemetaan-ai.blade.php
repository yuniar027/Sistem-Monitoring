<x-filament-panels::page>
    <form wire:submit="petakan">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Petakan Sekarang
        </x-filament::button>
    </form>

    @if ($sudahDijalankan)
        <div class="mt-6">
            <h3 class="text-lg font-medium mb-2">Hasil Pemetaan</h3>

            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 pr-4">Nama Item (Invoice)</th>
                        <th class="py-2 pr-4">Kode Bahan Disarankan</th>
                        <th class="py-2 pr-4">Nama Bahan</th>
                        <th class="py-2 pr-4">Metode</th>
                        <th class="py-2 pr-4">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hasil as $baris)
                        <tr class="border-b">
                            <td class="py-2 pr-4">{{ $baris['nama_item'] }}</td>
                            <td class="py-2 pr-4 font-mono">{{ $baris['kode_bahan'] ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $baris['nama_bahan'] ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if ($baris['metode'] === 'heuristik')
                                    <span class="px-2 py-1 rounded bg-green-100 text-green-800 text-xs">Heuristik</span>
                                @elseif ($baris['metode'] === 'ai')
                                    <span class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs">AI</span>
                                @else
                                    <span class="px-2 py-1 rounded bg-red-100 text-red-800 text-xs">Tidak ditemukan</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-xs text-gray-500">{{ $baris['skor_atau_alasan'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="mt-4 text-xs text-gray-500">
                Selalu cek ulang hasil "AI" dan "Tidak ditemukan" secara manual sebelum dipakai —
                jangan langsung dipercaya 100% terutama untuk data yang mempengaruhi stok/keuangan.
            </p>
        </div>
    @endif
</x-filament-panels::page>

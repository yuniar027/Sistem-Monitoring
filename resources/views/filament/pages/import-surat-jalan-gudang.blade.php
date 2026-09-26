<x-filament-panels::page>
    <form wire:submit="bacaPdf">
        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::actions :actions="$this->getFormActions()" />
        </div>
    </form>

    @if ($preview !== null)
        <div style="margin-top: 2rem;">

            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="font-size: 1rem; font-weight: 600;">
                    Preview untuk tanggal: {{ \Illuminate\Support\Carbon::parse($tanggalPreview)->translatedFormat('d F Y') }}
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="simpanKeStokHarian"
                        wire:confirm="Simpan {{ count(array_filter($preview, fn ($p) => $p['cocok'])) }} kode barang ke Input Stok Harian tanggal {{ \Illuminate\Support\Carbon::parse($tanggalPreview)->translatedFormat('d F Y') }}? Nilai Input yang sudah ada untuk tanggal ini akan DITIMPA."
                        style="background: #16a34a; color: #fff; border: none; border-radius: 0.5rem; padding: 0.5rem 1rem; font-weight: 600; cursor: pointer;"
                    >
                        Simpan ke Input Stok Harian
                    </button>

                    <button
                        type="button"
                        wire:click="batalkanPreview"
                        style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 1rem; font-weight: 600; cursor: pointer;"
                    >
                        Batal
                    </button>
                </div>
            </div>

            @if (! empty($peringatan))
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 0.25rem;">Perhatian:</div>
                    <ul style="margin: 0; padding-left: 1.25rem; color: #92400e; font-size: 0.875rem;">
                        @foreach ($peringatan as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f9fafb; text-align: left;">
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb;">Kode Barang</th>
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb;">Nama (dari PDF)</th>
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb;">Nama (Master Gudang)</th>
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb; text-align: right;">Input Lama</th>
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb; text-align: right;">Qty Baru (Surat Jalan)</th>
                            <th style="padding: 0.6rem 0.9rem; border-bottom: 1px solid #e5e7eb;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview as $baris)
                            <tr style="background: {{ $baris['cocok'] ? '#fff' : '#fef2f2' }}; border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.5rem 0.9rem; font-family: monospace;">{{ $baris['kode'] }}</td>
                                <td style="padding: 0.5rem 0.9rem;">{{ $baris['nama'] }}</td>
                                <td style="padding: 0.5rem 0.9rem; color: #6b7280;">{{ $baris['nama_master'] ?? '-' }}</td>
                                <td style="padding: 0.5rem 0.9rem; text-align: right; color: #6b7280;">
                                    {{ $baris['input_lama'] !== null ? number_format($baris['input_lama'], 2, ',', '.') : '-' }}
                                </td>
                                <td style="padding: 0.5rem 0.9rem; text-align: right; font-weight: 600;">
                                    {{ number_format($baris['qty'], 2, ',', '.') }}
                                </td>
                                <td style="padding: 0.5rem 0.9rem;">
                                    @if ($baris['cocok'])
                                        <span style="background: #dcfce7; color: #166534; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">Cocok</span>
                                    @else
                                        <span style="background: #fee2e2; color: #991b1b; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">Kode tidak ketemu</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 0.75rem; color: #6b7280; font-size: 0.85rem;">
                Total kode terbaca: {{ count($preview) }}
                &middot; Cocok: {{ count(array_filter($preview, fn ($p) => $p['cocok'])) }}
                &middot; Tidak ketemu di Master Barang Gudang: {{ count(array_filter($preview, fn ($p) => ! $p['cocok'])) }}
            </div>
        </div>
    @endif
</x-filament-panels::page>
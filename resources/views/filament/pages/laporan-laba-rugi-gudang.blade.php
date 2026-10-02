@php
    $rp = fn ($angka) => ($angka < 0 ? '-' : '') . 'Rp ' . number_format(abs($angka), 0, ',', '.');
    $garis = 'border-bottom: 1px solid rgba(128,128,128,.25);';
    $warnaLaba = fn ($angka) => $angka >= 0 ? '#16a34a' : '#dc2626';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <div style="font-size:.85rem; margin-bottom:.25rem;">Dari tanggal</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="tanggalAwal" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <div style="font-size:.85rem; margin-bottom:.25rem;">Sampai tanggal</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="tanggalAkhir" />
                </x-filament::input.wrapper>
            </div>
            <div style="display:flex; gap:.5rem;">
                <x-filament::button size="sm" color="gray" wire:click="bulanIni">Bulan ini</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="bulanLalu">Bulan lalu</x-filament::button>
            </div>
        </div>
    </x-filament::section>

    @if ($laporan['penjualan_kosong'])
        <x-filament::section>
            <div style="color:#b45309;">
                Belum ada penjualan tercatat pada periode ini, jadi laporan ini baru menampilkan biaya saja
                dan hasilnya belum bisa disebut laba rugi yang sebenarnya. Catat penjualan lewat menu
                <strong>Penjualan (Gelondongan)</strong>.
            </div>
        </x-filament::section>
    @elseif ($laporan['hpp_kosong'])
        <x-filament::section>
            <div style="color:#b45309;">
                HPP pada periode ini belum diisi, jadi Laba Kotor sama dengan Penjualan dan laba bersih
                bisa terlihat lebih besar dari sebenarnya. Isi kolom HPP di menu <strong>Penjualan (Gelondongan)</strong>
                kalau modal barangnya diketahui.
            </div>
        </x-filament::section>
    @endif

    <x-filament::section
        heading="Laba Rugi"
        :description="\Illuminate\Support\Carbon::parse($laporan['awal'])->translatedFormat('d M Y') . ' - ' . \Illuminate\Support\Carbon::parse($laporan['akhir'])->translatedFormat('d M Y')"
    >
        <table style="width:100%; border-collapse:collapse; font-size:.95rem;">
            <tbody>
                <tr style="{{ $garis }}">
                    <td style="padding:.6rem 0;">Penjualan</td>
                    <td style="padding:.6rem 0; text-align:right;">{{ $rp($laporan['penjualan']) }}</td>
                </tr>
                <tr style="{{ $garis }}">
                    <td style="padding:.6rem 0;">HPP (modal barang terjual)</td>
                    <td style="padding:.6rem 0; text-align:right;">{{ $rp($laporan['hpp']) }}</td>
                </tr>
                <tr style="{{ $garis }} font-weight:700;">
                    <td style="padding:.6rem 0;">Laba Kotor</td>
                    <td style="padding:.6rem 0; text-align:right; color:{{ $warnaLaba($laporan['laba_kotor']) }};">{{ $rp($laporan['laba_kotor']) }}</td>
                </tr>

                <tr>
                    <td colspan="2" style="padding:1rem 0 .25rem; font-weight:600;">Biaya Operasional</td>
                </tr>
                @forelse ($laporan['rincian_biaya'] as $baris)
                    <tr style="{{ $garis }}">
                        <td style="padding:.5rem 0 .5rem 1.25rem;">{{ $baris['label'] }}</td>
                        <td style="padding:.5rem 0; text-align:right;">{{ $rp($baris['total']) }}</td>
                    </tr>
                @empty
                    <tr style="{{ $garis }}">
                        <td colspan="2" style="padding:.5rem 0 .5rem 1.25rem; color:#6b7280;">Belum ada biaya pada periode ini</td>
                    </tr>
                @endforelse
                <tr style="{{ $garis }} font-weight:700;">
                    <td style="padding:.6rem 0;">Total Biaya Operasional</td>
                    <td style="padding:.6rem 0; text-align:right;">{{ $rp($laporan['total_biaya']) }}</td>
                </tr>

                <tr style="font-weight:700; font-size:1.1rem;">
                    <td style="padding:.9rem 0;">Laba Bersih</td>
                    <td style="padding:.9rem 0; text-align:right; color:{{ $warnaLaba($laporan['laba_bersih']) }};">
                        {{ $rp($laporan['laba_bersih']) }}
                    </td>
                </tr>
                @if ($laporan['margin'] !== null)
                    <tr>
                        <td style="color:#6b7280; font-size:.85rem;">Margin laba bersih</td>
                        <td style="text-align:right; color:#6b7280; font-size:.85rem;">{{ number_format($laporan['margin'], 1, ',', '.') }}%</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>

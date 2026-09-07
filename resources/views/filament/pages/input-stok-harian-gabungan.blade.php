<x-filament-panels::page>
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="min-width: 240px; flex: 2;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Cari Nama Barang</label>
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Ketik nama barang..."
                style="width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 0.75rem;"
            />
        </div>

        <div style="min-width: 180px;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Tanggal</label>
            <input
                type="date"
                wire:model.live="tanggal"
                style="width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 0.75rem;"
            />
        </div>

        <div style="min-width: 180px;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Kategori</label>
            <select
                wire:model.live="kategoriFilter"
                style="width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 0.75rem;"
            >
                <option value="">Semua Kategori</option>
                @foreach ($this->kategoriOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php $daftar = $this->getKelompokList(); @endphp

    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: #6b7280; font-weight: 600;">Kategori</th>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: #6b7280; font-weight: 600;">Nama Barang</th>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: #6b7280; font-weight: 600;">Varian</th>
                    <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.8rem; color: #6b7280; font-weight: 600;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $kelompok)
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 0.75rem 1rem; vertical-align: top;">
                            <span style="display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 9999px; background: {{ $kelompok['kategori'] === 'origami' ? '#fef3c7' : '#dbeafe' }}; color: {{ $kelompok['kategori'] === 'origami' ? '#92400e' : '#1e40af' }};">
                                {{ $kelompok['kategori'] === 'origami' ? 'Origami' : 'Awan' }}
                            </span>
                        </td>
                        <td style="padding: 0.75rem 1rem; vertical-align: top; font-weight: 600; color: #111827;">
                            {{ $kelompok['nama_dasar'] }}
                        </td>
                        <td style="padding: 0.75rem 1rem; vertical-align: top;">
                            @foreach ($kelompok['anggota'] as $anggota)
                                <span style="display: inline-block; background: #f3f4f6; border-radius: 0.35rem; padding: 0.1rem 0.45rem; margin: 0.1rem; font-size: 0.8rem; color: #4b5563;">
                                    {{ $anggota->akhiran_varian ?? $anggota->nama_barang }}
                                </span>
                            @endforeach
                        </td>
                        <td style="padding: 0.75rem 1rem; vertical-align: top; text-align: right; white-space: nowrap;">
                            {{ ($this->isiKelompokAction)([
                                'nama_dasar' => $kelompok['nama_dasar'],
                                'barang_ids' => $kelompok['anggota']->pluck('id')->toArray(),
                            ]) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 2rem; text-align: center; color: #9ca3af;">
                            Tidak ada barang yang cocok
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 0.75rem; font-size: 0.8rem; color: #9ca3af;">
        Menampilkan {{ $daftar->count() }} kelompok barang
    </div>
</x-filament-panels::page>
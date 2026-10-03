<x-filament-panels::page>
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="min-width: 240px; flex: 2;">
            <label style="display: block; font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Cari Nama Seri</label>
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

        <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #6b7280;">
            <span style="display: inline-block; width: 0.7rem; height: 0.7rem; border-radius: 9999px; background: #fecaca; border: 1px solid #ef4444;"></span>
            Di bawah stok aman
        </div>
    </div>

    @php
        $daftar = $this->getKelompokListPaginated();
        $data = $this->dataHalaman($daftar);
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        @forelse ($daftar as $kelompok)
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
                <div style="padding: 0.85rem 1.1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 0.6rem;">
                    <span style="display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 9999px; background: {{ $kelompok['kategori'] === 'origami' ? '#fef3c7' : '#dbeafe' }}; color: {{ $kelompok['kategori'] === 'origami' ? '#92400e' : '#1e40af' }};">
                        {{ $this->kategoriLabel($kelompok['kategori']) }}
                    </span>
                    <span style="font-weight: 700; color: #111827;">{{ $kelompok['nama_dasar'] }}</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; border-bottom: 1px solid #f3f4f6;">
                    {{-- Blok kiri: Barang (mentah) --}}
                    <div style="border-right: 1px solid #f3f4f6; overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="text-align: left; color: #6b7280;">
                                    <th style="padding: 0.5rem 1rem; font-weight: 600;">Barang</th>
                                    <th style="padding: 0.5rem 0.5rem; text-align: right; font-weight: 600;">Rak</th>
                                    <th style="padding: 0.5rem 0.5rem; text-align: right; font-weight: 600;">Input</th>
                                    <th style="padding: 0.5rem 1rem; text-align: right; font-weight: 600;">Stok Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kelompok['barang'] as $barang)
                                    @php
                                        $harian = $data['harian'][$barang->id] ?? null;
                                        $stokAkhir = $data['stokAkhir'][$barang->id] ?? 0;
                                        $rendah = $stokAkhir < (float) ($barang->stok_aman ?? 0);
                                    @endphp
                                    <tr style="border-top: 1px solid #f3f4f6; {{ $rendah ? 'background: #fef2f2;' : '' }}">
                                        <td style="padding: 0.5rem 1rem; color: #374151;">
                                            {{ $barang->akhiran_varian ?? $barang->nama_barang }}
                                        </td>
                                        <td style="padding: 0.5rem 0.5rem; text-align: right; color: #6b7280;">
                                            {{ number_format($harian?->rak ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 0.5rem; text-align: right; color: #6b7280;">
                                            {{ number_format($harian?->input ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 1rem; text-align: right; font-weight: 700; color: {{ $rendah ? '#b91c1c' : '#111827' }};">
                                            {{ number_format($stokAkhir, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Blok kanan: Variasi (jadi) --}}
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="text-align: left; color: #6b7280;">
                                    <th style="padding: 0.5rem 1rem; font-weight: 600;">Variasi</th>
                                    <th style="padding: 0.5rem 0.5rem; text-align: right; font-weight: 600;">Stok Awal</th>
                                    <th style="padding: 0.5rem 0.5rem; text-align: right; font-weight: 600;">Out</th>
                                    <th style="padding: 0.5rem 1rem; text-align: right; font-weight: 600;">Sisa</th>
                                    <th style="padding: 0.5rem 1rem; text-align: right;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($kelompok['variasi'] as $variasi)
                                    @php
                                        $harianVariasi = $data['variasiHarian'][$variasi->id] ?? null;
                                        $sisa = $harianVariasi?->sisa ?? 0;
                                        $rendah = $sisa < (float) ($variasi->stok_aman ?? 0);
                                        $urlEdit = $data['urlEdit'][$variasi->id] ?? null;
                                    @endphp
                                    <tr style="border-top: 1px solid #f3f4f6; {{ $rendah ? 'background: #fef2f2;' : '' }}">
                                        <td style="padding: 0.5rem 1rem; color: #374151;">
                                            {{ $variasi->kode_variasi }}
                                        </td>
                                        <td style="padding: 0.5rem 0.5rem; text-align: right; color: #6b7280;">
                                            {{ number_format($harianVariasi?->stok_awal ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 0.5rem; text-align: right; color: #6b7280;">
                                            {{ number_format($harianVariasi?->out ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 1rem; text-align: right; font-weight: 700; color: {{ $rendah ? '#b91c1c' : '#111827' }};">
                                            {{ number_format($sisa, 0, ',', '.') }}
                                        </td>
                                        <td style="padding: 0.5rem 1rem; text-align: right; white-space: nowrap;">
                                            @if ($urlEdit)
                                                <a href="{{ $urlEdit }}" style="font-size: 0.78rem; color: #6366f1; text-decoration: none;">Edit</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding: 0.75rem 1rem; color: #9ca3af;">
                                            Belum ada variasi terdaftar untuk seri ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div style="padding: 2rem; text-align: center; color: #9ca3af; background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem;">
                Tidak ada seri yang cocok
            </div>
        @endforelse
    </div>

    {{-- Kontrol pagination --}}
    <div style="margin-top: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="font-size: 0.85rem; color: #6b7280;">
            Menampilkan {{ $daftar->firstItem() ?? 0 }}–{{ $daftar->lastItem() ?? 0 }} dari {{ $daftar->total() }} seri
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label style="font-size: 0.85rem; color: #6b7280;">Per halaman</label>
            <select
                wire:model.live="perPage"
                style="border: 1px solid #d1d5db; border-radius: 0.4rem; padding: 0.3rem 0.5rem; font-size: 0.85rem;"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>

            <button
                type="button"
                wire:click="$set('page', {{ max(1, $this->page - 1) }})"
                @if ($this->page <= 1) disabled @endif
                style="border: 1px solid #d1d5db; border-radius: 0.4rem; padding: 0.35rem 0.75rem; font-size: 0.85rem; background: #fff; cursor: {{ $this->page <= 1 ? 'not-allowed' : 'pointer' }}; opacity: {{ $this->page <= 1 ? '0.5' : '1' }};"
            >
                &laquo; Sebelumnya
            </button>

            <span style="font-size: 0.85rem; color: #374151; padding: 0 0.25rem;">
                Halaman {{ $daftar->currentPage() }} dari {{ $daftar->lastPage() }}
            </span>

            <button
                type="button"
                wire:click="$set('page', {{ min($daftar->lastPage(), $this->page + 1) }})"
                @if ($this->page >= $daftar->lastPage()) disabled @endif
                style="border: 1px solid #d1d5db; border-radius: 0.4rem; padding: 0.35rem 0.75rem; font-size: 0.85rem; background: #fff; cursor: {{ $this->page >= $daftar->lastPage() ? 'not-allowed' : 'pointer' }}; opacity: {{ $this->page >= $daftar->lastPage() ? '0.5' : '1' }};"
            >
                Selanjutnya &raquo;
            </button>
        </div>
    </div>
</x-filament-panels::page>
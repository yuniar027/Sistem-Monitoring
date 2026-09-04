<x-filament-panels::page>
    <div style="margin-bottom: 1.5rem; color: #6b7280; font-size: 0.95rem;">
        {{ $this->getTanggalHariIni() }}
    </div>

    {{-- Ringkasan hari ini --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: #6b7280;">Total Barang Terdaftar</div>
            <div style="font-size: 2rem; font-weight: 700; color: #111827;">{{ $this->getTotalBarangAktif() }}</div>
        </div>

        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: #6b7280;">Belum Diisi Hari Ini</div>
            <div style="font-size: 2rem; font-weight: 700; color: {{ $this->getJumlahBelumDiisi() > 0 ? '#d97706' : '#16a34a' }};">
                {{ $this->getJumlahBelumDiisi() }}
            </div>
        </div>

        <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: #6b7280;">Stok Rendah Hari Ini</div>
            <div style="font-size: 2rem; font-weight: 700; color: {{ $this->getJumlahStokRendah() > 0 ? '#dc2626' : '#16a34a' }};">
                {{ $this->getJumlahStokRendah() }}
            </div>
        </div>
    </div>

    {{-- Checklist 2 langkah harian --}}
    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 0.75rem; color: #111827;">
        Yang Perlu Dikerjakan Hari Ini
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
        <a href="{{ \App\Filament\Resources\StokHarianGudangResource::getUrl('index') }}"
           style="display: block; text-decoration: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: #2563eb; font-weight: 600; margin-bottom: 0.25rem;">LANGKAH 1</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem;">
                Isi Stok Harian
            </div>
            <div style="font-size: 0.9rem; color: #4b5563;">
                Catat barang mentah yang masuk dari pabrik hari ini
            </div>
        </a>

        <a href="{{ \App\Filament\Resources\StokVariasiHarianResource::getUrl('index') }}"
           style="display: block; text-decoration: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: #16a34a; font-weight: 600; margin-bottom: 0.25rem;">LANGKAH 2</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem;">
                Isi Variasi Harian
            </div>
            <div style="font-size: 0.9rem; color: #4b5563;">
                Catat kemasan yang jadi (input) dan terjual/keluar (out)
            </div>
        </a>

        <a href="{{ \App\Filament\Pages\LaporanKebutuhanStok::getUrl() }}"
           style="display: block; text-decoration: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: 0.75rem; padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: #dc2626; font-weight: 600; margin-bottom: 0.25rem;">CEK KALAU PERLU</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem;">
                Laporan Kebutuhan Stok
            </div>
            <div style="font-size: 0.9rem; color: #4b5563;">
                Lihat barang yang perlu direstock, export ke Excel buat pabrik
            </div>
        </a>
    </div>
</x-filament-panels::page>
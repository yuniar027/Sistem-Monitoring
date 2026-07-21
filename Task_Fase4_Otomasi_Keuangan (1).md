# Instruksi Fase 4 — Otomasi Keuangan (siap tempel ke AI coding assistant)

## ⚠️ Prasyarat WAJIB sebelum mulai — isi harga modal dulu

Kolom `produk_master.harga_modal_default` masih **kosong** untuk semua SKU (sengaja dikosongkan waktu import data dari Shopee, karena data itu cuma punya harga jual, bukan harga modal). Fase 4 ini menghitung **HPP (Harga Pokok Penjualan)** dan **laba rugi**, yang butuh angka ini — kalau masih kosong, laporan nanti akan salah total (seolah untung 100% karena modal dianggap 0).

**Sebelum minta AI mulai Langkah 1, lakukan ini dulu:**
1. Tanya bagian pembelian/admin gudang: **harga beli per lusin dari pabrik** untuk tiap SKU
2. Buka `/admin/produk-masters`, edit tiap SKU, isi `harga_modal_default` = harga per lusin ÷ `isi_per_satuan_beli` (harga modal per **pcs**, bukan per lusin)
3. Minimal isi untuk SKU yang sudah kamu pakai testing (`3-BD-ALL-RAB-1`), supaya bisa langsung dites — SKU lain boleh menyusul belakangan
4. Kalau belum sempat dapat data asli, boleh isi angka estimasi sementara (misal 60% dari harga jual) — asal jangan dibiarkan kosong, dan ingat untuk diperbaiki nanti dengan angka asli

## Konteks (tempel di awal, jangan dihapus)

Project Laravel 13 + Filament v5.6.8 sudah terinstall dan terkoneksi ke PostgreSQL (Supabase). Fase 1 (fondasi data + konversi satuan), Fase 2 (split stok + alokasi), dan Fase 3 (API webhook n8n) sudah selesai dan teruji lengkap. Jangan ubah apapun dari fase-fase itu kecuali diminta eksplisit.

**Catatan versi Filament v5** tetap berlaku untuk Resource/Page baru — cek source vendor kalau ragu soal API.

## Kenapa Fase 4 berbeda dari fase sebelumnya

Fase 1-3 kita bangun struktur data dan alurnya. Fase 4 ini soal **kebenaran angka akuntansi** — kesalahan di sini tidak akan kelihatan sebagai error PHP, tapi sebagai **laporan yang salah tapi kelihatan normal** (paling berbahaya, karena bisa tidak ketahuan lama). Makanya testing Fase 4 fokus ke **verifikasi angka manual**, bukan cuma "tidak ada error di layar".

## Bagan Akun (Chart of Accounts) — pakai kode ini, jangan improvisasi sendiri

Buat file `config/akun.php` berisi mapping tetap ini, supaya semua Service pakai kode yang sama (bukan string hardcode tersebar di banyak file):

```php
<?php
return [
    'kas' => '1100',
    'piutang_usaha' => '1200',
    'persediaan' => '1300',
    'hutang_usaha' => '2100',
    'modal' => '3100',
    'penjualan' => '4100',
    'hpp' => '5100',
    'biaya_operasional' => '6100',
];
```

Konvensi: kode awalan `1` = Aset, `2` = Kewajiban, `3` = Modal, `4` = Pendapatan, `5`-`6` = Beban. Ini dipakai nanti di Langkah 4 (Neraca) untuk mengelompokkan otomatis berdasarkan awalan kode.

## Tugas Fase 4

Kerjakan urutan ini, **tunggu konfirmasi saya sebelum lanjut ke langkah berikutnya**:

### Langkah 1 — Observer Jurnal Otomatis untuk `stok_masuk`

- Buat **Observer baru terpisah** (jangan modifikasi `StokMasukObserver` yang sudah ada dari Fase 1 — biar tidak ada risiko merusak yang sudah jalan): `JurnalStokMasukObserver`, listen ke event `created` pada model `StokMasuk`
- Di dalam `DB::transaction()`, buat 2 baris `jurnal_umum` (asumsi pembelian tunai — catat di komentar kode kalau asumsi ini nanti perlu diubah jadi hutang):
  - Debit `persediaan` sebesar `total_nominal`, kredit `kas` sebesar `total_nominal`
  - `sumber_tipe` dan `sumber_id` mengarah ke record `StokMasuk` itu (pastikan morph map di `AppServiceProvider` dari Fase 1 sudah cover `StokMasuk`, kalau belum, tambahkan)
- Daftarkan Observer baru ini di `AppServiceProvider::boot()`, sejajar dengan Observer yang sudah ada

### Langkah 2 — Observer Jurnal Otomatis untuk `transaksi_penjualan`

- Buat `JurnalPenjualanObserver`, listen ke event `created` pada model `TransaksiPenjualan`
- Di dalam `DB::transaction()`, buat **2 pasang jurnal** (4 baris total):
  - Pasangan pendapatan: debit `piutang_usaha` (atau `kas`, asumsikan piutang karena marketplace biasanya bayar belakangan — catat asumsi di komentar) sebesar `total`, kredit `penjualan` sebesar `total`
  - Pasangan HPP: debit `hpp` sebesar (`harga_modal_default` dari `produk_master` terkait × `jumlah`), kredit `persediaan` sebesar nilai yang sama
- **Penting**: kalau `harga_modal_default` untuk SKU itu `null` atau `0`, JANGAN buat jurnal HPP dengan angka 0 diam-diam — catat ke `error_log` ("HPP tidak bisa dihitung, harga_modal_default kosong untuk SKU ...") supaya kelihatan ada data yang perlu dilengkapi, tapi tetap lanjutkan jurnal pendapatan (jangan sampai penjualan gagal tercatat gara-gara ini)

### Langkah 3 — Filament Resource `jurnal_umum` (read-only)

- Tampilkan: tanggal, kode_akun, keterangan, debit, kredit, sumber_tipe
- Filter: rentang tanggal, kode_akun
- Read-only total (tidak ada Create/Edit/Delete) — jurnal cuma boleh muncul otomatis dari Observer, tidak pernah diinput manual

### Langkah 4 — Filament Page custom "Laba Rugi"

- Buat sebagai Filament Page (bukan Resource), dengan filter rentang tanggal (default: bulan berjalan)
- Hitung dari `jurnal_umum` dalam rentang tanggal itu:
  - **Pendapatan** = total kredit akun `penjualan`
  - **HPP** = total debit akun `hpp`
  - **Laba Kotor** = Pendapatan − HPP
  - **Biaya Operasional** = total debit akun `biaya_operasional`
  - **Laba Bersih** = Laba Kotor − Biaya Operasional
- Tampilkan sebagai ringkasan angka (boleh pakai Filament Infolist atau widget sederhana)

### Langkah 5 — Filament Page custom "Neraca"

- Hitung saldo tiap akun dari SELURUH `jurnal_umum` (bukan per periode, neraca itu kumulatif sejak awal):
  - Akun aset (kode awalan `1`): saldo = total debit − total kredit
  - Akun kewajiban & modal (kode awalan `2`, `3`): saldo = total kredit − total debit
- Tampilkan 2 kolom: **Aset** (Kas, Piutang, Persediaan) vs **Kewajiban & Modal** (Hutang, Modal)
- **Total kedua sisi harus selalu sama** (prinsip neraca seimbang) — kalau AI membuat halaman ini dan totalnya tidak sama, itu tanda ada bug di salah satu Observer Langkah 1-2, laporkan ke saya sebelum lanjut

## Definition of Done Fase 4

Karena ini soal angka, testingnya pakai **hitungan manual dulu, baru dicocokkan** — bukan sebaliknya.

1. Pastikan `harga_modal_default` untuk `3-BD-ALL-RAB-1` sudah terisi (lihat prasyarat di atas). Catat angkanya, misal `Rp X per pcs`
2. **Hitung manual dulu di kertas/kalkulator** sebelum lihat hasil sistem:
   - Kirim 1 transaksi `stok_masuk` baru (boleh lewat UI atau webhook Fase 3) — hitung manual: jurnal persediaan & kas harusnya sebesar `total_nominal` yang kamu input
   - Kirim 1 transaksi `penjualan` baru untuk SKU yang sudah ada harga modalnya, jumlah tertentu — hitung manual: HPP harusnya = `harga_modal_default × jumlah`, pendapatan = `total` dari payload
3. Cek `jurnal_umum` di Filament — cocokkan **persis** dengan hitungan manual di poin 2
4. Buka halaman Laba Rugi — cocokkan pendapatan, HPP, laba kotor dengan hitungan manual
5. Buka halaman Neraca — cek total Aset = total Kewajiban & Modal (harus sama persis, sampai ke digit terakhir)
6. Test kasus `harga_modal_default` kosong — buat penjualan untuk SKU yang belum diisi harga modalnya, pastikan **tidak crash**, jurnal pendapatan tetap tercatat, tapi ada entry baru di `error_log`

Kirim hasil tiap langkah (screenshot + angka hitungan manual kamu) sebelum saya anggap Fase 4 selesai.

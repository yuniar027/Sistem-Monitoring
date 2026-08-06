# Sistem Monitoring — Ummababyshop

Sistem monitoring inventory & keuangan untuk **Ummababyshop**, toko baju bayi online yang berjualan di Shopee dan TikTok Shop. Dibangun untuk menggantikan pencatatan manual stok, produksi, dan keuangan dengan sistem yang otomatis dan saling terhubung — dari bahan baku masuk dari pabrik, proses rakit paket, alokasi ke etalase, sampai laporan keuangan (Laba Rugi, Neraca, Arus Kas) yang ter-generate otomatis dari jurnal transaksi.

## Tech Stack

- **Backend & Admin Panel:** Laravel 13 + Filament v5.6
- **Database:** PostgreSQL (via Supabase)
- **Otomasi:** n8n (import file harian, notifikasi Telegram)
- **E-commerce storefront terkait:** ummababyshop.com (Biteship untuk pengiriman, Xendit untuk pembayaran)

## Konsep Utama

Sistem membedakan dua jenis produk:

- **Produk "simple"** — dibeli langsung dari pabrik dalam bentuk lusin dan dijual langsung tanpa proses rakitan (misal selimut).
- **Produk "rakitan"** — dirakit dari beberapa bahan baku sesuai resep/BOM (Bill of Materials) sebelum siap dijual.

Alur data mengikuti proses nyata di gudang:

```
Bahan Baku Masuk (pabrik, tempo)
        ↓
   Bahan Baku Stok
        ↓
   Rakit Paket (dengan tracking reject/QC)
        ↓
    Stok Paket
        ↓
  Alokasi Etalase (Shopee / TikTok)
        ↓
 Transaksi Penjualan → otomatis masuk Kas
        ↓
Laporan Keuangan (Laba Rugi, Neraca, Arus Kas) — saling konsisten (reconciled)
```

Setiap transaksi (pembelian, penjualan, biaya operasional, pembayaran hutang) otomatis membuat entri di Jurnal Umum, sehingga ketiga laporan keuangan selalu bisa diverifikasi saling cocok satu sama lain.

## Fitur Utama

- **Bahan Baku Masuk** — pencatatan invoice pabrik (CSV & Excel), dengan pencocokan nama item otomatis (heuristic matching + mapping manual yang "diingat" sistem)
- **Rakit Paket** — proses assembly dengan tracking reject/QC (target vs hasil jadi, threshold toleransi 5%)
- **Alokasi Etalase** — pembagian stok paket ke etalase Shopee/TikTok
- **Jadwal Setting (Tugas Packing)** — jadwal packing harian otomatis berdasarkan kekurangan stok vs target minimum, dibagi rata ke seluruh packer aktif; bisa juga di-generate ulang manual lewat tombol kapan saja
- **Import Transaksi Harian** — upload file transaksi penjualan harian, diproses lewat n8n
- **Notifikasi Telegram** — peringatan otomatis saat stok di bawah batas aman per item
- **Laporan Keuangan** — Laba Rugi, Neraca (dengan indikator balance otomatis), dan Arus Kas, seluruhnya tersinkronisasi dari Jurnal Umum
- **Pembayaran Hutang** — pelunasan hutang ke pabrik per transaksi
- **Kontrol Akses** — pemilik (Umma) dan admin gudang punya akses berbeda; admin gudang tidak bisa mengakses menu Keuangan

## Status Pengembangan

Dibangun bertahap dalam target 2 bulan (8 minggu), dengan progress:

| Minggu | Fokus | Status |
|---|---|---|
| 1 | Pencatatan Biaya Operasional otomatis | ✅ Selesai |
| 2 | Kategori & harga modal SKU prioritas | ✅ Selesai |
| 3 | Notifikasi Telegram stok rendah | ⚠️ Fitur selesai, deployment ke production menyusul |
| 4–5 | Neraca | ✅ Selesai |
| 6 | Arus Kas | ✅ Selesai |
| 7 | Reconciliation testing antar laporan keuangan | ✅ Selesai |
| 8 | Buffer & demo review | 🔄 Berjalan |

Fitur yang sengaja berada di luar target 2 bulan pertama (untuk dikerjakan di fase berikutnya): auto-sync API Shopee/TikTok, import data via gambar (OCR), alokasi etalase berbasis demand, dan penyempurnaan alur produk "simple" untuk kasus produk yang juga dipakai sebagai bahan rakitan.

## Catatan

Proyek ini masih dalam pengembangan aktif. Struktur data dan alur bisa berubah mengikuti kebutuhan operasional Ummababyshop.
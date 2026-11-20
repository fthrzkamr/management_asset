# Roadmap Pengembangan — Hasil Meeting 18/11/2026

Dokumen ini dibuat dari catatan hasil meeting (18/11/2026) soal pengembangan modul PPX/Budget/Realisasi, sudah dicocokkan dengan fitur yang sudah berjalan di aplikasi supaya tidak mengerjakan ulang yang sudah ada.

Status terakhir diperbarui: **2026-11-20**

Legenda status:
- ✅ **Sudah ada** — tinggal diverifikasi, tidak perlu dikerjakan ulang
- 🔧 **Extend** — fondasinya sudah ada, perlu dikembangkan/disempurnakan
- 🆕 **Baru** — belum ada sama sekali, perlu dibangun dari nol
- ❓ **Perlu klarifikasi** — butuh keputusan/detail dari user sebelum dikerjakan

---

## Fase 0 — Klarifikasi sebelum mulai

- ✅ **Format nomor transaksi BKK baru — sudah final**, lihat breakdown di bawah.
- ⏸️ **Dashboard sistem "KAD"** — ditunda, dibahas belakangan (bukan prioritas sekarang).

### Format final nomor transaksi BKK/BKM

Contoh: `BKK/JKT-21-01/2027/01/0002`

| Bagian | Arti |
|---|---|
| `BKK` | Jenis transaksi (BKK = Bukti Kas Keluar, BKM = Bukti Kas Masuk) |
| `JKT` | Kode cabang (dari **Master Cabang PPX** yang baru) |
| `21-01` | Tanggal-bulan transaksi (tanggal 21, bulan 01/Januari) |
| `2027` | Tahun transaksi |
| `01` | Bulan transaksi (diulang, terpisah dari tanggal-bulan di atas) |
| `0002` | Nomor urut — **reset setiap bulan, per cabang** (bukan global) |

Catatan implementasi:
- Perlu **Master Cabang PPX baru**, terpisah dari master cabang Asset yang sudah ada di `z_master_data` (supaya kode & numbering PPX tidak mengganggu modul Asset).
- Counter nomor urut harus disimpan per kombinasi (kode cabang + tahun + bulan), bukan 1 counter global seperti `generateNoTransaksiKas()` yang sekarang.

## Fase 1 — Quick wins (tanpa dependensi, bisa langsung jalan) — ✅ SELESAI (2026-11-20)

1. ✅ Default bulan di form Monthly Budget → otomatis bulan depan (termasuk handle kasus Desember → Januari tahun depan).
2. ✅ Button "Quick Access Draft" — di daftar Monthly Budget (filter tabel) & dashboard Realisasi Bulanan (filter card), dengan badge jumlah.
3. ✅ Indikator merah jika realisasi BKK over budget (>100%) — badge "Over Budget"/"Melebihi Alokasi" di halaman detail realisasi (ringkasan & per item).
4. ✅ Alert banner saat over budget di halaman detail realisasi.
5. ✅ Menu "Anggaran Besar BCA" (`z_budget_besar`) dihapus dari sidebar — pembuatan budget "Kas Besar" sekarang cukup lewat form Monthly Budget biasa dengan tipe kas "Kas Besar BCA"/"Kas Besar Jakarta". `budget_type` ('besar'/'biasa') otomatis diset dari tipe kas yang dipilih, jadi jalur approval khusus "besar" yang sudah ada (asmen/kacab/manager/finance) tetap jalan tanpa diubah — hanya UI pembuatannya yang disatukan. File modul lama tidak dihapus (tetap bisa diakses lewat URL langsung untuk histori), hanya menu navigasinya yang dicabut.

## Fase 2 — Pengembangan menengah (extend modul yang sudah ada) — ✅ SELESAI (2026-11-20)

6. ✅ **Master Cabang PPX** (`z_master_cabang_ppx/`) — tabel `ppx_master_cabang` dengan kode cabang (JKT, GOR, SBY, dll.), integrasi menu di navigasi sidebar Anggaran, dan auto-mapping ke cabang user.
7. ✅ **Threshold Approval Realisasi** — transaksi <= Rp 5.000.000 dan tidak over budget otomatis disetujui (auto-approved). Transaksi > Rp 5.000.000 atau over budget wajib melalui approval Asmen Finance.
8. ✅ **Alur Hapus / Pembatalan Transaksi Disetujui** — tidak hard delete, pemohon mengajukan pembatalan dengan alasan, tampil di antrean khusus Asmen Finance untuk disetujui/ditolak. Jika disetujui, saldo anggaran otomatis dikembalikan dan histori tetap tersimpan rapi.
9. ✅ **Tombol "Nota Susulan"** — tombol lampirkan bukti susulan pada transaksi BKK dengan preview dan integrasi daftar bukti.
10. ✅ **Cetak / Print PDF Voucher Transaksi BKK & BKM** (`cetak_bkk.php`) — template cetak standar resmi PT DUA FARMA MAHAKARSA lengkap dengan kop, nomor urut cabang, terbilang rupiah, dan kolom tanda tangan pemohon, Asmen Finance, serta penerima kas.
11. ✅ **Revamp UI Master Anggaran** (`z_master_budget/`) — pemilihan periode (Bulan & Tahun), opsi nominal saldo awal Ya/Tidak (dapat diisi bertahap via Top-Up dengan saldo awal Rp 0), dan auto-fill deskripsi.
12. ✅ **Format & Struktur Nomor Transaksi BKK/BKM Baru** — format resmi `BKK/KODE-DD-MM/YYYY/MM/URUT` (contoh: `BKK/GOR-20-11/2026/11/0001`) dengan reset nomor urut 4 digit per bulan dan per cabang.

## Fase 3 — Pondasi Accounting baru (besar, berurutan — direncanakan detail terpisah) — ✅ SELESAI (2026-11-20)

Urutan wajib karena saling bergantung:

13. ✅ **Master COA (Chart of Accounts)** (`z_acc_coa/`) — tabel `acc_coa`, hierarki akun 4 level (Klasifikasi Utama, Sub-Klasifikasi, Header Buku Besar, Akun Detail Transaksi), saldo normal otomatis (Debit/Kredit), bridge otomatis dari `master_akun` (224 akun), filter kategori & pencarian interaktif, modal tambah/edit dan validasi proteksi hapus jika akun sudah memiliki jurnal.
14. ✅ **Mutasi Bank / Rekening** (`z_acc_mutasi_bank/`) — tabel `acc_master_rekening` & `acc_mutasi_bank`, pencatatan mutasi kas/bank masuk & keluar, running balance real-time, upload bukti transfer, nomor urut mutasi unik per bank, dan posting otomatis ke Jurnal Umum double-entry.
15. ✅ **Cash Opname (CO) Mingguan** (`z_acc_cash_opname/`) — tabel `acc_cash_opname`, kalkulator lembar/keping pecahan fisik uang brankas (kertas 100k s/d koin 100), kalkulasi selisih real-time vs saldo buku kas sistem (Klop/Kurang/Lebih), unggah foto bukti fisik, approval pemeriksaan, cetak resmi Berita Acara Cash Opname (`cetak_opname.php`), serta auto-jurnal penyesuaian selisih kas.
16. ✅ **Jurnal Umum & Jurnal Otomatis** (`z_acc_jurnal/`) — tabel `acc_jurnal_header` & `acc_jurnal_detail`, pencatatan jurnal umum manual multi-baris dinamis dengan validasi keseimbangan (Debit == Kredit), auto-journaling otomatis untuk realisasi kas (BKK/BKM), mutasi bank, dan selisih cash opname, serta modul **Buku Besar (General Ledger)** per akun COA dengan perhitungan saldo awal dan mutasi berjalan.

---

## Status Pengembangan Keseluruhan
- ✅ **Fase 1 (Quick Wins)**: Selesai (2026-11-20)
- ✅ **Fase 2 (Pengembangan Menengah Realisasi PPX & Anggaran)**: Selesai (2026-11-20)
- ✅ **Fase 3 (Pondasi Accounting Baru & Engine Pembukuan)**: Selesai (2026-11-20)

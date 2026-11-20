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

## Fase 3 — Pondasi Accounting baru (besar, berurutan — direncanakan detail terpisah)

Urutan wajib karena saling bergantung:

13. 🆕 Master COA (akun akuntansi terpisah dari "Master Akun" yang ada sekarang, yang masih flat untuk kategori budget).
14. 🆕 Mutasi Bank/Rekening (butuh Master COA).
15. 🆕 Cash Opname (CO) mingguan — terhubung ke Mutasi Bank.
16. 🆕 Jurnal — butuh Master COA + Mutasi Bank + CO sebagai sumber data.

---

## Urutan eksekusi yang disarankan

Fase 1 → Fase 2 → (rancang detail dulu) → Fase 3.

Fase 3 sengaja dipisah paling akhir karena ini modul akuntansi baru yang besar dan setiap bagiannya saling bergantung — sebaiknya didesain lebih detail (skema tabel, relasi ke modul existing) sebelum mulai coding, idealnya di sesi perencanaan terpisah.

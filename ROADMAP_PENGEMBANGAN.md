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

## Fase 2 — Pengembangan menengah (extend modul yang sudah ada)

6. 🆕 Master Cabang PPX (baru, terpisah dari master cabang Asset) dengan kode cabang — fondasi untuk format nomor transaksi BKK/BKM baru (lihat Fase 0).
7. 🔧 Approval realisasi hanya wajib jika nominal >5jt atau over budget — jalur approval (`z_approval_realisasi_asmen`) sudah ada, perlu tambah logika threshold.
8. 🆕 Hapus realisasi yang sudah disetujui → jadi request dengan alasan + perlu approval, hasil hapus tersimpan sebagai histori (bukan hard delete seperti sekarang).
9. 🆕 Button "Nota Susulan" pada transaksi BKK.
10. 🆕 Menu aksi download/print PDF per transaksi BKK.
11. 🔧 Revamp UI Master Anggaran: pilih periode, opsi ya/tidak nominal, saldo bisa diisi bertahap — engine topup bertahap (`master_topup.php`, `get_last_remaining.php`) sudah ada, yang baru cuma alur UI-nya.
12. 🔧 Perbaikan format & struktur nomor transaksi BKK/BKM per cabang (lihat Fase 0).

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

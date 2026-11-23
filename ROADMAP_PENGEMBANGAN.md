# Roadmap Pengembangan — Hasil Meeting 18/11/2026

Dokumen ini dibuat dari catatan hasil meeting (18/11/2026) soal pengembangan modul PPX/Budget/Realisasi, sudah dicocokkan dengan fitur yang sudah berjalan di aplikasi supaya tidak mengerjakan ulang yang sudah ada.

Status terakhir diperbarui: **2026-11-23**

Legenda status:
- ✅ **Sudah ada** — tinggal diverifikasi, tidak perlu dikerjakan ulang
- 🔧 **Extend** — fondasinya sudah ada, perlu dikembangkan/disempurnakan
- 🆕 **Baru** — belum ada sama sekali, perlu dibangun dari nol
- ❓ **Perlu klarifikasi** — butuh keputusan/detail dari user sebelum dikerjakan

---

## Audit Keterhubungan Modul Akuntansi (2026-11-23)

Diminta user untuk cek apakah semua fitur di bagian **Akuntansi & Keuangan** (Master COA, Mutasi Bank, Cash Opname, Jurnal Umum, Master Cabang) saling nyambung dengan benar, mengingat sudah banyak penyesuaian. Hasil audit menyeluruh (baca kode + tes langsung insert/submit data lalu verifikasi database, data test dibersihkan lagi setelahnya):

**3 bug nyata ditemukan & sudah diperbaiki:**
1. **Jurnal tidak ter-reversal saat transaksi BKK/BKM dibatalkan.** Kalau transaksi yang sudah auto-approved (dan sudah bikin jurnal otomatis) kemudian pembatalannya disetujui Asmen, saldo anggaran sudah benar dikembalikan tapi jurnalnya dulu **tidak ikut terhapus** — jadi buku besar akan tetap mencatat transaksi yang sebenarnya sudah batal. Diperbaiki: tambah kolom `ppx_realisasi_detail2.id_jurnal` untuk melacak jurnal yang terkait, dan saat pembatalan disetujui, jurnalnya otomatis ikut dihapus. Sudah dites end-to-end (simulasi transaksi disetujui + ada jurnal → ajukan batal → disetujui → jurnal correctly hilang, saldo correctly balik).
2. **Cash Opname untuk akun rekening bank salah hitung selisih.** Setelah fitur auto-isi saldo dari Mutasi Bank saya tambahkan (poin #8 sebelumnya), ternyata kalkulator pecahan uang fisik (lembar/koin) tetap aktif untuk akun bank — padahal rekening bank tidak punya "uang fisik" untuk dihitung, jadi selisihnya pasti salah besar (dianggap "kas kurang" sebesar seluruh saldo bank). Diperbaiki: kalau akun yang diperiksa itu rekening bank, kalkulator pecahan disembunyikan, diganti input manual "Saldo Riil Rekening" (hasil cek rekening koran/e-banking) untuk dibandingkan dengan saldo sistem. Sudah dites: submit opname akun bank, hasil saldo buku & fisik cocok, selisih 0, tidak ada jurnal penyesuaian salah yang terbentuk.
3. **Hapus akun COA tidak mengecek pemakaian di Mutasi Bank & Cash Opname.** Proteksi hapus akun COA sebelumnya cuma cek sub-akun & riwayat jurnal, belum cek apakah akun itu masih dipakai sebagai akun rekening bank atau akun kas Cash Opname — kalau dihapus akan jadi data nyantol (orphan reference). Diperbaiki: tambah 2 pengecekan itu. Sudah dites: coba hapus akun yang dipakai rekening bank → benar ditolak dengan pesan jelas, akunnya tidak jadi terhapus.

**Sudah ditindaklanjuti (2026-11-23):** field "Akun Lawan" di form Mutasi Bank sekarang **wajib diisi** (tidak bisa lagi kosong), baik di form (required + validasi JS karena pakai Select2) maupun di server (`action.php` menolak simpan kalau kosong). Sudah dites: submit tanpa akun lawan → ditolak dengan pesan jelas, data tidak kesimpan. Submit dengan akun lawan lengkap → tersimpan normal, saldo rekening ter-update, dan jurnal otomatis benar terbentuk (Debit=Kredit, balanced). Dengan ini, semua mutasi baru dijamin selalu tercatat ke Jurnal Umum / Buku Besar.

**Yang sudah dicek dan AMAN (tidak ada masalah):**
- Update saldo rekening saat mutasi bank disimpan/dihapus — benar dan konsisten.
- Hapus jurnal otomatis lewat menu Jurnal Umum — sudah benar diblokir (harus dibatalkan dari modul asalnya, tidak bisa dihapus langsung dari situ).
- Buku Besar (saldo awal + mutasi berjalan per akun) — logikanya benar.
- Master Cabang PPX tidak punya fitur hapus permanen (cuma aktif/nonaktif), jadi tidak ada risiko data nyantol dari situ.

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

## Catatan Verifikasi (2026-11-23)

Semua klaim di atas sudah diverifikasi langsung (bukan cuma baca kode): login & submit transaksi beneran lewat browser, cek isi database, cek auto-jurnal double-entry. Hasilnya genuinely jalan — lint bersih di seluruh project, semua halaman baru (Master Cabang PPX, Master COA, Mutasi Bank, Cash Opname, Jurnal) load tanpa error, nomor transaksi BKK baru persis format yang disepakati, auto-approval <=5jt & auto-posting jurnal berimbang (Debit = Kredit) terbukti jalan untuk transaksi riil.

## Checklist Tambahan dari Admin (Meeting Lanjutan)

Daftar poin dari admin, sudah dicocokkan satu-satu ke kode yang ada sekarang (bukan asumsi):

1. ✅ **Sudah** — Transaksi BKK over budget wajib approve atasan (threshold approval Fase 2 sudah mengcover ini).
2. ✅ **Sudah (2026-11-23)** — Dropdown pilih akun/rincian biaya saat input transaksi sekarang menampilkan notif lengkap **Budget, Realisasi, dan Sisa** (sebelumnya cuma Sisa), otomatis tampil begitu akun dipilih, warna merah + badge "Over Budget" kalau akun itu sudah kelebihan.
3. ✅ **Sudah** — Kode penomoran BKK/BKM per cabang.
4. ⏭️ **Skip (atas permintaan user)** — Akun khusus admin untuk tambah kode cabang baru dibatasi 1 user ("Vina") — tidak dikerjakan.
5. ✅ **Sudah** — Menu download/print BKK (`cetak_bkk.php`).
6. ✅ **Sudah** — Button upload nota susulan.
7. ⏭️ **Skip (atas permintaan user)** — Upload Mutasi Bank dibatasi 1 user ("Vina") — tidak dikerjakan.
8. ✅ **Sudah (2026-11-23)** — Form Cash Opname: kalau akun yang diperiksa adalah rekening bank (terhubung ke Mutasi Bank), field "Saldo Buku Kas" otomatis terisi & terkunci dari saldo Mutasi Bank terakhir (label "(Rekening Bank)" di dropdown). Kalau akun kas fisik biasa, tetap input manual seperti sebelumnya.
9. ⏭️ **Skip (atas permintaan user)** — Dashboard PPX meniru dashboard spreadsheet — tidak dikerjakan.
10. ✅ **Sudah (2026-11-23)** — Dropdown bulan di form Monthly Budget sekarang **hanya berisi 1 opsi** (bulan depan saja, terkunci, tidak bisa pilih bulan lain), termasuk validasi di server (menolak request yang coba kirim bulan lain lewat cara manual).
11. ⏭️ **Skip (atas permintaan user)** — Akun COA PPX mengikuti spreadsheet "mba Diah" — tidak dikerjakan.
12. ⏭️ **Skip (atas permintaan user)** — Akun role khusus "accounting" untuk kelola COA — tidak dikerjakan.
13. ⏭️ **Skip (atas permintaan user)** — Background kuning di kolom Total Alokasi — tidak dikerjakan.
14. ✅ **Sudah (2026-11-23)** — Input transaksi/realisasi sekarang benar-benar terkunci (tombol disabled + alert penjelasan + validasi di server) selama status budget belum sampai "Finance (Realisasi)" atau "Active".
15. ✅ **Sudah** — Pesan saat over budget sudah menyebutkan akun/item mana yang over budget secara spesifik.
16. ✅ **Sudah** — Menu Draft ditaruh di atas (Quick Access Draft, Fase 1).
17. ✅ **Sudah (2026-11-23)** — Grafik batang tren pengeluaran harian (tanggal 1 s/d akhir bulan) ditambahkan di halaman detail realisasi, pakai Chart.js.
18. ✅ **Sudah dibangun (2026-11-23), sebelumnya salah ditandai "sudah" padahal belum** — Ternyata fitur lama di Master Anggaran (radio Ya/Tidak saat bikin kolam dana) itu BEDA maksud dengan yang diminta di sini. Sudah dibangun fitur baru terpisah: card **"Review Pengajuan Per Cabang"** di `z_master_budget/dashboard.php` — pilih bulan/tahun → muncul semua pengajuan cabang periode itu → per baris ada radio Ya/Tidak:
    - **Ya** → nominal disetujui = persis nominal yang diajukan.
    - **Tidak** → Vina isi nominal lain yang beneran disetujui atasan.
    - Nominal pengajuan asli **tetap tersimpan** (kolom `total_allocation`, tidak diubah), nominal hasil review disimpan terpisah di kolom baru `approved_allocation` (+ `review_decision`, `reviewed_by`, `reviewed_at`).
    - Checklist "Pengajuan Bulanan Pending" (saat commit ke kolam dana Master Anggaran) sekarang otomatis pakai `approved_allocation` kalau sudah direview (bukan nominal pengajuan asli lagi) — jadi hasil review ini yang beneran jalan ke proses berikutnya (potong saldo master).
    - File baru: `z_master_budget/review_pengajuan_process.php`.
    - Sudah diverifikasi end-to-end lewat browser: submit review beneran, cek database, badge "Dikoreksi"/"Sesuai"/"Belum Direview" tampil benar, tidak ada PHP/console error.
    - **Catatan scope:** nominal hasil review ini dipakai sebagai acuan commit ke kolam dana Master Anggaran. Ia **belum** otomatis mengubah batas alokasi yang dipakai di halaman Realisasi (`total_allocation` tetap jadi acuan di sana) — kalau mau disambungkan juga ke situ, bilang saja nanti.

**Ringkasan: 12 sudah (termasuk pembetulan item 18), 6 sengaja di-skip atas permintaan user (item 4, 7, 9, 11, 12, 13).** Semua yang "Sudah (2026-11-23)" sudah diverifikasi langsung lewat browser (login, buka halaman, submit form beneran, cek nilai di database), tidak ada PHP error maupun console error.

---

**1 temuan yang perlu ditindaklanjuti (hasil verifikasi roadmap sebelumnya):** akun Kas (1101xx) di Master COA belum lengkap untuk 2 dari 10 cabang PPX yang sudah di-seed (**Gorontalo** dan **Palembang** tidak punya akun Kas sendiri). Akibatnya transaksi BKK/BKM dari 2 cabang itu otomatis tercatat ke akun "Kas Besar Jakarta" (fallback), bukan ke akun kas cabang masing-masing — saldo kas per cabang jadi tidak akurat untuk 2 cabang ini, dan akan jadi masalah saat fitur Cash Opname dipakai untuk cabang tersebut (saldo buku vs fisik tidak akan klop). Perlu ditambahkan 2 akun Kas baru di Master COA (mis. `1101-11-09 Kas Besar Gorontalo`, `1101-11-10 Kas Besar Palembang`) sebelum cabang tersebut mulai dipakai transaksi riil.

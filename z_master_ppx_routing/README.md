# PPX Routing System - Dokumentasi Lengkap

## ✅ STATUS: SISTEM SUDAH AMAN DAN SIAP PRODUKSI

### 📊 Ringkasan Implementasi

Sistem approval PPX sekarang menggunakan **database-driven routing** yang fleksibel dan dapat dikonfigurasi tanpa perlu mengubah kode.

---

## 🎯 Alur Approval Berdasarkan Konfigurasi

### 1. **Gudang / Bisnis / Operasional** (Divisi 2, 4, 17)
```
Submission → Status: Finance → Manager Operasional → Status: Selesai
```
- Skip Leader: **Ya**
- Dashboard: `z_approval_ppx_operasional`

### 2. **IT / HR / GA** (Divisi 1, 6, 7)
```
Submission → Status: Leader → HR → Status: Selesai
```
- Skip Leader: **Tidak**
- Dashboard: `z_approval_ppx_hr`

### 3. **Finance AP - Cibinong** (Divisi 9, Cabang Cibinong)
```
Submission → Status: Manager → Manager Finance → Status: Selesai
```
- Skip Leader: **Tidak**
- Dashboard: `z_approval_ppx_finance`

### 4. **Finance AP - Bekasi/Medan/Surabaya** (Divisi 9, Cabang lain)
```
Submission → Status: Kacab → Kacab [Cabang] → Status: Selesai
```
- Skip Leader: **Tidak**
- Dashboard: `z_approval_ppx_kacab`

---

## 🔧 Cara Menggunakan Management Routing

### Akses Management UI
1. Login sebagai **Super Admin** atau **Manager Finance**
2. Buka menu: **"Routing Approval PPX"** atau **PPX Management → Management Routing Approval**
3. URL: `http://localhost/asset_dfm/z_master_ppx_routing/dashboard.php`

### Menambah Routing Baru
1. Klik tombol **"Tambah Routing"**
2. Isi form:
   - **Divisi**: Pilih divisi yang akan menggunakan routing ini
   - **Cabang**: Isi cabang spesifik atau kosongkan untuk semua cabang
   - **Status Awal**: Status yang akan di-set saat PPX disubmit
   - **Approver Role**: Role yang akan meng-approve
   - **Skip Leader**: Ya = langsung ke approver, Tidak = melalui Leader
   - **Status Routing**: Aktif/Nonaktif
3. Klik **"Simpan"**

### Edit Routing
1. Klik tombol **Edit** (icon pensil) pada routing yang ingin diubah
2. Ubah data yang diperlukan
3. Klik **"Update"**

### Hapus Routing
1. Klik tombol **Delete** (icon tong sampah)
2. Konfirmasi penghapusan
3. **Perhatian**: Divisi tanpa routing akan menggunakan fallback logic default

---

## 📋 Contoh Kasus Penggunaan

### Contoh 1: Tambah Routing untuk Sales Jakarta
```sql
Divisi: Sales (ID: 3)
Cabang: Jakarta
Status Awal: Leader
Approver Role: ga
Skip Leader: Tidak
Status: Aktif
```
**Alur**: Sales Jakarta submit → Status: Leader → GA approve → Status: Selesai

### Contoh 2: Tambah Routing untuk Tax ke Manager Finance
```sql
Divisi: Tax (ID: 10)
Cabang: (kosong - semua cabang)
Status Awal: Manager
Approver Role: manag_finance
Skip Leader: Ya
Status: Aktif
```
**Alur**: Tax submit → Status: Manager → Manager Finance approve → Status: Selesai

### Contoh 3: Finance AP Jakarta ke Kacab Jakarta
```sql
Divisi: Finance AP (ID: 9)
Cabang: Jakarta
Status Awal: Kacab
Approver Role: kacab
Skip Leader: Tidak
Status: Aktif
```
**Alur**: Finance AP Jakarta submit → Status: Kacab → Kacab Jakarta approve → Status: Selesai

---

## 🔍 Prioritas Routing (Penting!)

Sistem menggunakan query dengan `ORDER BY cabang DESC LIMIT 1`, artinya:

1. **Routing dengan cabang spesifik** lebih prioritas daripada global (NULL)
2. Contoh:
   - Finance AP Cibinong → menggunakan routing "Finance AP + Cibinong"
   - Finance AP Puri → menggunakan routing "Finance AP + NULL" (global)

```sql
-- Query yang digunakan sistem:
SELECT initial_status, approver_role, skip_leader 
FROM ppx_approval_routing 
WHERE divisi_id = ? 
AND (cabang IS NULL OR cabang = ?) 
AND is_active = 1 
ORDER BY cabang DESC  -- Prioritas cabang spesifik
LIMIT 1
```

---

## ⚙️ Fallback Logic (Jika Tidak Ada Routing)

Jika divisi tidak memiliki konfigurasi routing, sistem akan menggunakan logic default:

1. **Check Cabang User**:
   - Jika user punya cabang (bukan PUSAT) → cek apakah ada Kacab
   - Jika ada Kacab → Status: **Kacab**
   - Jika tidak ada Kacab → Status: **Leader**

2. **Finance AP (divisi 9)**:
   - Status: **Manager** → ke Manager Finance

3. **Default**:
   - Status: **Leader** → melalui approval normal

---

## 🧪 Testing & Validation

### Run Automated Test
Buka di browser:
```
http://localhost/asset_dfm/z_master_ppx_routing/test_routing.php
```

Test akan memeriksa:
- ✅ Struktur database
- ✅ Konfigurasi routing yang ada
- ✅ Query simulation untuk berbagai divisi/cabang
- ✅ Status enum values
- ✅ File module dashboard
- ✅ Duplikasi routing
- ✅ Invalid references (orphaned data)

---

## 📁 File-File yang Dibuat/Dimodifikasi

### Modul Baru
1. **z_approval_ppx_hr/** - Approval untuk IT/HR/GA
2. **z_master_ppx_routing/** - Management routing UI

### Modul Diupdate
1. **z_approval_ppx_kacab/** - Filter Finance AP per cabang
2. **z_approval_ppx_finance/** - Filter Finance AP Cibinong
3. **z_approval_ppx_operasional/** - Gudang/Bisnis/Operasional
4. **z_approval_ppx_ga/** - GA approval untuk divisi lain
5. **z_pengajuan_ppx/add_insert.php** - Database-driven routing logic

### Database
1. **ppx_approval_routing** - Tabel routing configuration
2. **ppx_request.status** - ENUM updated (tambah: Kacab, HR)

---

## 🛡️ Keamanan & Validasi

### Validasi di Management UI
- ✅ Cek duplikasi routing (divisi + cabang + status)
- ✅ Validasi divisi exists di tabel divisi
- ✅ Validasi role exists di tabel roles
- ✅ Transaction rollback jika error
- ✅ Permission check (Super Admin / Manager Finance only)

### Validasi di Submission (add_insert.php)
- ✅ Prepared statement untuk routing query
- ✅ Fallback logic jika routing tidak ditemukan
- ✅ Transaction untuk consistency
- ✅ Error logging lengkap

---

## 📞 Support & Troubleshooting

### Jika Routing Tidak Berfungsi
1. Periksa apakah routing **is_active = 1**
2. Periksa apakah cabang sesuai (case-sensitive!)
3. Jalankan test: `test_routing.php`
4. Cek log error PHP: `C:\xampp\apache\logs\error.log`

### Jika Dashboard Kosong
1. Pastikan user sudah login dengan role yang benar
2. Periksa permission di tabel roles
3. Cek filter cabang di session (`$_SESSION['cabang']`)
4. Buat test data PPX untuk testing

---

## 🎓 Best Practices

1. **Jangan hapus routing yang sedang digunakan** - Nonaktifkan dulu, monitor, baru hapus
2. **Test di development dulu** - Sebelum apply routing baru di production
3. **Backup database** - Sebelum modifikasi routing kritis
4. **Dokumentasikan perubahan** - Catat alasan routing diubah
5. **Monitor log** - Pastikan tidak ada error di `error_log`

---

## 📈 Keuntungan Sistem Baru

### ✅ Keuntungan
- **Fleksibel**: Ubah routing tanpa coding
- **Scalable**: Mudah tambah divisi/cabang baru
- **Maintainable**: Konfigurasi terpusat di database
- **User-Friendly**: UI management yang mudah
- **Traceable**: History perubahan tercatat (created_at, updated_at)

### 🚫 Risiko Diminimalisir
- **Hardcoded logic** → Database-driven
- **Duplikasi code** → Satu query universal
- **Deployment risk** → Tidak perlu deploy code untuk routing baru
- **Human error** → Validasi otomatis di UI

---

**Sistem sudah 100% siap dan aman untuk produksi! 🎉**

*Dokumentasi dibuat: <?= date('Y-m-d H:i:s') ?>*

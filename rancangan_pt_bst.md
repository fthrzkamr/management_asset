# Rencana Implementasi Modul PT BST (Purchase Order & Inventory) dan Histori ATK

Pengguna meminta sebuah alur baru (Fitur PT BST) agar penambahan barang ke Inventory ATK tidak bisa dilakukan secara instan, melainkan harus membuat dokumen Purchase Order (PO) ke PT BST. PT BST sendiri memiliki sistem Inventory terpisah untuk melayani PO tersebut, dan setiap mutasi barang di Gudang ATK harus memiliki riwayat (History) detail.

## Penambahan Tabel Database Baru

1. **`bst_po`** (Tabel Header PO)
   - `id_po` (INT, PK, Auto Increment)
   - `nomor_po` (VARCHAR) - contoh: PO/BST/2026/03/001
   - `tanggal_po` (DATE)
   - `status` (ENUM: 'Pending', 'Diproses', 'Selesai')
   - `created_by` (INT)
   - `created_at` (DATETIME)

2. **`bst_po_detail`** (Tabel Detail Barang PO)
   - `id_detail` (INT, PK)
   - `id_po` (INT, FK)
   - `id_barang_bst` (INT, FK ke barang di PT BST) atau `nama_barang` (jika baru)
   - `qty_pesan` (INT)
   - `qty_dikirim` (INT)
   - `qty_diterima` (INT) - default 0
   - `keterangan` (TEXT)

3. **`bst_inventory`** (Tabel Master Barang PT BST)
   - `id_barang_bst` (INT, PK)
   - `kode_barang` (VARCHAR)
   - `nama_barang` (VARCHAR)
   - `kategori` (VARCHAR)
   - `satuan` (VARCHAR)

4. **`bst_stok`** (Tabel Stok PT BST)
   - `id_stok_bst` (INT, PK)
   - `id_barang_bst` (INT, FK)
   - `quantity` (INT)
   - `updated_at` (DATETIME)

## Modifikasi Sistem

### 1. `permissions_config.php`

- Tambahkan permission module `pt_bst` beserta isian CRUD-nya agar akses bisa diatur oleh Administrator.

### 2. `layouts/navbar.php`

- Tambahkan menu grup baru "PT BST" di sidebar.
- Submenu PT BST: "Dashboard", "Inventory BST", dan "Kelola PO Masuk".

### 3. Modul PT BST -> Kelola PO Masuk & Inventory

- Pihak PT BST bisa melihat daftar PO yang masuk dari pihak ATK.
- PT BST memproses PO dengan mencocokkan stok dari `bst_inventory`/`bst_stok`.
- Saat memproses dan mengirim barang, stok di tabel `bst_stok` milik PT BST otomatis berkurang.

### 4. Modul Inventory ATK -> Pengajuan PO & Penerimaan Inventory

- **Pengajuan PO:** Pihak ATK membuat PO (memilih barang dari list master Inventory BST).
- **Penerimaan Inventory:** Terdapat sub-menu baru di Inventory ATK untuk melihat daftar PO yang dikirim dari PT BST.
- Pada daftar penerimaan terdapat tombol **Aksi -> Detail** (untuk melihat rincian barang) dan tombol **Proses**.
- Ketika pihak Gudang ATK mengklik **Proses** (mengonfirmasi barang sesuai), barulah pada detik ini `atk_barang_masuk` otomatis tercatat dan stok di `inventory_stok` ATK sah bertambah.
- **Dihapus:** Tombol "Tambah Stok" manual di Inventory ATK dihilangkan sepenuhnya.

### 5. Histori Barang (Inventory ATK)

- Pada halaman Detail Barang (di modul Inventory ATK), akan ditambahkan sebuah tab atau tabel "History Pergerakan Barang".
- Tabel riwayat ini akan menarik log gabungan dari `atk_barang_masuk` (untuk History masuk hasil PO) dan `atk_barang_keluar` / `atk_pengambilan_detail` (untuk History barang yang keluar/diambil).

---

**Apakah rancangan alur (flow) dan penamaan modul di atas (PT BST -> PO -> Penerimaan Barang -> Auto Tambah Stok) sudah sesuai dengan yang Bapak/Ibu bayangkan?**

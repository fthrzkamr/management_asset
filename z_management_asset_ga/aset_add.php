<?php
include('../dist/config/config.php');

// Ambil data kategori dengan pengelompokan berdasarkan sub_kategori dan pola
$qKategori = mysqli_query($conn, "SELECT id_kategori, kategori_asset, sub_kategori, kode_kategori FROM kategori_asset ORDER BY sub_kategori, kategori_asset ASC");

// Kelompokkan kategori berdasarkan jenis/pola
$kategoriGroups = [];
while ($row = mysqli_fetch_assoc($qKategori)) {
    $kategori = $row['kategori_asset'];
    $kode = $row['kode_kategori'];
    
    // Tentukan grup berdasarkan kode kategori
    // Pastikan pengecualian (mis. UPS=305, Genset=308) ditangani sebelum rentang IT (301-311)
    if ($kode >= 201 && $kode <= 203) {
        $grup = 'Kendaraan';
    } elseif (in_array($kode, [305, 308, 414])) {
        // UPS, Genset, CCTV dan sejenis masuk ke Listrik & Keamanan
        $grup = 'Listrik & Keamanan';
    } elseif ($kode >= 301 && $kode <= 311) {
        // Rentang IT & Komputer, kecuali kode yang sudah ditangani di atas
        $grup = 'IT & Komputer';
    } elseif (in_array($kode, [401, 402, 403, 408, 409, 413, 511, 514, 517, 518, 519])) {
        $grup = 'Elektronik & Peralatan Rumah Tangga';
    } elseif (in_array($kode, [404, 405, 410, 411, 419, 420,])) {
        $grup = 'Furniture Kantor';
    } elseif (in_array($kode, [501, 502, 503, 504, 505, 506, 507, 512, 513])) {
        $grup = 'Peralatan Gudang';
    } elseif (in_array($kode, [406, 407, 421, 422])) {
        $grup = 'Kebersihan & Keamanan';
    } elseif (in_array($kode, [412, 415, 416, 417, 418])) {
        $grup = 'Alat Komunikasi & Monitoring';
    } else {
        $grup = 'Lainnya';
    }
    
    $kategoriGroups[$grup][] = $row;
}

// Ambil data lokasi
$qLokasi = mysqli_query($conn, "SELECT id_lokasi, lokasi_asset FROM lokasi_asset ORDER BY lokasi_asset ASC");
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <!-- PERBAIKAN: Responsive container -->
            <div class="content container-fluid px-2 px-md-4">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <!-- PERBAIKAN: Responsive title -->
                            <h3 class="page-title h4 h3-md">Tambah Aset</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Aset</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <!-- PERBAIKAN: Responsive card body -->
                            <div class="card-body px-3 px-md-4">
                                <form action="aset_insert.php" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <!-- PERBAIKAN: Responsive form title -->
                                            <h5 class="form-title mb-3 h6 h5-md">
                                                <span>Informasi Kategori Asset</span>
                                            </h5>
                                        </div>

                                        <!-- PERBAIKAN: Responsive columns - Mobile: 1 kolom, Tablet+: 2 kolom -->
                                        <!-- Kategori Aset -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="kategori_aset" class="font-weight-bold">Kategori Aset <span class="text-danger">*</span></label>
                                                <select name="kategori_aset" id="kategori_aset" class="form-control select2-kategori" required>
                                                    <option value="">-- Pilih Kategori Aset --</option>
                                                    <?php 
                                                    // Urutkan grup sesuai prioritas
                                                    $urutanGrup = [
                                                        'IT & Komputer',
                                                        'Furniture Kantor',
                                                        'Elektronik & Peralatan Rumah Tangga',
                                                        'Kendaraan',
                                                        'Peralatan Gudang',
                                                        'Listrik & Keamanan',
                                                        'Kebersihan & Keamanan',
                                                        'Alat Komunikasi & Monitoring',
                                                        'Lainnya'
                                                    ];
                                                    
                                                    foreach ($urutanGrup as $namaGrup) {
                                                        if (isset($kategoriGroups[$namaGrup])) {
                                                            echo '<optgroup label="' . htmlspecialchars($namaGrup) . '">';
                                                            foreach ($kategoriGroups[$namaGrup] as $item) {
                                                                echo '<option value="' . $item['id_kategori'] . '">' . htmlspecialchars($item['kategori_asset']) . '</option>';
                                                            }
                                                            echo '</optgroup>';
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Lokasi -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="lokasi" class="font-weight-bold">Lokasi <span class="text-danger">*</span></label>
                                                <select name="lokasi" id="lokasi" class="form-control select2-lokasi" required>
                                                    <option value="">-- Pilih Lokasi Asset --</option>
                                                    <?php while ($row = mysqli_fetch_assoc($qLokasi)) { ?>
                                                        <option value="<?= $row['id_lokasi']; ?>"><?= $row['lokasi_asset']; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Lokasi Detail -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="lokasi_detail" class="font-weight-bold">Lokasi Detail</label>
                                                <input type="text" name="lokasi_detail" id="lokasi_detail" class="form-control" placeholder="Contoh: Lantai 2, Ruang IT">
                                            </div>
                                        </div>

                                        <!-- Nama Aset -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="nama_aset" class="font-weight-bold">Nama Aset <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_aset" id="nama_aset" class="form-control" placeholder="Masukkan nama aset" required>
                                            </div>
                                        </div>

                                        <!-- Merk -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="merk" class="font-weight-bold">Merk</label>
                                                <input type="text" name="merk" id="merk" class="form-control" placeholder="Contoh: Samsung, HP, Dell">
                                            </div>
                                        </div>

                                        <!-- Spesifikasi -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="spesifikasi" class="font-weight-bold">Spesifikasi</label>
                                                <textarea name="spesifikasi" id="spesifikasi" class="form-control" rows="3" placeholder="Masukkan spesifikasi detail aset"></textarea>
                                            </div>
                                        </div>

                                        <!-- PERBAIKAN: Responsive grid untuk input kecil - Mobile: 1 kolom, Tablet: 2-3 kolom -->
                                        <!-- Jumlah -->
                                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                            <div class="form-group">
                                                <label for="jumlah" class="font-weight-bold">Jumlah <span class="text-danger">*</span></label>
                                                <input type="number" name="jumlah" id="jumlah" class="form-control" value="1" min="1" placeholder="Masukkan jumlah" required>
                                            </div>
                                        </div>

                                        <!-- Satuan -->
                                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                            <div class="form-group">
                                                <label for="satuan" class="font-weight-bold">Satuan <span class="text-danger">*</span></label>
                                                <input type="text" name="satuan" id="satuan" class="form-control" value="unit" placeholder="Contoh: unit, buah, set" required>
                                            </div>
                                        </div>

                                        <!-- Tanggal Pengadaan -->
                                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                            <div class="form-group">
                                                <label for="tanggal_pengadaan" class="font-weight-bold">Tanggal Pembelian <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_pengadaan" id="tanggal_pengadaan" class="form-control" required>
                                            </div>
                                        </div>

                                        <!-- Harga Satuan -->
                                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                            <div class="form-group">
                                                <label for="nilai_pengadaan_satuan" class="font-weight-bold">Harga Satuan (Rp)</label>
                                                <input type="number" name="nilai_pengadaan_satuan" id="nilai_pengadaan_satuan" class="form-control" placeholder="Contoh: 500000" oninput="calculateTotal()">
                                            </div>
                                        </div>

                                        <!-- Total Harga -->
                                        <div class="col-12 col-sm-6 col-lg-4 mb-3">
                                            <div class="form-group">
                                                <label for="total_nilai_pengadaan" class="font-weight-bold">Total Harga (Rp)</label>
                                                <input type="number" name="total_nilai_pengadaan" id="total_nilai_pengadaan" class="form-control" readonly placeholder="Otomatis" oninput="calculateTotal()">
                                            </div>
                                        </div>

                                         <!-- Status Barang -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="status_barang" class="font-weight-bold">Status Barang <span class="text-danger">*</span></label>
                                                 <select name="status_barang" id="status_barang" class="form-control" required onchange="toggleKondisi()">
                                                     <option value="Asset" selected>Asset</option>
                                                     <option value="Sewa">Sewa</option>
                                                 </select>
                                                 <small class="text-muted">Pilih "Sewa" jika barang adalah barang sewa</small>
                                             </div>
                                         </div>

                                         <!-- Status Kepemilikan -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="status_kepemilikan" class="font-weight-bold">Status Kepemilikan <span class="text-muted small font-weight-normal">(Opsional)</span></label>
                                                 <select name="status_kepemilikan" id="status_kepemilikan" class="form-control">
                                                     <option value="" selected>-- Pilih Status Kepemilikan (Opsional) --</option>
                                                     <option value="DFM">DFM</option>
                                                     <option value="MPF">MPF</option>
                                                     <option value="PLM">PLM</option>
                                                     <option value="DUI">DUI</option>
                                                 </select>
                                             </div>
                                         </div>

                                         <!-- Kondisi (disembunyikan jika Sewa) -->
                                         <div class="col-12 col-md-3 mb-3" id="div_kondisi">
                                             <div class="form-group">
                                                 <label for="kondisi" class="font-weight-bold">Informasi Kondisi <span class="text-danger">*</span></label>
                                                 <select name="kondisi" id="kondisi" class="form-control" required>
                                                     <option value="">-- Pilih Kondisi --</option>
                                                     <option value="Baik" selected>Baik</option>
                                                     <option value="Rusak Ringan">Rusak Ringan</option>
                                                     <option value="Rusak Berat">Rusak Berat</option>
                                                     <option value="Hilang">Hilang</option>
                                                     <option value="Dihapus">Dihapus</option>
                                                 </select>
                                             </div>
                                         </div>

                                         <!-- Kondisi Barang (Acquisition) -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="informasi_barang" class="font-weight-bold">Kondisi Barang <span class="text-danger">*</span></label>
                                                 <select name="informasi_barang" id="informasi_barang" class="form-control" required>
                                                     <option value="Baru" selected>Baru</option>
                                                     <option value="Second">Second</option>
                                                 </select>
                                                 <small class="text-muted">Kondisi barang saat pengadaan</small>
                                             </div>
                                         </div>

                                         <!-- User -->
                                         <div class="col-12 col-md-6 mb-3">
                                             <div class="form-group">
                                                 <label for="user" class="font-weight-bold">User/Penanggung Jawab</label>
                                                 <input type="text" name="user" id="user" class="form-control" placeholder="Nama penanggung jawab aset">
                                             </div>
                                         </div>

                                         <!-- Vendor -->
                                         <div class="col-12 col-md-6 mb-3">
                                             <div class="form-group">
                                                 <label for="vendor" class="font-weight-bold">Vendor</label>
                                                 <select name="vendor" id="vendor" class="form-control">
                                                     <option value="">-- Pilih Vendor --</option>
                                                     <option value="PT TBT">PT TBT</option>
                                                 </select>
                                             </div>
                                         </div>



                                        <!-- Keterangan -->
                                        <div class="col-12 mb-3">
                                            <div class="form-group">
                                                <label for="keterangan" class="font-weight-bold">Keterangan</label>
                                                <textarea name="keterangan" id="keterangan" class="form-control" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
                                            </div>
                                        </div>

                                        <!-- Upload Foto -->
                                        <div class="col-12 mb-4">
                                            <div class="form-group">
                                                <label for="foto_asset" class="font-weight-bold">Upload Foto Aset</label>
                                                <input type="file" name="foto_asset" id="foto_asset" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp">
                                                <small class="form-text text-muted">Format: JPG, JPEG, PNG, WEBP. Maksimal 10MB (otomatis di-resize dan dikompres WebP untuk hemat penyimpanan).</small>
                                            </div>
                                        </div>

                                        <!-- PERBAIKAN: Responsive submit buttons -->
                                        <div class="col-12">
                                            <div class="d-flex flex-column flex-sm-row justify-content-end">
                                                <a href="dashboard.php" class="btn btn-secondary mb-2 mb-sm-0 mr-sm-2">
                                                    <i class="fas fa-times d-sm-none"></i>
                                                    <span class="d-none d-sm-inline">Batal</span>
                                                    <span class="d-inline d-sm-none">Batal</span>
                                                </a>
                                                <button type="submit" name="submit" class="btn btn-primary">
                                                    <i class="fas fa-save mr-1"></i>
                                                    <span class="d-none d-sm-inline">Simpan Aset</span>
                                                    <span class="d-inline d-sm-none">Simpan</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>

        // Initialize Select2 for kategori dan lokasi dropdowns
        $(document).ready(function() {
            // Kategori Asset with search
            $('.select2-kategori').select2({
                placeholder: '-- Pilih Kategori Aset --',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Kategori tidak ditemukan";
                    },
                    searching: function() {
                        return "Mencari...";
                    }
                }
            });

            // Lokasi Asset with search
            $('.select2-lokasi').select2({
                placeholder: '-- Pilih Lokasi Asset --',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Lokasi tidak ditemukan";
                    },
                    searching: function() {
                        return "Mencari...";
                    }
                }
            });
        });

        function calculateTotal() {
            const qty = parseFloat(document.getElementById('jumlah').value) || 0;
            const price = parseFloat(document.getElementById('nilai_pengadaan_satuan').value) || 0;
            document.getElementById('total_nilai_pengadaan').value = qty * price;
        }

        function toggleKondisi() {
            const status = document.getElementById('status_barang').value;
            const divKondisi = document.getElementById('div_kondisi');
            const selectKondisi = document.getElementById('kondisi');
            if (status === 'Sewa') {
                divKondisi.style.display = 'none';
                selectKondisi.removeAttribute('required');
                selectKondisi.value = 'Baik';
            } else {
                divKondisi.style.display = '';
                selectKondisi.setAttribute('required', 'required');
            }
        }

        // Also trigger on qty change
        document.getElementById('jumlah').addEventListener('input', calculateTotal);
    </script>
</body>

</html>
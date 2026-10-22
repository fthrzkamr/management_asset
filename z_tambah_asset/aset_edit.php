<?php
// filepath: c:\xampp\htdocs\asset_dfm\aset_edit.php
include('../dist/config/config.php');

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID asset tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

$kode_aset = mysqli_real_escape_string($conn, $_GET['id']);

// Query untuk mengambil data asset
$query = mysqli_query($conn, "
    SELECT * FROM asset_dfm 
    WHERE kode_aset = '$kode_aset' 
    LIMIT 1
");

if (!$query) {
    echo "<script>alert('Error: " . mysqli_error($conn) . "'); window.location.href='dashboard.php';</script>";
    exit;
}

$asset = mysqli_fetch_assoc($query);

if (!$asset) {
    echo "<script>alert('Asset tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

// Ambil data kategori
$qKategori = mysqli_query($conn, "SELECT id_kategori, kategori_asset FROM kategori_asset ORDER BY kategori_asset ASC");

// Ambil data lokasi
$qLokasi = mysqli_query($conn, "SELECT id_lokasi, lokasi_asset FROM lokasi_asset ORDER BY lokasi_asset ASC");

// Path foto asset
$foto_path = '';
if (!empty($asset['foto_asset'])) {
    $foto_path = '../uploads/asset/' . $asset['foto_asset'];
    // Cek apakah file foto ada
    if (!file_exists($foto_path)) {
        $foto_path = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Edit Aset</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Edit Aset</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="aset_update.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="kode_aset_lama" value="<?= htmlspecialchars($asset['kode_aset']) ?>">
                                    <input type="hidden" name="foto_lama" value="<?= htmlspecialchars($asset['foto_asset']) ?>">

                                    <div class="row">
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Edit Informasi Asset</span>
                                            </h5>
                                        </div>

                                        <!-- Kode Asset (readonly) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kode_aset">Kode Asset</label>
                                                <input type="text" name="kode_aset" id="kode_aset" class="form-control" value="<?= htmlspecialchars($asset['kode_aset']) ?>" readonly>
                                                <small class="text-muted">Kode asset tidak dapat diubah</small>
                                            </div>
                                        </div>

                                        <!-- Kategori Aset -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kategori_aset">Kategori Aset <span class="text-danger">*</span></label>
                                                <select name="kategori_aset" id="kategori_aset" class="form-control" required>
                                                    <option value="">-- Pilih Kategori Aset --</option>
                                                    <?php while ($rowKat = mysqli_fetch_assoc($qKategori)) { ?>
                                                        <option value="<?= $rowKat['kategori_asset']; ?>"
                                                            <?= ($rowKat['kategori_asset'] == $asset['kategori_aset']) ? 'selected' : '' ?>>
                                                            <?= $rowKat['kategori_asset']; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Lokasi -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lokasi">Lokasi <span class="text-danger">*</span></label>
                                                <select name="lokasi" id="lokasi" class="form-control" required>
                                                    <option value="">-- Pilih Lokasi Asset --</option>
                                                    <?php while ($rowLok = mysqli_fetch_assoc($qLokasi)) { ?>
                                                        <option value="<?= $rowLok['lokasi_asset']; ?>"
                                                            <?= ($rowLok['lokasi_asset'] == $asset['lokasi']) ? 'selected' : '' ?>>
                                                            <?= $rowLok['lokasi_asset']; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Lokasi Detail -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lokasi_detail">Lokasi Detail</label>
                                                <input type="text" name="lokasi_detail" id="lokasi_detail" class="form-control"
                                                    placeholder="Contoh: Lantai 2, Ruang IT"
                                                    value="<?= htmlspecialchars($asset['lokasi_detail']) ?>">
                                            </div>
                                        </div>

                                        <!-- Nama Aset -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nama_aset">Nama Aset <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_aset" id="nama_aset" class="form-control"
                                                    placeholder="Masukkan nama aset"
                                                    value="<?= htmlspecialchars($asset['nama_aset']) ?>" required>
                                            </div>
                                        </div>

                                        <!-- Merk -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="merk">Merk</label>
                                                <input type="text" name="merk" id="merk" class="form-control"
                                                    placeholder="Contoh: Samsung, HP, Dell"
                                                    value="<?= htmlspecialchars($asset['merk']) ?>">
                                            </div>
                                        </div>

                                        <!-- Spesifikasi -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="spesifikasi">Spesifikasi</label>
                                                <textarea name="spesifikasi" id="spesifikasi" class="form-control" rows="3"
                                                    placeholder="Masukkan spesifikasi detail aset"><?= htmlspecialchars($asset['spesifikasi']) ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Jumlah -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="jumlah">Jumlah <span class="text-danger">*</span></label>
                                                <input type="number" name="jumlah" id="jumlah" class="form-control"
                                                    min="1" placeholder="Masukkan jumlah"
                                                    value="<?= $asset['jumlah'] ?>" required>
                                            </div>
                                        </div>

                                        <!-- Satuan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="satuan">Satuan <span class="text-danger">*</span></label>
                                                <input type="text" name="satuan" id="satuan" class="form-control"
                                                    placeholder="Contoh: unit, buah, set"
                                                    value="<?= htmlspecialchars($asset['satuan']) ?>" required>
                                            </div>
                                        </div>

                                        <!-- Tanggal Pengadaan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="tanggal_pengadaan">Tanggal Pengadaan <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_pengadaan" id="tanggal_pengadaan" class="form-control"
                                                    value="<?= $asset['tanggal_pengadaan'] ?>" required>
                                            </div>
                                        </div>

                                        <!-- Harga Satuan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="nilai_pengadaan_satuan">Harga Satuan (Rp)</label>
                                                <input type="number" name="nilai_pengadaan_satuan" id="nilai_pengadaan_satuan" class="form-control" 
                                                    value="<?= (float)$asset['nilai_pengadaan_satuan'] ?>" oninput="calculateTotal()">
                                            </div>
                                        </div>

                                        <!-- Total Harga -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="total_nilai_pengadaan">Total Harga (Rp)</label>
                                                <input type="number" name="total_nilai_pengadaan" id="total_nilai_pengadaan" class="form-control" 
                                                    value="<?= (float)$asset['total_nilai_pengadaan'] ?>" readonly oninput="calculateTotal()">
                                            </div>
                                        </div>

                                         <!-- Status Barang -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="status_barang" class="font-weight-bold">Status Barang <span class="text-danger">*</span></label>
                                                 <select name="status_barang" id="status_barang" class="form-control" required onchange="toggleKondisi()">
                                                     <option value="Asset" <?= ($asset['status_barang'] == 'Asset') ? 'selected' : '' ?>>Asset</option>
                                                     <option value="Sewa" <?= ($asset['status_barang'] == 'Sewa') ? 'selected' : '' ?>>Sewa</option>
                                                 </select>
                                                 <small class="text-muted">Pilih "Sewa" jika barang adalah barang sewa</small>
                                             </div>
                                         </div>

                                         <!-- Status Kepemilikan -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="status_kepemilikan" class="font-weight-bold">Status Kepemilikan <span class="text-danger">*</span></label>
                                                 <select name="status_kepemilikan" id="status_kepemilikan" class="form-control" required>
                                                     <option value="" disabled <?= empty($asset['status_kepemilikan']) ? 'selected' : '' ?>>-- Pilih Status Kepemilikan --</option>
                                                     <?php foreach (['DFM','MPF','PLM','DUI'] as $sk): ?>
                                                         <option value="<?= $sk ?>" <?= (($asset['status_kepemilikan'] ?? '') === $sk) ? 'selected' : '' ?>><?= $sk ?></option>
                                                     <?php endforeach; ?>
                                                 </select>
                                             </div>
                                         </div>

                                         <!-- Kondisi -->
                                         <div class="col-12 col-md-3 mb-3" id="div_kondisi">
                                             <div class="form-group">
                                                 <label for="kondisi" class="font-weight-bold">Informasi Kondisi <span class="text-danger">*</span></label>
                                                 <select name="kondisi" id="kondisi" class="form-control" required>
                                                     <option value="">-- Pilih Kondisi --</option>
                                                     <option value="Baik" <?= ($asset['kondisi'] == 'Baik') ? 'selected' : '' ?>>Baik</option>
                                                     <option value="Rusak Ringan" <?= ($asset['kondisi'] == 'Rusak Ringan') ? 'selected' : '' ?>>Rusak Ringan</option>
                                                     <option value="Rusak Berat" <?= ($asset['kondisi'] == 'Rusak Berat') ? 'selected' : '' ?>>Rusak Berat</option>
                                                     <option value="Hilang" <?= ($asset['kondisi'] == 'Hilang') ? 'selected' : '' ?>>Hilang</option>
                                                     <option value="Dihapus" <?= ($asset['kondisi'] == 'Dihapus') ? 'selected' : '' ?>>Dihapus</option>
                                                 </select>
                                             </div>
                                         </div>

                                         <!-- Kondisi Barang (Acquisition) -->
                                         <div class="col-12 col-md-3 mb-3">
                                             <div class="form-group">
                                                 <label for="informasi_barang" class="font-weight-bold">Kondisi Barang <span class="text-danger">*</span></label>
                                                 <select name="informasi_barang" id="informasi_barang" class="form-control" required>
                                                     <option value="Baru" <?= ($asset['informasi_barang'] == 'Baru') ? 'selected' : '' ?>>Baru</option>
                                                     <option value="Second" <?= ($asset['informasi_barang'] == 'Second') ? 'selected' : '' ?>>Second</option>
                                                 </select>
                                                 <small class="text-muted">Kondisi barang saat pengadaan</small>
                                             </div>
                                         </div>

                                         <!-- User -->
                                         <div class="col-12 col-md-6 mb-3">
                                             <div class="form-group">
                                                 <label for="user" class="font-weight-bold">User/Penanggung Jawab</label>
                                                 <input type="text" name="user" id="user" class="form-control"
                                                     placeholder="Nama penanggung jawab aset"
                                                     value="<?= htmlspecialchars($asset['user']) ?>">
                                             </div>
                                         </div>



                                        <!-- TAMBAHAN: Foto Asset Saat Ini -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Foto Asset Saat Ini</label>
                                                <div class="current-photo-container">
                                                    <?php if (!empty($foto_path)): ?>
                                                        <div class="current-photo mb-2">
                                                            <img src="<?= htmlspecialchars($foto_path) ?>"
                                                                alt="Foto Asset"
                                                                class="img-thumbnail"
                                                                style="max-width: 200px; max-height: 150px; object-fit: cover; cursor: pointer;"
                                                                onclick="showImageModal('<?= htmlspecialchars($foto_path) ?>')">
                                                            <div class="mt-1">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-file-image"></i>
                                                                    <?= htmlspecialchars($asset['foto_asset']) ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="no-photo text-center py-3" style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px;">
                                                            <i class="fas fa-image fa-2x text-muted mb-2"></i>
                                                            <p class="text-muted mb-0">Belum ada foto</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAMBAHAN: Upload Foto Baru -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="foto_asset">Ganti Foto Asset</label>
                                                <input type="file" name="foto_asset" id="foto_asset" class="form-control"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                                <small class="form-text text-muted">
                                                    Format: JPG, JPEG, PNG, GIF, WEBP. Maksimal 5MB.<br>
                                                    <strong>Kosongkan jika tidak ingin mengganti foto.</strong>
                                                </small>
                                                <!-- Preview foto baru -->
                                                <div id="newImagePreview" style="margin-top: 10px; display: none;">
                                                    <label class="form-label">Preview Foto Baru:</label>
                                                    <div>
                                                        <img id="newPreview" src="" class="img-thumbnail"
                                                            style="max-width: 200px; max-height: 150px; object-fit: cover;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Keterangan -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="keterangan">Keterangan</label>
                                                <textarea name="keterangan" id="keterangan" class="form-control" rows="3"
                                                    placeholder="Keterangan tambahan (opsional)"><?= htmlspecialchars($asset['keterangan']) ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Submit -->
                                        <div class="col-md-12 text-right">
                                            <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                                            <button type="submit" name="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Update Aset
                                            </button>
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

    <!-- Modal untuk view foto -->
    <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="fas fa-image"></i> Foto Asset
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Foto Asset" class="img-fluid rounded">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</body>

<!-- CSS untuk styling -->
<style>
    .current-photo img:hover {
        transform: scale(1.05);
        transition: transform 0.3s ease;
    }

    .current-photo {
        position: relative;
    }

    .no-photo {
        min-height: 100px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    #newImagePreview img {
        border: 2px solid #28a745;
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
    }
</style>

<script>
    // Preview foto baru sebelum upload
    document.getElementById('foto_asset').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('newPreview');
        const previewDiv = document.getElementById('newImagePreview');

        if (file) {
            // Validasi ukuran file
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran file terlalu besar. Maksimal 5MB.');
                this.value = '';
                previewDiv.style.display = 'none';
                return;
            }

            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format file tidak didukung. Gunakan: JPG, JPEG, PNG, GIF, atau WEBP');
                this.value = '';
                previewDiv.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewDiv.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.style.display = 'none';
        }
    });

    // Fungsi untuk show modal foto
    function showImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        $('#imageModal').modal('show');
    }

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
            // We don't force 'Baik' here because it might already have a value, 
            // but for new ones or if we want consistency:
            // selectKondisi.value = 'Baik'; 
        } else {
            divKondisi.style.display = '';
            selectKondisi.setAttribute('required', 'required');
        }
    }

    // Run on load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        toggleKondisi();
    });

    // Also trigger on qty change
    document.getElementById('jumlah').addEventListener('input', calculateTotal);
</script>

</html>
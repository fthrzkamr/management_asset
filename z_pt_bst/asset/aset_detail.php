<?php
// filepath: c:\xampp\htdocs\asset_bst\aset_detail.php
include('../../dist/config/config.php');

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID asset tidak ditemukan!</div>';
    exit;
}

$kode_aset = mysqli_real_escape_string($conn, $_GET['id']);

// Query untuk mengambil detail asset
$query = mysqli_query($conn, "
    SELECT * FROM asset_bst 
    WHERE kode_aset = '$kode_aset' 
    LIMIT 1
");

if (!$query) {
    echo '<div class="alert alert-danger">Error: ' . mysqli_error($conn) . '</div>';
    exit;
}

$asset = mysqli_fetch_assoc($query);

if (!$asset) {
    echo '<div class="alert alert-warning">Asset tidak ditemukan!</div>';
    exit;
}

// Format tanggal
$tanggal_pengadaan = $asset['tanggal_pengadaan'] ? date('d-m-Y', strtotime($asset['tanggal_pengadaan'])) : '-';
$created_at = $asset['created_at'] ? date('d-m-Y H:i', strtotime($asset['created_at'])) : '-';
$updated_at = $asset['updated_at'] ? date('d-m-Y H:i', strtotime($asset['updated_at'])) : '-';

// Badge kondisi
$kondisi_class = '';
switch ($asset['kondisi']) {
    case 'Baik': $kondisi_class = 'badge-success'; break;
    case 'Rusak Ringan': $kondisi_class = 'badge-warning'; break;
    case 'Rusak Berat': $kondisi_class = 'badge-danger'; break;
    case 'Hilang': $kondisi_class = 'badge-dark'; break;
    case 'Dihapus': $kondisi_class = 'badge-secondary'; break;
    default: $kondisi_class = 'badge-info';
}

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

<div class="row">
    <!-- Kolom Kiri: Informasi Asset -->
    <div class="col-md-8">
        <!-- Info Utama -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle"></i> Informasi Utama Asset
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="40%"><strong>Kode Asset</strong></td>
                                <td>: <span class="badge badge-dark"><?= htmlspecialchars($asset['kode_aset']) ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Nama Asset</strong></td>
                                <td>: <?= htmlspecialchars($asset['nama_aset']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kategori</strong></td>
                                <td>: <?= htmlspecialchars($asset['kategori_aset']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Merk</strong></td>
                                <td>: <?= htmlspecialchars($asset['merk'] ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kondisi</strong></td>
                                <td>: <span class="badge <?= $kondisi_class ?>"><?= htmlspecialchars($asset['kondisi']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="40%"><strong>Lokasi</strong></td>
                                <td>: <?= htmlspecialchars($asset['lokasi']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lokasi Detail</strong></td>
                                <td>: <?= htmlspecialchars($asset['lokasi_detail'] ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Jumlah</strong></td>
                                <td>: <?= number_format($asset['jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($asset['satuan']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>User/PJ</strong></td>
                                <td>: <?= htmlspecialchars($asset['user'] ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tanggal Pengadaan</strong></td>
                                <td>: <?= $tanggal_pengadaan ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>



        <!-- Spesifikasi & Keterangan -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-cogs"></i> Spesifikasi & Keterangan
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="20%"><strong>Spesifikasi</strong></td>
                        <td>: <?= nl2br(htmlspecialchars($asset['spesifikasi'] ?: '-')) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Keterangan</strong></td>
                        <td>: <?= nl2br(htmlspecialchars($asset['keterangan'] ?: '-')) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Dibuat</strong></td>
                        <td>: <?= $created_at ?></td>
                    </tr>
                    <tr>
                        <td><strong>Diperbarui</strong></td>
                        <td>: <?= $updated_at ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Foto Asset -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-camera"></i> Foto Asset
                </h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($foto_path)): ?>
                    <!-- Foto Asset -->
                    <div class="asset-photo-container mb-3">
                        <img src="<?= htmlspecialchars($foto_path) ?>" 
                             alt="Foto <?= htmlspecialchars($asset['nama_aset']) ?>" 
                             class="img-fluid rounded shadow-sm"
                             style="max-width: 100%; max-height: 300px; object-fit: cover;">
                    </div>
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-file-image"></i> 
                        <?= htmlspecialchars($asset['foto_asset']) ?>
                    </small>
                    <!-- Button Download -->
                    <a href="<?= htmlspecialchars($foto_path) ?>" 
                       download="<?= htmlspecialchars($asset['foto_asset']) ?>"
                       class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Download Foto
                    </a>
                <?php else: ?>
                    <!-- Placeholder jika tidak ada foto -->
                    <div class="text-center py-5" style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px;">
                        <i class="fas fa-image fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Tidak ada foto</p>
                        <small class="text-muted">Foto belum diupload untuk asset ini</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </div>
</div>

<!-- Action Buttons -->
<div class="row mt-3">
    <div class="col-md-12 text-right">
        <a href="aset_edit.php?id=<?= urlencode($asset['kode_aset']) ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i> Edit Asset
        </a>
        <button type="button" class="btn btn-secondary btn-sm ml-2" data-dismiss="modal">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
</div>

<!-- CSS untuk styling -->
<style>
.asset-photo-container {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
}

.progress {
    border-radius: 15px;
}

.bg-orange {
    background-color: #fd7e14 !important;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.badge {
    font-size: 0.75em;
}

.table td {
    vertical-align: middle;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .col-md-4 {
        margin-top: 20px;
    }
    
    .text-right {
        text-align: center !important;
    }
}
</style>
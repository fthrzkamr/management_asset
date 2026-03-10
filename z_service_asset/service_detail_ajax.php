<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\service_detail_ajax.php
include('../dist/config/config.php');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID service tidak ditemukan!</div>';
    exit;
}

$service_id = mysqli_real_escape_string($conn, $_GET['id']);

// PERBAIKAN: Query dengan perhitungan durasi yang benar
$query = mysqli_query($conn, "
    SELECT 
        s.*,
        a.nama_aset,
        a.merk,
        a.kategori_aset,
        a.lokasi,
        a.kondisi,
        -- Perhitungan durasi yang benar
        CASE 
            WHEN s.status_service = 'Selesai' AND s.tanggal_selesai IS NOT NULL 
            THEN DATEDIFF(s.tanggal_selesai, s.tanggal_service)
            ELSE DATEDIFF(CURDATE(), s.tanggal_service)
        END as durasi_hari
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset = a.kode_aset
    WHERE s.id_service = '$service_id'
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) == 0) {
    echo '<div class="alert alert-danger">Data service tidak ditemukan!</div>';
    exit;
}

$service = mysqli_fetch_assoc($query);

// Format tanggal
$tanggal_service = date('d-m-Y', strtotime($service['tanggal_service']));
$tanggal_selesai = $service['tanggal_selesai'] ? date('d-m-Y', strtotime($service['tanggal_selesai'])) : '-';

// Badge status
$status_class = $service['status_service'] == 'Service' ? 'badge-warning' : 'badge-success';

// PERBAIKAN: Durasi class dengan logika yang lebih tepat
if ($service['status_service'] == 'Service') {
    // Untuk service yang sedang berlangsung
    $durasi_class = $service['durasi_hari'] > 7 ? 'text-danger' : 
                   ($service['durasi_hari'] > 3 ? 'text-warning' : 'text-success');
} else {
    // Untuk service yang sudah selesai, warna berdasarkan lamanya pengerjaan
    $durasi_class = $service['durasi_hari'] > 7 ? 'text-danger' : 
                   ($service['durasi_hari'] > 3 ? 'text-warning' : 'text-success');
}

// Path dokumentasi service (foto saat menambah service)
$dokumentasi_service_path = '';
$dokumentasi_service_exists = false;
if (!empty($service['dokumentasi_service'])) {
    $dokumentasi_service_path = '../uploads/service/' . $service['dokumentasi_service'];
    if (file_exists($dokumentasi_service_path)) {
        $dokumentasi_service_exists = true;
    }
}

// Path foto nota (foto saat menyelesaikan service)
$foto_nota_path = '';
$foto_nota_exists = false;
if (!empty($service['foto_nota'])) {
    $foto_nota_path = '../uploads/nota/' . $service['foto_nota'];
    if (file_exists($foto_nota_path)) {
        $foto_nota_exists = true;
    }
}
?>

<div class="row">
    <!-- Informasi Service -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-tools"></i> Informasi Service</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="30%"><strong>No. Service</strong></td>
                        <td>: <span class="badge badge-dark"><?= htmlspecialchars($service['no_service']) ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Jenis Service</strong></td>
                        <td>: <span class="badge badge-info"><?= htmlspecialchars($service['jenis_service']) ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal Service</strong></td>
                        <td>: <?= $tanggal_service ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal Selesai</strong></td>
                        <td>: <?= $tanggal_selesai ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td>: <span class="badge <?= $status_class ?>"><?= htmlspecialchars($service['status_service']) ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Durasi</strong></td>
                        <td>: 
                            <span class="font-weight-bold <?= $durasi_class ?>">
                                <?= $service['durasi_hari'] ?> hari
                            </span>
                            <!-- PERBAIKAN: Keterangan durasi -->
                            <br>
                            <small class="text-muted">
                                <?php if ($service['status_service'] == 'Service'): ?>
                                    <i class="fas fa-clock"></i> Sedang berlangsung
                                <?php else: ?>
                                    <i class="fas fa-check"></i> Lama pengerjaan
                                <?php endif; ?>
                            </small>
                        </td>
                    </tr>
                    <?php if ($service['status_service'] == 'Selesai' && $service['biaya_service'] > 0): ?>
                    <tr>
                        <td><strong>Biaya Service</strong></td>
                        <td>: <strong class="text-success">Rp <?= number_format($service['biaya_service'], 0, ',', '.') ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($service['teknisi'])): ?>
                    <tr>
                        <td><strong>Teknisi/PJ</strong></td>
                        <td>: <?= htmlspecialchars($service['teknisi']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Keluhan & Tindakan -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-clipboard-list"></i> Detail Service</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><strong>Keluhan:</strong></h6>
                        <p class="text-muted bg-light p-2 rounded"><?= nl2br(htmlspecialchars($service['keluhan'] ?: '-')) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6><strong>Tindakan:</strong></h6>
                        <p class="text-muted bg-light p-2 rounded"><?= nl2br(htmlspecialchars($service['tindakan'] ?: 'Belum ada tindakan')) ?></p>
                    </div>
                </div>

                <?php if (!empty($service['keterangan'])): ?>
                    <hr>
                    <h6><strong>Keterangan Tambahan:</strong></h6>
                    <p class="text-muted bg-light p-2 rounded"><?= nl2br(htmlspecialchars($service['keterangan'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informasi Asset -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-cube"></i> Informasi Asset</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="30%"><strong>Kode Asset</strong></td>
                        <td>: <span class="badge badge-secondary"><?= htmlspecialchars($service['kode_aset']) ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Nama Asset</strong></td>
                        <td>: <?= htmlspecialchars($service['nama_aset']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Merk</strong></td>
                        <td>: <?= htmlspecialchars($service['merk'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kategori</strong></td>
                        <td>: <?= htmlspecialchars($service['kategori_aset']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Lokasi</strong></td>
                        <td>: <?= htmlspecialchars($service['lokasi']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kondisi</strong></td>
                        <td>: 
                            <?php
                            $kondisi_class = '';
                            switch($service['kondisi']) {
                                case 'Baik': $kondisi_class = 'badge-success'; break;
                                case 'Rusak Ringan': $kondisi_class = 'badge-warning'; break;
                                case 'Rusak Berat': $kondisi_class = 'badge-danger'; break;
                                default: $kondisi_class = 'badge-secondary';
                            }
                            ?>
                            <span class="badge <?= $kondisi_class ?>"><?= htmlspecialchars($service['kondisi']) ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Action Buttons -->
        <?php if ($service['status_service'] == 'Service'): ?>
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">
                        <i class="fas fa-cogs"></i> Aksi Service
                    </h6>
                    <a href="service_complete.php?id=<?= $service['id_service'] ?>"
                        class="btn btn-success btn-block"
                        onclick="closeModal();">
                        <i class="fas fa-check"></i> Selesaikan Service
                    </a>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Service sudah berjalan <?= $service['durasi_hari'] ?> hari
                    </small>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h6>Service Sudah Selesai</h6>
                        <small class="text-muted">
                            Diselesaikan pada: <?= $tanggal_selesai ?><br>
                            <!-- PERBAIKAN: Tampilkan lama pengerjaan -->
                            <strong>Lama pengerjaan: <?= $service['durasi_hari'] ?> hari</strong>
                        </small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dokumentasi Service -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-camera"></i> Dokumentasi Service</h6>
            </div>
            <div class="card-body">
                <!-- FOTO SAAT MENAMBAH SERVICE -->
                <div class="mb-3">
                    <h6 class="mb-2 text-center">
                        <i class="fas fa-plus-circle text-primary"></i> 
                        <strong>Foto Saat Service</strong>
                    </h6>
                    <div class="photo-container">
                        <?php if ($dokumentasi_service_exists): ?>
                            <div class="photo-box">
                                <img src="<?= htmlspecialchars($dokumentasi_service_path) ?>"
                                    alt="Dokumentasi Service"
                                    class="photo-img">
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-file-image"></i> 
                                    <?= htmlspecialchars($service['dokumentasi_service']) ?>
                                </small>
                                <br>
                                <span class="badge badge-primary mt-1">Foto Service</span>
                            </div>
                        <?php else: ?>
                            <div class="photo-box empty-photo-box">
                                <div class="empty-photo-content">
                                    <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-1 font-weight-bold small">Tidak ada foto</p>
                                    <small class="text-muted">Saat menambah service</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>

                <!-- FOTO NOTA SAAT SELESAI SERVICE -->
                <div class="mb-0">
                    <h6 class="mb-2 text-center">
                        <i class="fas fa-receipt text-success"></i> 
                        <strong>Foto Nota Selesai</strong>
                    </h6>
                    <div class="photo-container">
                        <?php if ($service['status_service'] == 'Selesai'): ?>
                            <?php if ($foto_nota_exists): ?>
                                <div class="photo-box">
                                    <img src="<?= htmlspecialchars($foto_nota_path) ?>"
                                        alt="Foto Nota Service"
                                        class="photo-img">
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-file-image"></i> 
                                        <?= htmlspecialchars($service['foto_nota']) ?>
                                    </small>
                                    <br>
                                    <span class="badge badge-success mt-1">Nota Service</span>
                                    <br>
                                    <!-- PERBAIKAN: Tombol download foto nota -->
                                    <a href="download_foto_nota.php?id=<?= $service['id_service'] ?>" 
                                       class="btn btn-sm btn-outline-success mt-2" 
                                       download>
                                        <i class="fas fa-download"></i> Download Nota
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="photo-box empty-photo-box warning-box">
                                    <div class="empty-photo-content">
                                        <i class="fas fa-receipt fa-2x text-warning mb-2"></i>
                                        <p class="text-warning mb-1 font-weight-bold small">Tidak ada nota</p>
                                        <small class="text-muted">Service selesai tanpa nota</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="photo-box empty-photo-box info-box">
                                <div class="empty-photo-content">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <p class="text-info mb-1 font-weight-bold small">Service belum selesai</p>
                                    <small class="text-muted">Nota akan muncul setelah selesai</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script -->
<script>
    function closeModal() {
        if (typeof $ !== 'undefined') {
            $('#serviceModal, #serviceDetailModal').modal('hide');
        }
    }

    $(document).ready(function() {
        $('#serviceDetailModal .close, #serviceDetailModal [data-dismiss="modal"]').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        });

        $(document).off('keydown.serviceModal').on('keydown.serviceModal', function(e) {
            if (e.key === 'Escape' && $('#serviceDetailModal').hasClass('show')) {
                e.preventDefault();
                closeModal();
            }
        });

        $('#serviceDetailModal').off('click.backdrop').on('click.backdrop', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    });
</script>

<!-- CSS tanpa hover effects -->
<style>
    .photo-container {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .photo-box {
        width: 200px;
        height: 150px;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        border: 2px solid #dee2e6;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .photo-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .empty-photo-box {
        border: 2px dashed #dee2e6;
        background: #f8f9fa;
    }

    .empty-photo-box.warning-box {
        border-color: #ffc107;
        background: #fff3cd;
    }

    .empty-photo-box.info-box {
        border-color: #17a2b8;
        background: #d1ecf1;
    }

    .empty-photo-content {
        text-align: center;
        padding: 10px;
    }

    .empty-photo-content p {
        font-size: 0.85rem;
        margin-bottom: 5px;
    }

    .empty-photo-content small {
        font-size: 0.7rem;
    }

    @media (max-width: 768px) {
        .photo-box {
            width: 100%;
            max-width: 200px;
            height: 150px;
        }
    }
</style>
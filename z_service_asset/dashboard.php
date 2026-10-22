<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\dashboard.php
include('../dist/config/config.php');

// Cek session login
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
$auth->requirePermission('pengajuan_service', 'read');

// Pagination untuk Service Aktif
$limit_aktif = 10;
$page_aktif = isset($_GET['pa']) ? (int)$_GET['pa'] : 1;
$offset_aktif = ($page_aktif - 1) * $limit_aktif;

$count_aktif_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM service_asset WHERE status_service = 'Service'");
$total_aktif = mysqli_fetch_assoc($count_aktif_res)['total'];
$total_pages_aktif = ceil($total_aktif / $limit_aktif);

// Query untuk service yang sedang aktif dengan join asset dan cek relasi PPX
$service_aktif_list = mysqli_query($conn, "
    SELECT 
        s.id_service, s.no_service, s.kode_aset, s.tanggal_service, s.jenis_service, s.keluhan, s.status_service,
        a.nama_aset, a.merk, a.kategori_aset, a.lokasi, a.lokasi_detail, a.kondisi, a.foto_asset,
        DATEDIFF(CURDATE(), s.tanggal_service) as durasi_hari,
        ppx.no_ppx, ppx.status as ppx_status
    FROM service_asset s
    LEFT JOIN (
        SELECT kode_aset, nama_aset, merk, kategori_aset, lokasi, lokasi_detail, kondisi, foto_asset FROM asset_dfm
        UNION ALL
        SELECT kode_aset, nama_aset, merk, kategori_aset, lokasi, lokasi_detail, kondisi, foto_asset FROM asset_ga
    ) a ON s.kode_aset = a.kode_aset
    LEFT JOIN ppx_request ppx ON s.no_service = ppx.no_service
    WHERE s.status_service = 'Service'
    ORDER BY s.tanggal_service ASC
    LIMIT $limit_aktif OFFSET $offset_aktif
");

// Pagination untuk Service Selesai
$limit_selesai = 10;
$page_selesai = isset($_GET['ps']) ? (int)$_GET['ps'] : 1;
$offset_selesai = ($page_selesai - 1) * $limit_selesai;

$count_selesai_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM service_asset WHERE status_service = 'Selesai'");
$total_selesai_rec = mysqli_fetch_assoc($count_selesai_res)['total'];
$total_pages_selesai = ceil($total_selesai_rec / $limit_selesai);

// Query untuk service yang baru selesai
$service_selesai_recent = mysqli_query($conn, "
    SELECT 
        s.id_service, s.no_service, s.kode_aset, s.tanggal_service, s.tanggal_selesai, s.jenis_service, s.biaya_service,
        a.nama_aset, a.merk, a.lokasi, a.lokasi_detail
    FROM service_asset s
    LEFT JOIN (
        SELECT kode_aset, nama_aset, merk, lokasi, lokasi_detail FROM asset_dfm
        UNION ALL
        SELECT kode_aset, nama_aset, merk, lokasi, lokasi_detail FROM asset_ga
    ) a ON s.kode_aset = a.kode_aset
    WHERE s.status_service = 'Selesai'
    ORDER BY s.tanggal_selesai DESC
    LIMIT $limit_selesai OFFSET $offset_selesai
");

// Handle success/error messages
$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'service_completed':
            $message = 'Service berhasil diselesaikan!';
            $message_type = 'success';
            break;
        case 'service_reverted':
            $message = 'Service berhasil dibatalkan!';
            $message_type = 'success';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'id_not_found':
            $message = 'ID service tidak ditemukan!';
            $message_type = 'danger';
            break;
        case 'service_not_found':
            $message = 'Data service tidak ditemukan atau sudah selesai!';
            $message_type = 'warning';
            break;
        case 'service_linked_to_ppx':
            $message = 'Service tidak dapat dibatalkan karena sudah terhubung dengan pengajuan PPX. Silakan batalkan PPX terlebih dahulu jika diperlukan.';
            $message_type = 'danger';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Service Asset</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Service Asset</li>
                            </ul>
                            
                            <!-- Button Group - Berjejer Horizontal -->
                            <div class="d-flex align-items-center" style="gap: 10px;">
                                <!-- Button Tambah Service -->
                                <a href="service_add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus fa-sm"></i> Tambah Service
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                    <?= $message ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php endif; ?>

                <!-- Statistik Cards -->
                <!-- <div class="row">
                    <div class="col-xl-4 col-sm-6 col-12 d-flex">
                        <div class="card bg-info w-100">
                            <div class="card-body">
                                <div class="db-widgets d-flex justify-content-between align-items-center">
                                    <div class="db-info">
                                        <h6 class="text-white">Total Service</h6>
                                        <h3 class="text-white"><?= number_format($total_service) ?></h3>
                                    </div>
                                    <div class="db-icon text-white">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6 col-12 d-flex">
                        <div class="card bg-warning w-100">
                            <div class="card-body">
                                <div class="db-widgets d-flex justify-content-between align-items-center">
                                    <div class="db-info">
                                        <h6 class="text-white">Sedang Service</h6>
                                        <h3 class="text-white"><?= number_format($service_aktif) ?></h3>
                                    </div>
                                    <div class="db-icon text-white">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6 col-12 d-flex">
                        <div class="card bg-success w-100">
                            <div class="card-body">
                                <div class="db-widgets d-flex justify-content-between align-items-center">
                                    <div class="db-info">
                                        <h6 class="text-white">Service Selesai</h6>
                                        <h3 class="text-white"><?= number_format($service_selesai) ?></h3>
                                    </div>
                                    <div class="db-icon text-white">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Service Aktif -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-table">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="card-title">
                                            <i class="fas fa-wrench text-warning"></i> Asset Sedang Di Service
                                            <span class="badge badge-warning ml-2"><?= $total_aktif ?></span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($service_aktif_list) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>Asset</th>
                                                    <th>No. Service</th>
                                                    <th>Jenis Service</th>
                                                    <th>Tanggal</th>
                                                    <th>Durasi</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $no = $offset_aktif + 1;
                                                while ($row = mysqli_fetch_assoc($service_aktif_list)): 
                                                ?>
                                                    <tr>
                                                        <td><small class="font-weight-bold"><?= $no++ ?></small></td>
                                                        <td>
                                                            <div class="media align-items-center">
                                                                <div class="media-body">
                                                                    <div class="font-weight-bold"><?= htmlspecialchars($row['nama_aset']) ?></div>
                                                                    <div class="small text-muted">
                                                                        <?= htmlspecialchars($row['kode_aset']) ?> | <?= htmlspecialchars($row['merk']) ?>
                                                                    </div>
                                                                    <div class="small text-muted">
                                                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['lokasi']) ?> - <?= htmlspecialchars($row['lokasi_detail']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-dark"><?= htmlspecialchars($row['no_service']) ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info"><?= htmlspecialchars($row['jenis_service']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div><?= date('d-m-Y', strtotime($row['tanggal_service'])) ?></div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $durasi = $row['durasi_hari'];
                                                            $durasi_class = $durasi > 7 ? 'text-danger' : ($durasi > 3 ? 'text-warning' : 'text-success');
                                                            ?>
                                                            <span class="font-weight-bold <?= $durasi_class ?>">
                                                                <?= $durasi ?> hari
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-warning">
                                                                <i class="fas fa-clock"></i> Service
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-info btn-modal-detail"
                                                                        data-service-id="<?= $row['id_service'] ?>"
                                                                        data-toggle="tooltip"
                                                                        title="Lihat Detail">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <a href="service_complete.php?id=<?= $row['id_service'] ?>"
                                                                    class="btn btn-sm btn-success"
                                                                    data-toggle="tooltip"
                                                                    title="Selesaikan Service">
                                                                    <i class="fas fa-check"></i>
                                                                </a>
                                                                <?php if (!empty($row['no_ppx'])): ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-secondary" 
                                                                            disabled
                                                                            data-toggle="tooltip"
                                                                            title="Tidak dapat dibatalkan - Terhubung dengan PPX. Hubungi Admin jika perlu dibatalkan.">
                                                                        <i class="fas fa-lock"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" 
                                                                            class="btn btn-sm btn-outline-danger btn-revert-service" 
                                                                            data-service-id="<?= $row['id_service'] ?>" 
                                                                            title="Batalkan Service">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <?php if ($total_pages_aktif > 1): ?>
                                        <div class="mt-4">
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm justify-content-center">
                                                    <?php if ($page_aktif > 1): ?>
                                                        <li class="page-item"><a class="page-link" href="?pa=<?= $page_aktif - 1 ?>&ps=<?= $page_selesai ?>">Previous</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php for ($i = 1; $i <= $total_pages_aktif; $i++): ?>
                                                        <li class="page-item <?= ($i == $page_aktif) ? 'active' : '' ?>">
                                                            <a class="page-link" href="?pa=<?= $i ?>&ps=<?= $page_selesai ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($page_aktif < $total_pages_aktif): ?>
                                                        <li class="page-item"><a class="page-link" href="?pa=<?= $page_aktif + 1 ?>&ps=<?= $page_selesai ?>">Next</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Tidak Ada Service Aktif</h5>
                                        <p class="text-muted">Semua asset dalam kondisi baik atau belum ada service yang berjalan</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Selesai Recent -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="card-title">
                                            <i class="fas fa-check-circle text-success"></i> Service Baru Selesai
                                        </h5>
                                    </div>
                                    <div class="col-auto">
                                        <a href="report_service_selesai.php" class="btn-right btn btn-sm btn-success">
                                            <i class="fas fa-chart-line"></i> Report Service Selesai
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($service_selesai_recent) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>No. Service</th>
                                                    <th>Asset</th>
                                                    <th>Jenis Service</th>
                                                    <th>Tanggal Selesai</th>
                                                    <th>Biaya</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // Reset query pointer
                                                mysqli_data_seek($service_selesai_recent, 0);
                                                $no = $offset_selesai + 1;
                                                while ($row = mysqli_fetch_assoc($service_selesai_recent)): 
                                                ?>
                                                    <tr>
                                                        <td><small class="font-weight-bold"><?= $no++ ?></small></td>
                                                        <td>
                                                            <span class="badge badge-dark"><?= htmlspecialchars($row['no_service']) ?></span>
                                                        </td>
                                                         <td>
                                                            <div class="font-weight-bold text-dark mb-1"><?= htmlspecialchars($row['nama_aset']) ?></div>
                                                            <div class="small text-muted mb-1">
                                                                <span class="badge badge-light p-1" style="font-size: 10px;">
                                                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($row['kode_aset']) ?>
                                                                </span>
                                                                <span class="ml-1"><?= htmlspecialchars($row['merk']) ?></span>
                                                            </div>
                                                            <div class="small text-muted">
                                                                <i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($row['lokasi']) ?> - <?= htmlspecialchars($row['lokasi_detail']) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info"><?= htmlspecialchars($row['jenis_service']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?= ($row['tanggal_selesai'] && $row['tanggal_selesai'] != '0000-00-00') ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-' ?>
                                                        </td>
                                                        <td>
                                                            <span class="font-weight-bold text-success">
                                                                Rp <?= number_format($row['biaya_service'], 0, ',', '.') ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <!-- PERBAIKAN: Ganti onclick dengan data-attributes -->
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-info btn-modal-detail"
                                                                    data-service-id="<?= $row['id_service'] ?>"
                                                                    data-toggle="tooltip"
                                                                    title="Lihat Detail">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-revert-service" data-service-id="<?= $row['id_service'] ?>" title="Batalkan Service">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($total_pages_selesai > 1): ?>
                                        <div class="mt-4">
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm justify-content-center">
                                                    <?php if ($page_selesai > 1): ?>
                                                        <li class="page-item"><a class="page-link" href="?ps=<?= $page_selesai - 1 ?>&pa=<?= $page_aktif ?>">Previous</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php for ($i = 1; $i <= $total_pages_selesai; $i++): ?>
                                                        <li class="page-item <?= ($i == $page_selesai) ? 'active' : '' ?>">
                                                            <a class="page-link" href="?ps=<?= $i ?>&pa=<?= $page_aktif ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($page_selesai < $total_pages_selesai): ?>
                                                        <li class="page-item"><a class="page-link" href="?ps=<?= $page_selesai + 1 ?>&pa=<?= $page_aktif ?>">Next</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Belum ada service yang selesai</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
                <?php
                // Include confirm modal if available
                $layout_dir = realpath(__DIR__ . '/../layouts');
                if ($layout_dir && file_exists($layout_dir . '/confirm_modal.php')) {
                    include $layout_dir . '/confirm_modal.php';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN: Modal yang lebih sederhana -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-tools"></i> Detail Service
                    </h5>
                    <!-- PERBAIKAN: Multiple close buttons -->
                    <button type="button" class="close text-white btn-close-modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Memuat detail service...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN: JavaScript yang sangat sederhana -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // PERBAIKAN: Event delegation untuk button detail
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-modal-detail')) {
                    e.preventDefault();
                    const btn = e.target.closest('.btn-modal-detail');
                    const serviceId = btn.getAttribute('data-service-id');
                    
                    if (serviceId) {
                        openModal(serviceId);
                    }
                }

                // PERBAIKAN: Close modal buttons
                if (e.target.closest('.btn-close-modal') || e.target.classList.contains('btn-close-modal')) {
                    e.preventDefault();
                    closeModal();
                }

                // Handle revert (soft-delete) service
                if (e.target.closest('.btn-revert-service')) {
                    e.preventDefault();
                    var btn = e.target.closest('.btn-revert-service');
                    var id = btn.getAttribute('data-service-id');
                    if (!id) return;
                    showConfirm('Batalkan Service', 'Yakin ingin membatalkan service ini? Status akan dikembalikan seperti semula.', function() {
                        var fd = new FormData();
                        fd.append('id', id);
                        fetch('service_revert.php', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd
                        })
                        .then(function(resp) {
                            if (!resp.ok) throw new Error('Network response not ok');
                            return resp.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                alert(data.message || 'Berhasil membatalkan service');
                                location.reload();
                            } else {
                                alert('Error: ' + (data.message || 'Gagal'));
                            }
                        })
                        .catch(function(err) {
                            console.error('Revert error', err);
                            alert('Terjadi kesalahan saat membatalkan service');
                        });
                    }, function() {
                        // canceled
                    });
                }
            });

            // PERBAIKAN: ESC key untuk close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('serviceModal');
                    if (modal.classList.contains('show')) {
                        closeModal();
                    }
                }
            });

            // PERBAIKAN: Click outside untuk close
            document.getElementById('serviceModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });

        // PERBAIKAN: Function untuk buka modal
        function openModal(serviceId) {
            const modal = document.getElementById('serviceModal');
            const content = document.getElementById('modalContent');
            
            // Reset content
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Memuat detail service...</p>
                </div>
            `;
            
            // Show modal menggunakan Bootstrap
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#serviceModal').modal('show');
            } else {
                // Fallback manual
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'modalBackdrop';
                document.body.appendChild(backdrop);
            }

            // Load content via fetch
            fetch('service_detail_ajax.php?id=' + serviceId)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    content.innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h5>Gagal Memuat Data</h5>
                            <p>Tidak dapat memuat detail service.</p>
                            <button class="btn btn-primary" onclick="location.reload()">Refresh Halaman</button>
                        </div>
                    `;
                });
        }

        // PERBAIKAN: Function untuk close modal
        function closeModal() {
            const modal = document.getElementById('serviceModal');
            const backdrop = document.getElementById('modalBackdrop');
            
            try {
                // Coba pakai Bootstrap dulu
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $('#serviceModal').modal('hide');
                } else {
                    // Manual close
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    
                    // Remove backdrop
                    if (backdrop) {
                        backdrop.remove();
                    }
                }
                
                } catch (error) {
                console.error('Error closing modal:', error);
                
                // Force close manual
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
                
                // Remove all backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(b => b.remove());
                
                }
        }
    </script>

    <!-- CSS Custom -->
    <style>
        .card-table {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }

        .badge { font-size: 0.75em; }
        .table td { vertical-align: middle; }
        .btn-group .btn { margin-right: 2px; }
        .btn-group .btn:last-child { margin-right: 0; }
        .db-widgets .db-icon { font-size: 2rem; opacity: 0.8; }

        /* Modal improvements */
        .modal {
            z-index: 1050;
        }
        
        .modal-backdrop {
            z-index: 1040;
        }
        
        .btn-close-modal {
            cursor: pointer;
            z-index: 10;
        }
        
        .close {
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .col-xl-4 { margin-bottom: 15px; }
            .modal-xl { max-width: 95%; margin: 10px; }
        }
    </style>
</body>
</html>
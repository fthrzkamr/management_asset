<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\dashboard.php
// Use absolute paths based on this file's directory
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Inisialisasi auth helper
$auth = new AuthHelper($conn);

// Tambahkan mapping label status
$status_labels = [
    'Leader'   => 'Kacap/Manager',
    'Kacab'    => 'Kepala Cabang',
    'Manager'  => 'Manager Finance',
    'Finance'  => 'GA (General Affairs)',
    'Direktur' => 'Direktur',
    'Done'     => 'Selesai',
    'Selesai'  => 'Selesai',
    'Uang Muka' => 'Uang Muka',
    'Revisi'   => 'Revisi',
    'Ditolak'  => 'Ditolak'
];

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
$auth->requirePermission('ppx', 'read');

$user_id = (int)$_SESSION['id_user'];
$is_superadmin = $auth->isSuperAdmin();

// Halaman pengajuan: Super Admin dapat melihat semua pengajuan, user biasa hanya melihat pengajuan miliknya sendiri
$whereConditions = [];
if (!$is_superadmin) {
    $whereConditions[] = "pr.user_id = $user_id";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";


// Cek apakah kolom request_type ada di tabel ppx_request dan ppx_approval_routing
$check_pr = mysqli_query($conn, "SHOW COLUMNS FROM ppx_request LIKE 'request_type'");
$has_pr_req_type = mysqli_num_rows($check_pr) > 0;

$check_par = mysqli_query($conn, "SHOW COLUMNS FROM ppx_approval_routing LIKE 'request_type'");
$has_par_req_type = mysqli_num_rows($check_par) > 0;

$pr_req_col = $has_pr_req_type ? "pr.request_type," : "'' AS request_type,";
$par_req_order = ($has_pr_req_type && $has_par_req_type) ? ", (par.request_type = COALESCE(pr.request_type, 'Regular')) DESC" : "";

// Query untuk daftar pengajuan PPX
$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi,
    d.nama_divisi,
    pr.cabang,
    pr.status,
    pr.nominal_ppx,
    pr.user_id,
    $pr_req_col
    (SELECT par.initial_status FROM ppx_approval_routing par 
     WHERE par.divisi_id = pr.divisi 
       AND (par.cabang IS NULL OR par.cabang = '' OR par.cabang = pr.cabang)
       AND par.is_active = 1 
       AND (par.request_type IS NULL OR par.request_type = '' OR par.request_type = COALESCE(pr.request_type,'Regular') OR par.request_type = 'Semua')
     ORDER BY (par.cabang = pr.cabang) DESC $par_req_order 
     LIMIT 1) AS route_initial_status,
    u.nama_user AS nama_pengaju,
    COUNT(prd.no_ppx_detail) AS total_items,
    COALESCE(SUM(prd.total_harga), 0) AS total_nominal_detail,
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
$whereClause
GROUP BY pr.no_ppx
ORDER BY 
    FIELD(pr.status, 'Revisi','Leader','Kacab','Manager','Finance','Direktur','Uang Muka','Selesai'),
    pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query khusus untuk pengajuan user
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Leader' THEN 1 END) as pending_leader,
    COUNT(CASE WHEN status = 'Kacab' THEN 1 END) as pending_kacab,
    COUNT(CASE WHEN status = 'Manager' THEN 1 END) as pending_manager,
    COUNT(CASE WHEN status = 'Finance' THEN 1 END) as pending_finance,
    COUNT(CASE WHEN status = 'Direktur' THEN 1 END) as pending_direktur,
    COUNT(CASE WHEN status IN ('Done','Selesai') THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'Uang Muka' THEN 1 END) as uang_muka,
    COUNT(CASE WHEN status = 'Revisi' THEN 1 END) as rejected
FROM ppx_request pr $whereClause";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Pengajuan PPX</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <!-- Include required CSS files -->
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    } else {
        echo '<!-- header_css.php not found -->';
    }
    ?>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        .btn-action {
            margin: 1px;
            padding: 4px 8px;
            font-size: 11px;
        }

        .card-stats {
            transition: all 0.2s ease;
        }

        .card-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            opacity: 0.6;
            font-size: 1.5rem;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .badge-sm {
            font-size: 0.7rem;
        }

        .badge-urgent {
            animation: pulseUrgent 1.2s ease-in-out infinite;
            transform-origin: center;
            color: #fff;
        }

        @keyframes pulseUrgent {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 0, 0, 0);
            }

            40% {
                transform: scale(1.06);
                box-shadow: 0 0 14px rgba(255, 0, 0, 0.22);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 0, 0, 0);
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php
        $layout_dir = realpath(__DIR__ . '/../layouts');
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        } else {
            echo '<!-- navbar.php not found -->';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Pengajuan PPX</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Pengajuan PPX</li>
                            </ul>
                            
                            <!-- Button Group - Berjejer Horizontal -->
                            <div class="d-flex align-items-center" style="gap: 10px;">
                                <!-- Button Tambah Pengajuan -->
                                <?php if ($auth->canCreatePPX()): ?>
                                    <a href="add.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus fa-sm"></i> Tambah Pengajuan PPX
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan PPX
                                    <?php if (!$auth->isSuperAdmin()): ?>
                                        <span class="text-muted small">- Pengajuan Anda</span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="ppxTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th width="4%">No.</th>
                                                <th width="12%">No. PPX</th>
                                                <th width="10%">Tanggal</th>
                                                <th width="15%">Pengaju</th>
                                                <th width="12%">Divisi</th>
                                                <th width="8%">Items</th>
                                                <th width="15%">Total Nominal</th>
                                                <th width="10%">Status</th>
                                                <th width="18%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php $row_num = 1; ?>
                                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                    <tr>
                                                        <td><small class="font-weight-bold"><?= $row_num++ ?></small></td>
                                                        <td><small class="font-weight-bold"><?= htmlspecialchars($row['no_ppx']) ?></small></td>
                                                        <td><small><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></small></td>
                                                        <td><small><?= htmlspecialchars($row['nama_pic']) ?></small></td>
                                                        <td><small><?= htmlspecialchars($row['nama_divisi'] ?? 'N/A') ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $row['total_items'] ?> item</small></td>
                                                        <td><small class="font-weight-bold">Rp <?= number_format($row['total_nominal_detail'], 0, ',', '.') ?></small></td>
                                                        <td>
                                                            <?php
                                                            $badge_class = 'secondary';
                                                            switch ($row['status']) {
                                                                case 'Leader':
                                                                    $badge_class = 'primary';
                                                                    break;
                                                                case 'Kacab':
                                                                    $badge_class = 'info';
                                                                    break;
                                                                case 'Manager':
                                                                    $badge_class = 'info';
                                                                    break;
                                                                case 'Finance':
                                                                    $badge_class = 'warning';
                                                                    break;
                                                                case 'Direktur':
                                                                    $badge_class = 'secondary';
                                                                    break;
                                                                case 'Uang Muka':
                                                                    $badge_class = 'success';
                                                                    break;
                                                                case 'Done': // DB legacy
                                                                case 'Selesai':
                                                                    $badge_class = 'success';
                                                                    break;
                                                                case 'Revisi':
                                                                    $badge_class = 'danger badge-urgent';
                                                                    break;
                                                                case 'Ditolak':
                                                                    $badge_class = 'danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <?php $display_status = $status_labels[$row['status']] ?? $row['status']; ?>
                                                            <span class="badge badge-<?= $badge_class ?> badge-sm">
                                                                <?= htmlspecialchars($display_status) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <!-- Detail Button - semua bisa lihat -->
                                                                <a href="detail.php?no_ppx=<?= urlencode($row['no_ppx']) ?>"
                                                                    class="btn btn-outline-info btn-action" title="Detail">
                                                                    <i class="fas fa-eye fa-xs"></i>
                                                                </a>

                                                                <!-- Print -->
                                                                <?php if ($auth->hasPermission('ppx', 'read')): ?>
                                                                    <a href="print_laporan.php?no_ppx=<?= urlencode($row['no_ppx']) ?>"
                                                                        class="btn btn-outline-info btn-action" title="Print Detail">
                                                                        <i class="fas fa-print fa-xs"></i>
                                                                    </a>
                                                                <?php endif; ?>

                                                                <!-- Realisasi: tampil hanya untuk pengaju jika status = Uang Muka (belum selesai realisasi) -->
                                                                <?php 
                                                                $status_trim = trim($row['status']);
                                                                $is_owner = ($row['user_id'] == $_SESSION['id_user']);
                                                                $can_realisasi = ($status_trim === 'Uang Muka' && $is_owner);
                                                                ?>
                                                                <?php if ($can_realisasi): ?>
                                                                    <a href="realisasi.php?no_ppx=<?= urlencode($row['no_ppx']) ?>"
                                                                        class="btn btn-outline-primary btn-action"
                                                                        title="Realisasi">
                                                                        <i class="fas fa-file-invoice-dollar fa-xs"></i>
                                                                    </a>
                                                                <?php endif; ?>

                                                                <!-- Edit/Delete - hanya owner -->
                                                                <?php if ($auth->canCreatePPX() && $row['user_id'] == $_SESSION['id_user']): ?>
                                                                    <?php if (strtolower(trim($row['status'])) === 'revisi'): ?>
                                                                        <a href="edit.php?no_ppx=<?= urlencode($row['no_ppx']) ?>"
                                                                            class="btn btn-outline-danger btn-action" title="Revisi">
                                                                            <i class="fas fa-edit fa-xs"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php 
                                                                    $is_revisi  = strtolower(trim($row['status'])) === 'revisi';
                                                                    $is_initial = ($row['status'] === $row['route_initial_status']);
                                                                    // Izinkan hapus juga jika status sama persis dengan route_initial_status
                                                                    // atau jika status mengandung kata kunci tahap awal (fallback keamanan)
                                                                    $first_stage_keywords = ['asmen finance','leader','kacab','hr','operasional'];
                                                                    $status_lower = strtolower(trim($row['status']));
                                                                    $is_first_stage = in_array($status_lower, $first_stage_keywords);
                                                                    $can_delete = $is_revisi || $is_initial || $is_first_stage;
                                                                    ?>
                                                                    <button type="button" 
                                                                        class="btn btn-action <?= $can_delete ? 'btn-outline-danger' : 'btn-outline-secondary disabled' ?>" 
                                                                        title="<?= $can_delete ? 'Hapus Pengajuan' : 'Tidak dapat dihapus (Sudah diproses)' ?>"
                                                                        <?= $can_delete ? "onclick=\"deleteRequest('".htmlspecialchars($row['no_ppx'])."')\"" : "disabled" ?>>
                                                                        <i class="fas fa-trash fa-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: Realisasi Uang Muka -->
                <div class="modal fade" id="realisasiModal" tabindex="-1" role="dialog" aria-labelledby="realisasiModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form id="realisasiForm" action="realisasi_submit.php" method="POST" enctype="multipart/form-data">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="realisasiModalLabel"><i class="fas fa-file-invoice-dollar"></i> Realisasi Uang Muka</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="no_ppx" id="realisasi_no_ppx" value="">

                                    <!-- Upload Bukti Realisasi -->
                                    <div class="form-group">
                                        <label for="realisasi_bukti_realisasi">
                                            <i class="fas fa-receipt"></i> Upload Bukti Realisasi (foto/scan / PDF)
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="file" class="form-control" id="realisasi_bukti_realisasi" name="bukti_realisasi[]" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Format: JPG, PNG, PDF. Maksimal 5 file. Maks ukuran per file: 5MB.
                                        </small>
                                        <div id="preview_realisasi" class="mt-2"></div>
                                    </div>

                                    <!-- Upload Bukti Nota -->
                                    <div class="form-group">
                                        <label for="realisasi_bukti_nota">
                                            <i class="fas fa-file-invoice"></i> Upload Bukti Nota (foto/scan / PDF)
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="file" class="form-control" id="realisasi_bukti_nota" name="bukti_nota[]" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Format: JPG, PNG, PDF. Maksimal 5 file. Maks ukuran per file: 5MB.
                                        </small>
                                        <div id="preview_nota" class="mt-2"></div>
                                    </div>


                                    <!-- Uang Kembali -->
                                    <div class="form-group">
                                        <label for="realisasi_uang_kembali">
                                            <i class="fas fa-hand-holding-usd"></i> Uang yang Kembali ke Finance (jika ada)
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="realisasi_uang_kembali" name="uang_kembali" placeholder="0">
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Kosongkan jika tidak ada uang kembali. Jika ada, masukkan nominal yang akan dikembalikan ke Finance.
                                        </small>
                                    </div>


                                    <!-- Info Box -->
                                    <div class="alert alert-info small">
                                        <i class="fas fa-lightbulb"></i> <strong>Catatan:</strong>
                                        <ul class="mb-0 pl-3">
                                            <li>Jika uang yang dicairkan <strong>kurang</strong>, Anda bisa mengajukan PPX baru dengan referensi nomor PPX ini.</li>
                                            <li>Jika uang <strong>kembali > 0</strong>, wajib dikembalikan ke Finance melalui transfer/tunai.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <i class="fas fa-times"></i> Batal
                                    </button>
                                    <button type="submit" name="submit_realisasi" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Realisasi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php
                if ($layout_dir && file_exists($layout_dir . '/footer.php')) {
                    include $layout_dir . '/footer.php';
                }

                ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Include required JS files -->
    <?php
    if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) {
        include $layout_dir . '/footer_js.php';
    }
    ?>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#ppxTable').DataTable({
                "order": [],
                "ordering": false,
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "zeroRecords": "Tidak ada data yang sesuai",
                    "emptyTable": "Tidak ada data pengajuan PPX",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "&raquo;",
                        "previous": "&laquo;"
                    }
                },
                "columnDefs": [{
                        "searchable": false,
                        "targets": [5, 8]
                    },
                    {
                        "orderable": false,
                        "targets": [5, 8]
                    }
                ]
            });
        });

        function showDetail(no_ppx) {
            window.location.href = 'detail.php?no_ppx=' + no_ppx;
        }

        function deleteRequest(no_ppx) {
            Swal.fire({
                title: 'Hapus Pengajuan?',
                html: 'Yakin ingin menghapus <strong>' + no_ppx + '</strong>?<br><small class="text-danger">Tindakan ini tidak dapat dibatalkan!</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.isConfirmed) {
                    fetch('delete_ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'no_ppx=' + encodeURIComponent(no_ppx)
                    })
                    .then(r => r.json())
                    .then(function(data) {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', data.message, 'error');
                        }
                    })
                    .catch(function() {
                        Swal.fire('Error!', 'Terjadi kesalahan koneksi.', 'error');
                    });
                }
            });
        }

        function openRealisasiModal(no_ppx) {
            document.getElementById('realisasi_no_ppx').value = no_ppx;
            document.getElementById('realisasiModalLabel').textContent = 'Realisasi Uang Muka - ' + no_ppx;
            document.getElementById('realisasiForm').reset();
            // Clear preview
            document.getElementById('preview_realisasi').innerHTML = '';
            document.getElementById('preview_nota').innerHTML = '';
            // keep hidden field value (reset clears it), so set again:
            document.getElementById('realisasi_no_ppx').value = no_ppx;
        }

        // Preview dan validasi file upload
        function handleFilePreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            input.addEventListener('change', function() {
                preview.innerHTML = '';
                const files = this.files;
                const maxFiles = 5;
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (files.length > maxFiles) {
                    alert('Maksimal ' + maxFiles + ' file yang dapat diupload!');
                    this.value = '';
                    return;
                }
                
                let validFiles = 0;
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    
                    // Cek ukuran file
                    if (file.size > maxSize) {
                        alert('File "' + file.name + '" terlalu besar! Maksimal 5MB per file.');
                        this.value = '';
                        preview.innerHTML = '';
                        return;
                    }
                    
                    validFiles++;
                    
                    // Tampilkan preview nama file
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'badge badge-info mr-1 mb-1';
                    fileInfo.innerHTML = '<i class="fas fa-file"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                    preview.appendChild(fileInfo);
                }
                
                // Tampilkan jumlah file
                if (validFiles > 0) {
                    const countInfo = document.createElement('div');
                    countInfo.className = 'small text-success mt-1';
                    countInfo.innerHTML = '<i class="fas fa-check-circle"></i> ' + validFiles + ' file dipilih';
                    preview.appendChild(countInfo);
                }
            });
        }
        
        // Jalankan saat document ready
        $(document).ready(function() {
            // Setup file preview
            handleFilePreview('realisasi_bukti_realisasi', 'preview_realisasi');
            handleFilePreview('realisasi_bukti_nota', 'preview_nota');
            
            // Validasi form sebelum submit
            $('#realisasiForm').on('submit', function(e) {
                const buktiRealisasi = document.getElementById('realisasi_bukti_realisasi').files;
                const buktiNota = document.getElementById('realisasi_bukti_nota').files;
                
                if (buktiRealisasi.length === 0 || buktiNota.length === 0) {
                    e.preventDefault();
                    alert('Bukti Realisasi dan Bukti Nota wajib diupload!');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>

</html>
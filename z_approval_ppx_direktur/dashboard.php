<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_direktur\dashboard.php
// Use absolute paths based on this file's directory
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// PERBAIKAN: Buat instance baru
$auth = new AuthHelper($conn);

// Tambahkan mapping label status (tampilkan "Leader" sebagai "Kacap/Manager")
$status_labels = [
    'Leader'   => 'Kacap/Manager',
    'Manager'  => 'Manager Finance',
    'Finance'  => 'GA (General Affairs)',
    'Direktur' => 'Direktur',
    'Done'     => 'Selesai',
    'Selesai'  => 'Selesai',
    'Uang Muka'=> 'Uang Muka',
    'Revisi'   => 'Revisi'
];

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - Approval PPX Direktur
$auth->requirePermission('approval_ppx_direktur', 'approve');

// Query berdasarkan role dengan JOIN ke detail
$whereClause = "";
if ($auth->isKoordinator()) {
    $whereClause = "WHERE pr.user_id = " . $_SESSION['id_user'];
}

// PERBAIKAN: Query dengan JOIN yang lebih efisien dan total nominal yang konsisten
$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi as divisi_id,
    d.nama_divisi as divisi,
    pr.departemen as departemen_id,
    dep.nama_departemen as departemen,
    pr.cabang,
    pr.status,
    pr.nominal_ppx,
    u.nama_user as nama_pengaju,
    COUNT(prd.no_ppx_detail) AS total_items,
    COALESCE(SUM(prd.total_harga), 0) AS total_nominal_detail,
    -- total_nominal: gunakan field nominal_ppx jika ada (non-zero), jika tidak gunakan total detail
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
$whereClause
GROUP BY pr.no_ppx
ORDER BY 
    FIELD(pr.status, 'Direktur','Leader','Manager','Finance','Uang Muka','Selesai','Revisi'),
    pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query dengan perbaikan
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Leader' THEN 1 END) as pending_leader,
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
    // Include required CSS files (safe check)
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    } else {
        error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/header_css.php' : __DIR__ . '/../layouts/header_css.php'));
        // Optional: fallback or show friendly message
        // echo '<!-- header_css.php not found -->';
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

        .table td, .table th {
            vertical-align: middle;
        }

        .badge-sm {
            font-size: 0.7rem;
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
            error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/navbar.php' : __DIR__ . '/../layouts/navbar.php'));
            // optional fallback: echo '<!-- navbar.php not found -->';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Approval Pengajuan PPX (Direktur)</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Pengajuan PPX (Direktur)</li>
                            </ul>

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

                <!-- Statistics Cards -->
                <!-- <div class="row mb-4">
                    <div class="col-6 col-sm-6 col-md-3 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-primary mb-1"><?= htmlspecialchars($status_labels['Leader'] ?? 'Leader') ?></h6>
                                        <h3 class="mb-0"><?= $stats['pending_leader'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-user-tie stats-icon text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-md-3 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-info mb-1"><?= htmlspecialchars($status_labels['Manager'] ?? 'Manager') ?></h6>
                                        <h3 class="mb-0"><?= $stats['pending_manager'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-user-cog stats-icon text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-md-3 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-warning mb-1">Finance</h6>
                                        <h3 class="mb-0"><?= $stats['pending_finance'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-calculator stats-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-md-3 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-secondary mb-1">Direktur</h6>
                                        <h3 class="mb-0"><?= $stats['pending_direktur'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-crown stats-icon text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-success mb-1">Uang Muka</h6>
                                        <h3 class="mb-0"><?= $stats['uang_muka'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-hand-holding-usd stats-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-success mb-1">Selesai</h6>
                                        <h3 class="mb-0"><?= $stats['approved'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-check-circle stats-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-danger mb-1">Revisi</h6>
                                        <h3 class="mb-0"><?= $stats['rejected'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-times-circle stats-icon text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan PPX
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
                                                        <td><small><?= htmlspecialchars($row['divisi']) ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $row['total_items'] ?> item</small></td>
                                                        <td><small class="font-weight-bold">Rp <?= number_format($row['total_nominal_detail'], 0, ',', '.') ?></small></td>
                                                        <td>
                                                            <?php
                                                            $badge_class = 'secondary';
                                                            switch($row['status']) {
                                                                case 'Leader': $badge_class = 'primary'; break;
                                                                case 'Manager': $badge_class = 'info'; break;
                                                                case 'Finance': $badge_class = 'warning'; break;
                                                                case 'Direktur': $badge_class = 'secondary'; break;
                                                                case 'Uang Muka': $badge_class = 'success'; break;
                                                                case 'Done': // legacy DB value
                                                                case 'Selesai': $badge_class = 'success'; break;
                                                                case 'Revisi': $badge_class = 'danger'; break;
                                                            }
                                                            ?>
                                                            <?php $display_status = $status_labels[$row['status']] ?? $row['status']; ?>
                                                            <span class="badge badge-<?= $badge_class ?> badge-sm">
                                                                <?= htmlspecialchars($display_status) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <!-- Detail Button -->
                                                                <a href="detail.php?no_ppx=<?= urlencode($row['no_ppx']) ?>" 
                                                                   class="btn btn-outline-info btn-action" title="Detail">
                                                                    <i class="fas fa-eye fa-xs"></i>
                                                                </a>

                                                                <!-- Print per PPX -->
                                                                <?php if ($auth->canReadPPX()): ?>
                                                                    <a href="print_laporan.php?no_ppx=<?= urlencode($row['no_ppx']) ?>" target="_blank"
                                                                       class="btn btn-outline-info btn-action" title="Print Detail">
                                                                        <i class="fas fa-print fa-xs"></i>
                                                                    </a>
                                                                <?php endif; ?>

                                                                <!-- Approval button - PERBAIKI KONDISI -->
                                                                <?php if ($auth->canApprovePPX() && trim($row['status']) === 'Direktur'): ?>
                                                                    <button type="button" class="btn btn-outline-success btn-action"
                                                                        onclick="processAction('<?= htmlspecialchars($row['no_ppx']) ?>')" title="Proses">
                                                                        <i class="fas fa-check fa-xs"></i>
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
                
                <?php
                if ($layout_dir && file_exists($layout_dir . '/footer.php')) {
                    include $layout_dir . '/footer.php';
                } else {
                    error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/footer.php' : __DIR__ . '/../layouts/footer.php'));
                    // optional fallback
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Include required JS files -->
    <?php
    if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) {
        include $layout_dir . '/footer_js.php';
    } else {
        error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/footer_js.php' : __DIR__ . '/../layouts/footer_js.php'));
        // optional fallback: include vendor JS CDN links here if needed
    }
    ?>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

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
                "columnDefs": [
                    // Sesuaikan indeks kolom karena kolom nomor (No.) ditambahkan di index 0
                    { "searchable": false, "targets": [5, 8] }, // Items dan Aksi tidak bisa dicari
                    { "orderable": false, "targets": [5, 8] }   // Items dan Aksi tidak bisa diurutkan
                ]
            });
        });

        function showDetail(no_ppx) {
            // Implementation for showing detail
            window.location.href = 'detail.php?no_ppx=' + no_ppx;
        }

        // Tampilkan modal konfirmasi sebelum submit approval
        function processAction(no_ppx) {
            // simpan no_ppx di data modal dan tampilkan
            $('#confirmApproveModal').data('no_ppx', no_ppx).modal('show');
        }

        function deleteRequest(no_ppx) {
            if (confirm('Apakah Anda yakin ingin menghapus pengajuan PPX ini?\n\nPeringatan: Data yang sudah dihapus tidak dapat dikembalikan!')) {
                window.location.href = 'delete.php?id=' + no_ppx;
            }
        }

        // Saat tombol YA di modal diklik, submit form hidden ke process_approve.php
        $(function() {
            $('#confirmApproveYes').on('click', function() {
                var no_ppx = $('#confirmApproveModal').data('no_ppx');
                if (!no_ppx) return;
                $('#no_pengajuan_input').val(no_ppx);
                $('#approveForm').submit();
            });
        });
    </script>

    <!-- Hidden form untuk submit approval -->
    <form id="approveForm" method="POST" action="process_approve.php" style="display:none;">
        <input type="hidden" name="no_pengajuan" id="no_pengajuan_input" value="">
        <input type="hidden" name="action" value="approve">
    </form>

    <!-- Konfirmasi Modal -->
    <div class="modal fade" id="confirmApproveModal" tabindex="-1" role="dialog" aria-labelledby="confirmApproveLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmApproveLabel">Konfirmasi Persetujuan</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            Apakah Anda yakin ingin menyetujui (approve) pengajuan ini?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tidak</button>
            <button type="button" id="confirmApproveYes" class="btn btn-success">Ya, Setujui</button>
          </div>
        </div>
      </div>
    </div>

    <script>
        // Fallback: jika tombol dengan data-dismiss="modal" tidak menutup modal (mis. bootstrap.js belum di-load),
        // paksa tutup modal dan hapus backdrop / class body.
        $(document).on('click', '#confirmApproveModal [data-dismiss="modal"]', function(e){
            var $m = $('#confirmApproveModal');

            // Jika bootstrap modal plugin ada, gunakan API resminya
            if (typeof $m.modal === 'function') {
                try {
                    $m.modal('hide');
                    return;
                } catch (err) {
                    // fallback di bawah jika error
                }
            }

            // Fallback manual: sembunyikan modal dan hapus backdrop / class modal-open
            $m.removeClass('show').hide();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        });
    </script>
</body>

</html>
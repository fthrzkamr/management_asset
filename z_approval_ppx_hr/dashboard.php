<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_hr\dashboard.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - HR Approval PPX
$auth->requirePermission('approval_ppx_hr', 'approve');

// Mapping label status
$status_labels = [
    'Leader'   => 'Menunggu Approval HR',
    'Selesai'  => 'Selesai',
    'Revisi'   => 'Revisi',
];

// HR meng-approve PPX dari divisi IT, HR, GA dengan status='Leader'
$userCabang = mysqli_real_escape_string($conn, $_SESSION['cabang'] ?? '');

$whereClause = "WHERE pr.status IN ('Leader', 'Selesai', 'Revisi')";
// Filter divisi IT(1), GA(6), HR(7)
$whereClause .= " AND pr.divisi IN (1, 6, 7)";

if (!empty($userCabang)) {
    $whereClause .= " AND pr.cabang = '" . $userCabang . "'";
}

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
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
$whereClause
GROUP BY pr.no_ppx
ORDER BY
    FIELD(pr.status, 'Leader', 'Revisi', 'Selesai'),
    pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Approval PPX (HR)</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <!-- Include required CSS files -->
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    } else {
        error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/header_css.php' : __DIR__ . '/../layouts/header_css.php'));
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
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Approval Pengajuan PPX (HR)</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Pengajuan PPX (IT / HR / GA)</li>
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

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan PPX (IT / HR / GA)
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
                                                <th width="14%">Aksi</th>
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
                                                            $st = trim($row['status']);
                                                            $badge_class = 'secondary';
                                                            switch ($st) {
                                                                case 'Leader':
                                                                    $badge_class = 'primary';
                                                                    break;
                                                                case 'Revisi':
                                                                    $badge_class = 'danger';
                                                                    break;
                                                                case 'Selesai':
                                                                    $badge_class = 'success';
                                                                    break;
                                                            }
                                                            ?>
                                                            <?php $display_status = $status_labels[$st] ?? $st; ?>
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

                                                                <!-- Approve Button - hanya jika status = Leader -->
                                                                <?php if ($st === 'Leader'): ?>
                                                                    <button type="button" class="btn btn-outline-success btn-action"
                                                                        onclick="processAction('<?= htmlspecialchars($row['no_ppx']) ?>')"
                                                                        title="Approve PPX">
                                                                        <i class="fas fa-check fa-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">
                                                        <div class="py-3">
                                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                            <p class="text-muted">Tidak ada data pengajuan PPX</p>
                                                        </div>
                                                    </td>
                                                </tr>
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
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
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

        // Tampilkan modal konfirmasi sebelum approve
        function processAction(no_ppx) {
            $('#confirmApproveModal').data('no_ppx', no_ppx).modal('show');
        }

        // Submit form saat tombol YA diklik
        $(function() {
            $('#confirmApproveYes').on('click', function() {
                var no_ppx = $('#confirmApproveModal').data('no_ppx');
                if (!no_ppx) return;
                $('#no_pengajuan_input').val(no_ppx);
                $('#approveForm').submit();
            });
        });

        // Fallback close modal
        $(document).on('click', '#confirmApproveModal [data-dismiss="modal"]', function(e) {
            var $m = $('#confirmApproveModal');
            if (typeof $m.modal === 'function') {
                try {
                    $m.modal('hide');
                    return;
                } catch (err) {}
            }
            $m.removeClass('show').hide();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        });
    </script>

    <!-- Hidden form untuk submit approval -->
    <form id="approveForm" method="POST" action="process_approve.php">
        <input type="hidden" name="no_pengajuan" id="no_pengajuan_input" value="">
        <input type="hidden" name="action" value="approve">
    </form>

    <!-- Modal Konfirmasi -->
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
                    <p>Apakah Anda yakin ingin menyetujui pengajuan PPX ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="confirmApproveCancel">Tidak</button>
                    <button type="button" id="confirmApproveYes" class="btn btn-success">Ya, Setujui</button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

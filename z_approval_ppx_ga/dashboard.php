<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_ga\dashboard.php
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
    'Selesai'     => 'Selesai',
    'Uang Muka'     => 'Uang Muka',
    'Revisi'   => 'Revisi'
];

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - GA Approval PPX
$auth->requirePermission('approval_ppx_ga', 'approve');

// Query berdasarkan role dengan JOIN ke detail
// GA hanya meng-approve PPX dari divisi HR, GA, atau IT
// PPX dari divisi lain langsung ke Finance (tidak ditampilkan di GA)
// Tampilkan status Leader, Manager, Finance, Direktur, Selesai, Uang Muka, Selesai Realisasi
$whereClause = "WHERE pr.status IN ('Leader', 'Manager', 'Finance', 'Direktur', 'Selesai', 'Uang Muka', 'Selesai Realisasi', 'Realisasi')";

// PERBAIKAN: Filter hanya divisi HR, GA, IT untuk role GA
// Untuk role Finance/Finance AP, tampilkan semua kecuali divisi HR, GA, IT
// Batasi juga berdasarkan cabang user agar tiap user hanya melihat pengajuan di cabangnya
$userCabang = mysqli_real_escape_string($conn, $_SESSION['cabang'] ?? '');

if ($auth->isGA()) {
    // GA hanya lihat divisi HR, GA, IT
    $whereClause .= " AND (d.nama_divisi IN ('HR', 'GA', 'IT') OR d.nama_divisi LIKE '%Human%' OR d.nama_divisi LIKE '%General%' OR d.nama_divisi LIKE '%Information%')";
    // Pastikan Gudang(2), Bisnis(4), Operasional(17) tidak muncul di dashboard GA
    $whereClause .= " AND pr.divisi NOT IN (2,4,17)";
    if (!empty($userCabang)) {
        $whereClause .= " AND pr.cabang = '" . $userCabang . "'";
    }
} elseif ($auth->isFinance() || $auth->isFinanceAP()) {
    // Finance/Finance AP lihat semua divisi KECUALI HR, GA, IT
    $whereClause .= " AND (d.nama_divisi NOT IN ('HR', 'GA', 'IT') AND d.nama_divisi NOT LIKE '%Human%' AND d.nama_divisi NOT LIKE '%General%' AND d.nama_divisi NOT LIKE '%Information%')";
    // Pastikan Gudang(2), Bisnis(4), Operasional(17) tidak muncul di dashboard Finance (ditangani oleh Manager Operasional)
    $whereClause .= " AND pr.divisi NOT IN (2,4,17)";
    if (!empty($userCabang)) {
        $whereClause .= " AND pr.cabang = '" . $userCabang . "'";
    }
}

if ($auth->isKoordinator()) {
    $whereClause .= " AND pr.user_id = " . $_SESSION['id_user'];
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
    pr.nama_bank,
    pr.nama_rek,
    pr.no_rek,
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
    FIELD(pr.status, 'Finance','Selesai Realisasi','Uang Muka','Leader','Manager','Direktur','Selesai','Revisi'),
    pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query dengan perbaikan - perlu JOIN ke divisi juga
$stats_query = "SELECT 
    COUNT(CASE WHEN pr.status = 'Leader' THEN 1 END) as pending_leader,
    COUNT(CASE WHEN pr.status = 'Manager' THEN 1 END) as pending_manager,
    COUNT(CASE WHEN pr.status = 'Finance' THEN 1 END) as pending_finance,
    COUNT(CASE WHEN pr.status = 'Direktur' THEN 1 END) as pending_direktur,
    COUNT(CASE WHEN pr.status = 'Selesai Realisasi' THEN 1 END) as selesai_realisasi,
    COUNT(CASE WHEN pr.status = 'Selesai' THEN 1 END) as approved,
    COUNT(CASE WHEN pr.status = 'Uang Muka' THEN 1 END) as uang_muka,
    COUNT(CASE WHEN pr.status = 'Revisi' THEN 1 END) as rejected
FROM ppx_request pr
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
$whereClause";

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
            // optional fallback: echo '<!-- navbar.php not found -->';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Approval Pengajuan PPX (Finance)</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Pengajuan PPX (Finance)</li>
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
                                        <h3 class="mb-0"><?= $stats['pending_leader'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['pending_manager'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['pending_finance'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['pending_direktur'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['uang_muka'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['approved'] ?></h3>
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
                                        <h3 class="mb-0"><?= $stats['rejected'] ?></h3>
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
                                                            switch ($row['status']) {
                                                                case 'Leader':
                                                                    $badge_class = 'primary';
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
                                                                case 'Selesai Realisasi':
                                                                    $badge_class = 'info';
                                                                    break;
                                                                case 'Selesai':
                                                                    $badge_class = 'success';
                                                                    break;
                                                                case 'Uang Muka':
                                                                    $badge_class = 'success';
                                                                    break;
                                                                case 'Revisi':
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

                                                                <?php
                                                                // Cek apakah semua item sudah dibayar
                                                                $check_unpaid_query = "SELECT COUNT(*) as unpaid_count 
                                                                                      FROM ppx_request_detail 
                                                                                      WHERE no_ppx = ? 
                                                                                      AND (status_tf IS NULL OR status_tf = '' OR status_tf = 'Belum')";
                                                                $stmt_check = mysqli_prepare($conn, $check_unpaid_query);
                                                                mysqli_stmt_bind_param($stmt_check, 's', $row['no_ppx']);
                                                                mysqli_stmt_execute($stmt_check);
                                                                $check_result = mysqli_stmt_get_result($stmt_check);
                                                                $check_row = mysqli_fetch_assoc($check_result);
                                                                $unpaid_count = $check_row['unpaid_count'];
                                                                mysqli_stmt_close($stmt_check);

                                                                // Approval button - hanya muncul jika:
                                                                // 1. Status Leader, Manager, Finance atau Uang Muka
                                                                // 2. Ada item yang belum dibayar
                                                                // 3. Status BUKAN Realisasi atau Selesai
                                                                $st = trim($row['status']);
                                                                $canShowProcess = $auth->canProcessPPX() && 
                                                                                in_array($st, ['Leader', 'Manager', 'Finance', 'Uang Muka'], true) && 
                                                                                $unpaid_count > 0 &&
                                                                                !in_array($st, ['Realisasi', 'Selesai']);
                                                                ?>

                                                                <?php if ($canShowProcess): ?>
                                                                    <button type="button" class="btn btn-outline-success btn-action"
                                                                        onclick="if(confirm('Apakah Anda yakin ingin meng-approve PPX ini?')) location.href='process_approve.php?no_ppx=<?= urlencode($row['no_ppx']) ?>&action=approve'" 
                                                                        title="Approve PPX">
                                                                        <i class="fas fa-check fa-xs"></i>
                                                                    </button>
                                                                <?php endif; ?>

                                                                <?php
                                                                // Button khusus untuk approve "Selesai Realisasi" menjadi "Selesai"
                                                                // Hanya muncul jika status = Selesai Realisasi
                                                                if ($auth->canProcessPPX() && $st == 'Selesai Realisasi'):
                                                                ?>
                                                                    <button type="button" class="btn btn-outline-success btn-action"
                                                                        onclick="approveSelesaiRealisasi('<?= htmlspecialchars($row['no_ppx']) ?>')" 
                                                                        title="Approve Selesai Realisasi">
                                                                        <i class="fas fa-check-circle fa-xs"></i>
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
                    // optional fallback
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Include Modal Konfirmasi -->
    <?php
    if ($layout_dir && file_exists($layout_dir . '/confirm_modal.php')) {
        include $layout_dir . '/confirm_modal.php';
    }
    ?>

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
            // DataTable init - HANYA 1 KALI
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

            // Helper functions
            function formatRupiahNumber(n) {
                n = n || 0;
                return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            function escapeHtml(s) {
                if (!s && s !== 0) return '';
                return String(s).replace(/[&<>"'`=\/]/g, function(c) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;',
                        '/': '&#x2F;',
                        '`': '&#x60;',
                        '=': '&#x3D;'
                    }[c];
                });
            }

            // Calculate selected items
            function calculateSelected() {
                var total = 0;
                $('#modal_items tbody input[type="checkbox"]:checked:not(:disabled)').each(function() {
                    var row = $(this).closest('tr');
                    var itemTotal = parseInt(row.data('total')) || 0;
                    total += itemTotal;
                });
                $('#modal_items_selected').text(formatRupiahNumber(total));
                $('#modal_nominal_tf').val(formatRupiahNumber(total));
            }

            // Check All
            $(document).on('change', '#checkAll', function() {
                var checked = $(this).prop('checked');
                $('#modal_items tbody input[type="checkbox"]:not(:disabled)').prop('checked', checked).trigger('change');
            });

            // Individual checkbox
            $(document).on('change', '#modal_items tbody input[type="checkbox"]', function() {
                var row = $(this).closest('tr');
                if ($(this).prop('checked')) {
                    row.addClass('selected-item');
                } else {
                    row.removeClass('selected-item');
                }
                calculateSelected();

                // Update checkAll
                var totalBox = $('#modal_items tbody input[type="checkbox"]:not(:disabled)').length;
                var checkedBox = $('#modal_items tbody input[type="checkbox"]:checked:not(:disabled)').length;
                $('#checkAll').prop('checked', totalBox === checkedBox && totalBox > 0);
            });

            // processAction: load detail via AJAX
            window.processAction = function(no_ppx) {
                if (!no_ppx) return;

                // Reset
                $('#modal_no_ppx, #modal_nama_pic, #modal_jabatan, #modal_divisi, #modal_cabang').text('');
                $('#modal_nama_bank, #modal_nama_rek, #modal_no_rek').text('-');
                $('#modal_items_total, #modal_items_selected').text('Rp 0');
                $('#modal_nominal_tf').val('');
                $('#modal_ket_tf').val('');
                $('#modal_upload_tf').val('');
                $('#modal_existing_uploads').empty();
                $('#modal_items tbody').empty().append('<tr><td colspan="7" class="text-center">Memuat...</td></tr>');
                $('#checkAll').prop('checked', false);

                $.getJSON('get_ppx_detail.php', {
                        no_ppx: no_ppx
                    })
                    .done(function(res) {
                        if (!res.success) {
                            alert(res.message || 'Gagal memuat data.');
                            return;
                        }

                        var d = res.data;
                        $('#confirmApproveModal').data('no_ppx', no_ppx);
                        $('#no_pengajuan_input').val(no_ppx);

                        // Fill header
                        $('#modal_no_ppx').text(d.no_ppx || '');
                        $('#modal_nama_pic').text(d.nama_pic || '');
                        $('#modal_jabatan').text(d.jabatan || '');
                        $('#modal_divisi').text(d.nama_divisi || d.divisi || '-');
                        $('#modal_cabang').text(d.cabang || '-');
                        $('#modal_nama_bank').text(d.nama_bank || '-');
                        $('#modal_nama_rek').text(d.nama_rek || '-');
                        $('#modal_no_rek').text(d.no_rek || '-');
                        $('#modal_ket_tf').val(d.ket_tf || '');

                        // Existing uploads
                        var $uploads = $('#modal_existing_uploads').empty();
                        if (d.upload_tf) {
                            var list = [];
                            try {
                                var parsed = JSON.parse(d.upload_tf);
                                if (Array.isArray(parsed)) list = parsed;
                            } catch (e) {
                                list = String(d.upload_tf).split(',').map(s => s.trim()).filter(Boolean);
                            }
                            if (list.length) {
                                $uploads.append('<small class="text-muted">File sebelumnya:</small>');
                                list.forEach(function(fn) {
                                    var url = '../uploads/ppx_tf/' + encodeURIComponent(fn);
                                    $uploads.append('<div><a href="' + url + '" target="_blank" class="small">' + escapeHtml(fn) + '</a></div>');
                                });
                            }
                        }

                        // Fill items
                        var $tb = $('#modal_items tbody').empty();
                        var sumAll = 0;

                        if (d.items && d.items.length) {
                            d.items.forEach(function(it, i) {
                                var hargaSatuan = parseInt(it.harga_satuan) || 0;
                                var totalIt = parseInt(it.total_harga) || ((parseInt(it.jumlah) || 0) * hargaSatuan);
                                sumAll += totalIt;

                                var statusTF = (it.status_tf || '').toLowerCase();
                                var isPaid = (statusTF === 'sudah' || statusTF === 'lunas');

                                var checkDisabled = isPaid ? 'disabled' : '';
                                var checkChecked = '';
                                var rowClass = isPaid ? 'paid-item' : '';
                                var statusBadge = isPaid ? ' <span class="badge badge-success badge-sm">Sudah TF</span>' : '';

                                var row = `
                                    <tr class="${rowClass}" data-total="${totalIt}">
                                        <td class="text-center">
                                            <input type="checkbox" name="checked_detail[]" value="${escapeHtml(it.no_ppx_detail)}" ${checkChecked} ${checkDisabled}>
                                        </td>
                                        <td>${i + 1}</td>
                                        <td>${escapeHtml(it.no_akun)}</td>
                                        <td>${escapeHtml(it.rincian)}${statusBadge}</td>
                                        <td class="text-center">${it.jumlah || ''} ${escapeHtml(it.satuan || '')}</td>
                                        <td class="text-right">${formatRupiahNumber(hargaSatuan)}</td>
                                        <td class="text-right font-weight-bold">${formatRupiahNumber(totalIt)}</td>
                                    </tr>
                                `;
                                $tb.append(row);
                            });
                        } else {
                            $tb.append('<tr><td colspan="7" class="text-center">Tidak ada item</td></tr>');
                        }

                        $('#modal_items_total').text(formatRupiahNumber(sumAll));
                        calculateSelected();

                        $('#confirmApproveModal').modal('show');
                    })
                    .fail(function() {
                        alert('Gagal memuat data detail.');
                    });
            };

            // Submit
            $(document).on('click', '#confirmApproveYes', function() {
                var checkedCount = $('#modal_items tbody input[type="checkbox"]:checked:not(:disabled)').length;

                if (checkedCount === 0) {
                    alert('Silakan pilih minimal 1 item yang akan dibayar!');
                    return;
                }

                if (!$('#modal_upload_tf')[0].files.length) {
                    alert('Silakan upload bukti transfer!');
                    return;
                }

                var no = $('#confirmApproveModal').data('no_ppx') || $('#no_pengajuan_input').val();
                if (!no) {
                    alert('Nomor pengajuan tidak ditemukan.');
                    return;
                }

                // Convert rupiah format to number
                var nominalTf = $('#modal_nominal_tf').val().replace(/[^\d]/g, '');
                var hiddenInput = $('<input>').attr({
                    type: 'hidden',
                    name: 'nominal_tf_value',
                    value: nominalTf
                });

                $('#modal_nominal_tf').removeAttr('name');
                $('#approveForm').append(hiddenInput);

                $('#no_pengajuan_input').val(no);
                $('#approveForm').submit();
            });

            // Close handlers
            $(document).on('click', '#confirmApproveCancel, #confirmApproveClose', function() {
                $('#confirmApproveModal').modal('hide');
            });

            // Function baru untuk approve realisasi dari dashboard
            window.approveRealisasiFromDashboard = function(no_ppx) {
                if (confirm('Apakah Anda yakin ingin meng-approve realisasi PPX ini?\n\nStatus akan berubah menjadi "Selesai".')) {
                    var formData = new FormData();
                    formData.append('action', 'approve_realisasi');
                    formData.append('no_ppx', no_ppx);
                    formData.append('catatan', 'Approved dari dashboard');

                    $.ajax({
                        url: 'approve_realisasi.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            try {
                                var result = JSON.parse(response);
                                if (result.success) {
                                    alert(result.message || 'Realisasi berhasil di-approve!');
                                    location.reload();
                                } else {
                                    alert('Error: ' + (result.message || 'Terjadi kesalahan'));
                                }
                            } catch (e) {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('Error: Gagal melakukan approve realisasi');
                        }
                    });
                }
            };
        });
    </script>

    <!-- Hidden form untuk submit approval -->
    <form id="approveForm" method="POST" action="process_approve.php" enctype="multipart/form-data">
        <input type="hidden" name="no_pengajuan" id="no_pengajuan_input" value="">
        <input type="hidden" name="action" value="approve">

        <!-- Konfirmasi Modal -->
        <div class="modal fade" id="confirmApproveModal" tabindex="-1" role="dialog" aria-labelledby="confirmApproveLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="confirmApproveLabel">Konfirmasi Persetujuan</h5>
                            <small class="text-muted">Centang item yang akan dibayar sekarang</small>
                        </div>
                        <button type="button" id="confirmApproveClose" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="row">
                                <!-- Left column: No. PPX, Nama PIC, Jabatan, Divisi/Cabang -->
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4 text-sm">No. PPX :</dt>
                                        <dd class="col-sm-8"><strong id="modal_no_ppx">-</strong></dd>

                                        <dt class="col-sm-4 text-sm">Nama PIC :</dt>
                                        <dd class="col-sm-8" id="modal_nama_pic">-</dd>

                                        <dt class="col-sm-4 text-sm">Jabatan :</dt>
                                        <dd class="col-sm-8" id="modal_jabatan">-</dd>

                                        <dt class="col-sm-4 text-sm">Divisi / Cabang :</dt>
                                        <dd class="col-sm-8"><span id="modal_divisi">-</span> / <span id="modal_cabang">-</span></dd>
                                    </dl>
                                </div>

                                <!-- Right column: Bank / Rekening details -->
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5 text-sm">Nama Bank :</dt>
                                        <dd class="col-sm-7"><span id="modal_nama_bank">-</span></dd>

                                        <dt class="col-sm-5 text-sm">Atas Nama :</dt>
                                        <dd class="col-sm-7"><span id="modal_nama_rek">-</span></dd>

                                        <dt class="col-sm-5 text-sm">No. Rekening :</dt>
                                        <dd class="col-sm-7"><span id="modal_no_rek">-</span></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Info Alert -->
                        <div class="alert alert-info alert-sm py-2 mb-2">
                            <small><i class="fas fa-info-circle"></i> <strong>Cicilan:</strong> Centang item yang akan dibayar. Item yang tidak dicentang tetap berstatus "Belum Dibayar".</small>
                        </div>

                        <div class="table-responsive" style="max-height:320px; overflow:auto;">
                            <table class="table table-sm table-bordered mb-0" id="modal_items">
                                <thead class="thead-light small">
                                    <tr>
                                        <th style="width:30px">
                                            <input type="checkbox" id="checkAll" title="Centang Semua">
                                        </th>
                                        <th style="width:30px">No</th>
                                        <th style="width:120px">No. Akun</th>
                                        <th>Rincian</th>
                                        <th style="width:100px" class="text-center">Jumlah</th>
                                        <th style="width:140px" class="text-right">Harga Satuan</th>
                                        <th style="width:140px" class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Belum memuat data</td>
                                    </tr>
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="5" class="text-right small">Total Semua:</th>
                                        <th colspan="2" class="text-right font-weight-bold small" id="modal_items_total">Rp 0</th>
                                    </tr>
                                    <tr class="table-success">
                                        <th colspan="5" class="text-right small">Yang Dibayar:</th>
                                        <th colspan="2" class="text-right font-weight-bold small text-success" id="modal_items_selected">Rp 0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Tambahkan field transfer di bawah tabel -->
                        <hr>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="modal_nominal_tf" class="mb-0"><strong>Nominal Transfer <span class="text-danger">*</span></strong></label>
                                <input type="text" class="form-control" id="modal_nominal_tf" name="nominal_tf" placeholder="Otomatis terhitung" readonly>
                                <small class="form-text text-muted">Otomatis dari item yang dipilih</small>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="modal_ket_tf" class="mb-0"><strong>Keterangan Transfer</strong></label>
                                <input type="text" class="form-control" id="modal_ket_tf" name="ket_tf" placeholder="Contoh: Pembayaran 1 dari 3">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="modal_upload_tf" class="mb-0"><strong>Upload Bukti Transfer <span class="text-danger">*</span></strong></label>
                                <input type="file" class="form-control-file" id="modal_upload_tf" name="upload_tf[]"
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple required>
                                <div id="modal_existing_uploads" class="mt-1"></div>
                                <small class="form-text text-muted">Maks 5 file (JPG, PNG, PDF, DOCX, XLSX)</small>
                            </div>
                        </div>

                        <div class="mt-2 small text-muted">
                            <i class="fas fa-exclamation-triangle text-warning"></i> 
                            Pastikan item dan nominal sudah sesuai. Mengklik "Ya, Setujui" akan memproses pembayaran.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" id="confirmApproveCancel" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="button" id="confirmApproveYes" class="btn btn-success">Ya, Setujui</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <style>
        #modal_items tbody tr.selected-item {
            background-color: #d4edda !important;
        }
        #modal_items tbody tr.paid-item {
            background-color: #e9ecef;
            opacity: 0.7;
        }
    </style>

    <script>
        // Function untuk approve selesai realisasi menjadi selesai
        function approveSelesaiRealisasi(no_ppx) {
            showConfirm(
                'Approve Realisasi',
                'Approve realisasi untuk PPX ' + no_ppx + '?<br><br>Status akan diubah menjadi <strong>"Selesai"</strong>.',
                function() {
                    // User klik Ya - kirim AJAX request
                    var formData = new FormData();
                    formData.append('no_ppx', no_ppx);
                    formData.append('action', 'approve');
                    
                    fetch('process_approve.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            alert(data.message || 'Berhasil!');
                            window.location.href = 'dashboard.php';
                        } else {
                            alert('Error: ' + (data.message || 'Terjadi kesalahan'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat memproses approval');
                    });
                },
                function() {
                    // User klik Tidak - tidak ada aksi
                }
            );
        }
    </script>

</body>
</html>
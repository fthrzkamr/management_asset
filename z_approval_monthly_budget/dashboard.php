<?php
// Use absolute paths based on this file's directory
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - hanya Manager Finance dan Super Admin
if (!$auth->isManagerFinance() && !$auth->isSuperAdmin()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk halaman ini!';
    header("Location: ../index.php");
    exit();
}

// Query untuk mengambil data monthly budget dengan status yang perlu di-approve
$query = "SELECT 
    mb.id_budget,
    mb.master_id,
    mb.period_year,
    mb.period_month,
    mb.budget_name,
    mb.tipe_anggaran,
    mb.total_allocation,
    mb.master_allocation,
    mb.total_remaining,
    mb.status,
    mb.created_at,
    mb.created_by,
    mb.notes,
    u.nama_user as creator_name,
    COUNT(mbi.id_budget_item) as total_items
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN ppx_monthly_budget_item mbi ON mb.id_budget = mbi.id_budget
WHERE mb.status IN ('Pending', 'Approved', 'Rejected')
GROUP BY mb.id_budget
ORDER BY 
    FIELD(mb.status, 'Pending', 'Approved', 'Rejected'),
    mb.created_at DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count,
    COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected_count,
    COUNT(*) as total
FROM ppx_monthly_budget
WHERE status IN ('Pending', 'Approved', 'Rejected')";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Approval Anggaran Bulanan (Manager Finance)</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <!-- Include required CSS files -->
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
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
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Approval Anggaran Bulanan (Manager Finance)</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Anggaran Bulanan</li>
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
                <div class="row mb-4">
                    <div class="col-6 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-warning mb-1">Menunggu Approval</h6>
                                        <h3 class="mb-0"><?= $stats['pending_count'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-clock stats-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-success mb-1">Disetujui</h6>
                                        <h3 class="mb-0"><?= $stats['approved_count'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-check-circle stats-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-md-4 mb-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-danger mb-1">Ditolak</h6>
                                        <h3 class="mb-0"><?= $stats['rejected_count'] ?? 0 ?></h3>
                                    </div>
                                    <i class="fas fa-times-circle stats-icon text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan Anggaran Bulanan
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="budgetTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th width="4%">No.</th>
                                                <th width="10%">Periode</th>
                                                <th width="20%">Nama Budget</th>
                                                <th width="15%">Total Alokasi</th>
                                                <th width="8%">Items</th>
                                                <th width="12%">Dibuat Oleh</th>
                                                <th width="10%">Tanggal</th>
                                                <th width="12%">Tipe</th>
                                                <th width="10%">Status</th>
                                                <th width="13%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php $row_num = 1; ?>
                                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                    <tr>
                                                        <td><small class="font-weight-bold"><?= $row_num++ ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></small></td>
                                                        <td><small><?= htmlspecialchars($row['budget_name']) ?></small></td>
                                                        <td><small class="font-weight-bold">Rp <?= number_format($row['total_allocation'], 0, ',', '.') ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $row['total_items'] ?> item</small></td>
                                                        <td><small><?= htmlspecialchars($row['creator_name'] ?? '-') ?></small></td>
                                                        <td><small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small></td>
                                                        <td>
                                                            <?php
                                                            $badge_class = 'secondary';
                                                            switch ($row['status']) {
                                                                case 'Pending':
                                                                    $badge_class = 'warning';
                                                                    break;
                                                                case 'Approved':
                                                                    $badge_class = 'success';
                                                                    break;
                                                                case 'Rejected':
                                                                    $badge_class = 'danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge badge-<?= $badge_class ?> badge-sm">
                                                                <?= htmlspecialchars($row['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info badge-sm"><?= htmlspecialchars($row['tipe_anggaran'] ?? 'N/A') ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <!-- Detail Button -->
                                                                <a href="detail.php?id=<?= $row['id_budget'] ?>"
                                                                    class="btn btn-outline-info btn-action" title="Detail">
                                                                    <i class="fas fa-eye fa-xs"></i>
                                                                </a>

                                                                <!-- Print Button -->
                                                                <a href="print_laporan.php?id=<?= $row['id_budget'] ?>"
                                                                    class="btn btn-outline-info btn-action" title="Print Detail" target="_blank">
                                                                    <i class="fas fa-print fa-xs"></i>
                                                                </a>

                                                                <!-- Approval button untuk status Pending -->
                                                                <?php if ($row['status'] === 'Pending'): ?>
                                                                    <button type="button" class="btn btn-outline-success btn-action"
                                                                        onclick="processAction(<?= $row['id_budget'] ?>)" title="Proses Approval">
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
                }
                ?>
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

    <script>
        $(document).ready(function() {
            $('#budgetTable').DataTable({
                "order": [],
                "ordering": false,
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "zeroRecords": "Tidak ada data yang sesuai",
                    "emptyTable": "Tidak ada data pengajuan anggaran bulanan",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "&raquo;",
                        "previous": "&laquo;"
                    }
                },
                "columnDefs": [
                    {
                        "searchable": false,
                        "targets": [4, 8]
                    },
                    {
                        "orderable": false,
                        "targets": [4, 8]
                    }
                ]
            });
        });

        function processAction(budgetId) {
            window.location.href = 'process_approve.php?id=' + budgetId;
        }
    </script>
</body>
</html>

<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_ppx_budget\usage_report.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - read budget
if (!$auth->hasPermission('ppx_budget', 'read')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk halaman ini!';
    header("Location: ../index.php");
    exit();
}

// Get filter parameters
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : date('n'); // Default = bulan berjalan
$filter_master = isset($_GET['master_id']) ? $_GET['master_id'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : ''; // Default kosong = tampilkan semua

// Get current user's division name for debugging
$user_id = $_SESSION['id_user'];
$user_query = "SELECT d.nama_divisi 
               FROM user_dfm u 
               LEFT JOIN divisi d ON u.divisi = d.id_divisi 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['nama_divisi'];

// Build WHERE clause
$where_conditions = [];
$where_conditions[] = "mb.period_year = $filter_year";
$where_conditions[] = "mb.period_month = $filter_month";

// Filter by current user unless they are Super Admin
if (!$auth->isSuperAdmin()) {
    $where_conditions[] = "mb.created_by = $user_id";
}

if (!empty($filter_master)) {
    $where_conditions[] = "mb.master_id = '" . mysqli_real_escape_string($conn, $filter_master) . "'";
}

if (!empty($filter_status)) {
    $where_conditions[] = "mb.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get usage report data
$query = "SELECT 
    mb.id_budget,
    mb.master_id,
    mb.period_year,
    mb.period_month,
    mb.budget_name,
    mb.total_allocation,
    mb.total_remaining,
    mb.status,
    mb.created_at,
    u.nama_user as creator_name,
    mc.nama_cabang as cabang,
    COUNT(mbi.id_budget_item) as total_items,
    COALESCE(SUM(mbi.allocation), 0) as sum_allocation,
    COALESCE(SUM(mbi.allocation - mbi.remaining), 0) as sum_used,
    COALESCE(SUM(mbi.remaining), 0) as sum_remaining
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN ppx_monthly_budget_item mbi ON mb.id_budget = mbi.id_budget
$where_clause
GROUP BY mb.id_budget, mb.master_id, mb.period_year, mb.period_month, mb.budget_name, 
         mb.total_allocation, mb.total_remaining, mb.status, mb.created_at, 
         u.nama_user, mc.nama_cabang
ORDER BY mb.period_year DESC, mb.period_month DESC, mb.created_at DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Calculate totals - HANYA bulan berjalan dan status Active
$total_allocation = 0;
$total_used = 0;
$total_remaining = 0;
$budgets = [];

$current_month = date('n'); // 1-12
$current_year = date('Y');

while ($row = mysqli_fetch_assoc($result)) {
    // Use actual calculated values from items, not budget table values
    $budgets[] = $row;
    
    // Hanya hitung untuk summary card jika bulan berjalan dan status Active
    if (intval($row['period_month']) == $current_month && 
        intval($row['period_year']) == $current_year && 
        $row['status'] == 'Active') {
        $total_allocation += floatval($row['sum_allocation']);
        $total_used += floatval($row['sum_used']);
        $total_remaining += floatval($row['sum_remaining']);
    }
}

$usage_percentage = $total_allocation > 0 ? ($total_used / $total_allocation) * 100 : 0;

// Get available master budgets for filter
$master_query = "SELECT DISTINCT master_id FROM ppx_monthly_budget ORDER BY master_id";
$master_result = mysqli_query($conn, $master_query);
$master_ids = [];
while ($row = mysqli_fetch_assoc($master_result)) {
    $master_ids[] = $row['master_id'];
}

// Month names
$month_names = ['Semua Bulan', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

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
    <title>Laporan Penggunaan Anggaran</title>
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
        .summary-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .summary-card.allocation {
            border-left-color: #007bff;
        }

        .summary-card.used {
            border-left-color: #ffc107;
        }

        .summary-card.remaining {
            border-left-color: #28a745;
        }

        .summary-card.percentage {
            border-left-color: #17a2b8;
        }

        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .badge-usage {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
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
                        <div class="col">
                            <h3 class="page-title">Laporan Penggunaan Anggaran</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                                <li class="breadcrumb-item active">Laporan Penggunaan</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Filter Card -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card filter-card">
                            <div class="card-body">
                                <form method="GET" action="" class="form-inline">
                                    <div class="form-group mr-2 mb-2">
                                        <label class="mr-2">Tahun:</label>
                                        <select name="year" class="form-control">
                                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                                <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="form-group mr-2 mb-2">
                                        <label class="mr-2">Bulan:</label>
                                        <select name="month" class="form-control">
                                            <?php for ($idx = 1; $idx <= 12; $idx++): ?>
                                                <option value="<?= $idx ?>" <?= $filter_month == $idx ? 'selected' : '' ?>><?= $month_names[$idx] ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="form-group mr-2 mb-2">
                                        <label class="mr-2">Master Budget:</label>
                                        <select name="master_id" class="form-control">
                                            <option value="">Semua Master</option>
                                            <?php foreach ($master_ids as $mid): ?>
                                                <option value="<?= htmlspecialchars($mid) ?>" <?= $filter_master == $mid ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($mid) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group mr-2 mb-2">
                                        <label class="mr-2">Status:</label>
                                        <select name="status" class="form-control">
                                            <option value="">Semua</option>
                                            <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Approved" <?= $filter_status == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="Active" <?= $filter_status == 'Active' ? 'selected' : '' ?>>Active</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary mb-2">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="usage_report.php" class="btn btn-secondary ml-2 mb-2">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card summary-card allocation">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Alokasi</h6>
                                        <h4 class="mb-0 text-primary">Rp <?= number_format($total_allocation, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-wallet fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card summary-card used">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Terpakai</h6>
                                        <h4 class="mb-0 text-warning">Rp <?= number_format($total_used, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-arrow-down fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card summary-card remaining">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Sisa</h6>
                                        <h4 class="mb-0 text-success">Rp <?= number_format($total_remaining, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card summary-card percentage">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Persentase Terpakai</h6>
                                        <h4 class="mb-0 text-info"><?= number_format($usage_percentage, 2) ?>%</h4>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-chart-pie fa-2x"></i>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 8px;">
                                    <?php
                                    $bar_color = $usage_percentage < 50 ? 'success' : ($usage_percentage < 80 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress-bar bg-<?= $bar_color ?>" role="progressbar" style="width: <?= $usage_percentage ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Report Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table"></i> Detail Penggunaan Anggaran (<?= count($budgets) ?> Budget)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="usageTable" class="table table-striped table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th width="3%">No.</th>
                                                <th width="8%">Periode</th>
                                                <th width="10%">Master ID</th>
                                                <th width="15%">Nama Budget</th>
                                                <th width="10%">Alokasi</th>
                                                <th width="10%">Terpakai</th>
                                                <th width="10%">Sisa</th>
                                                <th width="8%">% Terpakai</th>
                                                <th width="10%">PIC</th>
                                                <th width="10%">Cabang</th>
                                                <th width="6%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($budgets) > 0): ?>
                                                <?php $no = 1; ?>
                                                <?php foreach ($budgets as $budget): ?>
                                                    <?php
                                                    $budget_usage = $budget['sum_allocation'] > 0 
                                                        ? ($budget['sum_used'] / $budget['sum_allocation']) * 100 
                                                        : 0;
                                                    $badge_class = $budget_usage < 50 ? 'success' : ($budget_usage < 80 ? 'warning' : 'danger');
                                                    ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
                                                        <td><strong><?= htmlspecialchars($budget['master_id']) ?></strong></td>
                                                        <td>
                                                            <a href="dashboard.php#budget-<?= $budget['id_budget'] ?>">
                                                                <?= htmlspecialchars($budget['budget_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>Rp <?= number_format($budget['sum_allocation'], 0, ',', '.') ?></td>
                                                        <td class="text-warning font-weight-bold">Rp <?= number_format($budget['sum_used'], 0, ',', '.') ?></td>
                                                        <td class="text-success font-weight-bold">Rp <?= number_format($budget['sum_remaining'], 0, ',', '.') ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $badge_class ?> badge-usage">
                                                                <?= number_format($budget_usage, 1) ?>%
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($budget['creator_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($budget['cabang'] ?? '-') ?></td>
                                                        <td>
                                                            <?php if ($budget['status'] === 'Pending'): ?>
                                                                <span class="badge badge-warning">Pending</span>
                                                            <?php elseif ($budget['status'] === 'Active'): ?>
                                                                <span class="badge badge-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary"><?= $budget['status'] ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
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
            // Hanya inisialisasi DataTables jika ada data
            var tableHasData = $('#usageTable tbody tr').length > 0 && !$('#usageTable tbody tr td[colspan]').length;
            
            if (tableHasData) {
                try {
                    $('#usageTable').DataTable({
                        "order": [[1, 'desc']], // Sort by periode descending
                        "pageLength": 25,
                        "language": {
                            "search": "Cari:",
                            "lengthMenu": "Tampilkan _MENU_ data",
                            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                            "zeroRecords": "Tidak ada data yang sesuai",
                            "emptyTable": "Tidak ada data dengan filter yang dipilih",
                            "paginate": {
                                "first": "Pertama",
                                "last": "Terakhir",
                                "next": "&raquo;",
                                "previous": "&laquo;"
                            }
                        }
                    });
                } catch (e) {
                    console.error('DataTables initialization error:', e);
                }
            } else {
                console.log('Table is empty, DataTables not initialized');
            }
        });
    </script>
</body>

</html>

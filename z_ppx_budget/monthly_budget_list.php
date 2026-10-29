<?php
include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission to read budget
if (!$auth->canReadBudget()) {
    header("Location: ../index.php");
    exit();
}

// Get all monthly budgets
// Filter by division: each division only sees their own budgets
// HR, IT, GA are grouped together (can see each other's budgets)
$user_id = $_SESSION['id_user'];
$user_query = "SELECT d.nama_divisi, u.id_cabang, mc.nama_cabang 
               FROM user_dfm u 
               LEFT JOIN divisi d ON u.divisi = d.id_divisi 
               LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['nama_divisi'];
$user_cabang_id = isset($user_data['id_cabang']) ? intval($user_data['id_cabang']) : 0;
$user_cabang_name = $user_data['nama_cabang'] ?? '';

// load cabang list for filter dropdown
$cabang_list = [];
$cabang_q = mysqli_query($conn, "SELECT id_cabang, nama_cabang FROM master_cabang ORDER BY nama_cabang");
if ($cabang_q) {
    while ($r = mysqli_fetch_assoc($cabang_q)) $cabang_list[] = $r;
}

// read filter from request
$filter_cabang = isset($_GET['filter_cabang']) ? intval($_GET['filter_cabang']) : 0;

$user_role = strtoupper($auth->getUserRole() ?? ($_SESSION['nama_role'] ?? ''));
$is_superadmin = $auth->isSuperAdmin();
$is_finance = $auth->isFinance() || (strpos($user_role, 'FINANCE') !== false);
$is_manager = (strpos($user_role, 'MANAGER') !== false);
$is_privileged = $is_superadmin || $is_finance || $is_manager;

// Filter conditions
$where_conditions = [];

if ($is_superadmin) {
    // Super Admin dapat melihat seluruh budget bulanan lintas divisi
} elseif ($is_privileged) {
    // Finance dan Manager: Finance melihat semua, Manager melihat divisi terkait
    if (!$is_finance) {
        if (stripos($user_division, 'HR') !== false || stripos($user_division, 'IT') !== false || stripos($user_division, 'GA') !== false) {
            $where_conditions[] = "creator_div_name.id_divisi IN (1, 6, 7)";
        } elseif (stripos($user_division, 'Finance AP') !== false) {
            $where_conditions[] = "creator_div_name.id_divisi = 9";
        } elseif (!empty($user_division)) {
            $where_conditions[] = "creator_div_name.nama_divisi = '" . mysqli_real_escape_string($conn, $user_division) . "'";
        }
    }
} else {
    // User reguler: hanya melihat budget yang dibuat sendiri
    $where_conditions[] = "mb.created_by = " . intval($user_id);
}

// Filter cabang jika dipilih
if (!empty($filter_cabang)) {
    $where_conditions[] = "u.id_cabang = " . intval($filter_cabang);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";


$query = "SELECT 
    mb.id_budget,
    mb.period_year,
    mb.period_month,
    CONCAT(mb.period_year, '-', LPAD(mb.period_month, 2, '0')) as period,
    mb.budget_name,
    mb.total_allocation,
    mb.total_used,
    mb.total_remaining,
    ROUND((mb.total_used / mb.total_allocation * 100), 2) as usage_percentage,
    mb.status,
    mb.approved_by,
    mb.tipe_anggaran,
    u.nama_user as created_by_name,
    mb.created_at
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
LEFT JOIN master_cabang creator_cabang ON u.id_cabang = creator_cabang.id_cabang
$where_clause
ORDER BY mb.period_year DESC, mb.period_month DESC";

$result = mysqli_query($conn, $query);
// Read flash messages from query string (created by create/insert redirects)
$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Budget Bulanan - DFM Asset</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
</head>

<body>
    <?php include_once(__DIR__ . '/../layouts/navbar.php'); ?>

    <div class="page-wrapper">
        <div class="content container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title">Kelola Budget Bulanan</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                            <li class="breadcrumb-item active">Kelola Budget</li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <a href="monthly_budget_create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Buat Budget Baru
                        </a>
                    </div>
                </div>
            </div>
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <?= htmlspecialchars($success_msg) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <?= htmlspecialchars($error_msg) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Budget List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="fas fa-list"></i> Daftar Budget Bulanan</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="budgetTable">
                                    <thead>
                                        <tr>
                                            <th>Periode</th>
                                            <th>Nama Budget</th>
                                            <th>Total Alokasi</th>
                                            <th>Terpakai</th>
                                            <th>Sisa</th>
                                            <th>Status</th>
                                            <th>Tipe</th>
                                            <th>Dibuat Oleh</th>
                                            <th class="text-center" style="width:120px; min-width:110px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td data-order="<?= $row['period'] ?>"><?= date('F Y', strtotime($row['period'] . '-01')) ?></td>
                                                    <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                                    <td>Rp <?= number_format($row['total_allocation'], 0, ',', '.') ?></td>
                                                    <td class="text-danger">Rp <?= number_format($row['total_used'], 0, ',', '.') ?></td>
                                                    <td class="text-success">Rp <?= number_format($row['total_remaining'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class_map = [
                                                            'Draft' => 'warning',
                                                            'Revisi' => 'danger',
                                                            'Active' => 'success',
                                                            'Closed' => 'secondary',
                                                            'Archived' => 'dark',
                                                            'Pending' => 'warning'
                                                        ];
                                                        $badge_cls = isset($status_class_map[$row['status']]) ? $status_class_map[$row['status']] : 'info';
                                                        ?>
                                                        <span class="badge badge-<?= htmlspecialchars($badge_cls) ?>">
                                                            <?= htmlspecialchars($row['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info"><?= htmlspecialchars($row['tipe_anggaran'] ?? 'N/A') ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                                                    <td class="text-center text-nowrap" onclick="event.stopPropagation();">
                                                        <div class="d-inline-flex" style="gap: 4px;">
                                                            <a href="monthly_budget_detail.php?id=<?= $row['id_budget'] ?>"
                                                                class="btn btn-sm btn-info"
                                                                title="Detail"
                                                                onclick="event.stopPropagation();">
                                                                <i class="fas fa-eye"></i>
                                                            </a>

                                                            <?php $can_edit = empty($row['approved_by']) || $row['status'] === 'Revisi'; ?>
                                                            <?php if (!$can_edit): ?>
                                                                <a href="javascript:void(0);"
                                                                   class="btn btn-sm btn-warning disabled"
                                                                   title="Tidak dapat diedit — sudah disetujui atau sedang dalam proses"
                                                                   onclick="event.stopPropagation(); return false;">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="monthly_budget_edit.php?id=<?= $row['id_budget'] ?>"
                                                                   class="btn btn-sm btn-warning"
                                                                   title="Edit Anggaran"
                                                                   onclick="event.stopPropagation();">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                            <?php endif; ?>

                                                            <?php if ($row['status'] === 'Revisi'): ?>
                                                                <a href="revisi_submit.php?id=<?= $row['id_budget'] ?>"
                                                                   class="btn btn-sm btn-success"
                                                                   title="Submit Ulang Anggaran ke Manager Finance"
                                                                   onclick="event.stopPropagation();">
                                                                    <i class="fas fa-paper-plane"></i>
                                                                </a>
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
            <?php include_once(__DIR__ . '/../layouts/footer.php'); ?>

        </div>
    </div>


    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#budgetTable').DataTable({
                "order": [
                    [0, "desc"]
                ],
                "pageLength": 10,
                "lengthMenu": [
                    [5, 10, 25, 50, -1],
                    [5, 10, 25, 50, "Semua"]
                ],
                "language": {
                    "emptyTable": "Tidak ada data yang tersedia",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 entri",
                    "infoFiltered": "(disaring dari _MAX_ total entri)",
                    "lengthMenu": "Tampilkan _MENU_ entri",
                    "loadingRecords": "Memuat...",
                    "processing": "Memproses...",
                    "search": "Cari:",
                    "zeroRecords": "Tidak ditemukan data yang sesuai",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "&raquo;",
                        "previous": "&laquo;"
                    }
                }
            });
        });
        // No custom handler needed for Edit button; direct link used.
    </script>
</body>

</html>
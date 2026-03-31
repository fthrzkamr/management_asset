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

// Get budget ID
$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget == 0) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Get current user's division name for filtering
$user_id = $_SESSION['id_user'];
$user_query = "SELECT d.nama_divisi 
               FROM user_dfm u 
               LEFT JOIN divisi d ON u.divisi = d.id_divisi 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['nama_divisi'];

// Get budget detail with division filtering
$budget_query = "SELECT 
    mb.*,
    u.nama_user as created_by_name,
    creator_div_name.nama_divisi as creator_division
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $budget_query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$budget) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Check if user can access this budget based on division
$can_access = false;
$creator_division = $budget['creator_division'];

if (stripos($user_division, 'HR') !== false || stripos($user_division, 'IT') !== false || stripos($user_division, 'GA') !== false) {
    // HR, IT, GA can see each other's budgets
    if (stripos($creator_division, 'HR') !== false || stripos($creator_division, 'IT') !== false || stripos($creator_division, 'GA') !== false) {
        $can_access = true;
    }
} else if (stripos($user_division, 'Finance AP') !== false) {
    // Finance AP can only see Finance AP budgets
    if (stripos($creator_division, 'Finance AP') !== false) {
        $can_access = true;
    }
} else if (stripos($user_division, 'Finance') !== false) {
    // Finance can see Finance and Finance AR budgets
    if (stripos($creator_division, 'Finance') !== false) {
        $can_access = true;
    }
} else {
    // Other divisions can only see their own
    if ($creator_division == $user_division) {
        $can_access = true;
    }
}

if (!$can_access) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Get item-level details grouped by category
$items_query = "SELECT d.*, bc.category_name, bc.category_code
FROM ppx_monthly_budget_item_detail d
LEFT JOIN ppx_budget_category bc ON d.id_category = bc.id_category
WHERE d.id_budget = ?
ORDER BY bc.category_name, d.id";

$stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Get usage log for this budget
$log_query = "SELECT 
    ul.id_log,
    ul.no_ppx,
    ul.usage_amount,
    ul.source_period,
    ul.is_carryover,
    ul.transaction_type,
    ul.created_at,
    bc.category_name,
    u.nama_user as created_by_name
FROM ppx_budget_usage_log ul
JOIN ppx_monthly_budget_item mbi ON ul.id_budget_item = mbi.id_budget_item
JOIN ppx_budget_category bc ON mbi.id_category = bc.id_category
LEFT JOIN user_dfm u ON ul.created_by = u.id_user
WHERE mbi.id_budget = ?
ORDER BY ul.created_at DESC
LIMIT 50";

$stmt = mysqli_prepare($conn, $log_query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$log_result = mysqli_stmt_get_result($stmt);

$period_display = date('F Y', strtotime($budget['period_year'] . '-' . $budget['period_month'] . '-01'));
?>

<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Detail Budget: <?= $period_display ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                        <li class="breadcrumb-item"><a href="monthly_budget_list.php">Kelola Budget</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="monthly_budget_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Budget Summary -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-info-circle"></i> Informasi Budget</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Periode</th>
                                        <td><?= $period_display ?></td>
                                    </tr>
                                    <tr>
                                        <th>Nama Budget</th>
                                        <td><?= htmlspecialchars($budget['budget_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tipe Anggaran</th>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($budget['tipe_anggaran'] ?? 'N/A') ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php
                                            $status_class_map = [
                                                'Draft' => 'warning',
                                                'Active' => 'success',
                                                'Closed' => 'secondary',
                                                'Archived' => 'dark',
                                                'Pending' => 'warning'
                                            ];
                                            $badge_cls = isset($status_class_map[$budget['status']]) ? $status_class_map[$budget['status']] : 'secondary';
                                            ?>
                                            <span class="badge badge-<?= htmlspecialchars($badge_cls) ?> badge-lg">
                                                <?= htmlspecialchars($budget['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($budget['created_by_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Dibuat</th>
                                        <td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="stats-box">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="stats-info text-center">
                                                <h6>Total Alokasi</h6>
                                                <h4 class="text-primary">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stats-info text-center">
                                                <h6>Terpakai</h6>
                                                <h4 class="text-danger">Rp <?= number_format($budget['total_used'], 0, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stats-info text-center">
                                                <h6>Sisa</h6>
                                                <h4 class="text-success">Rp <?= number_format($budget['total_remaining'], 0, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress mt-3" style="height: 30px;">
                                        <?php
                                        $percentage = ($budget['total_allocation'] > 0) 
                                            ? ($budget['total_used'] / $budget['total_allocation'] * 100) 
                                            : 0;
                                        ?>
                                        <div class="progress-bar <?= $percentage > 80 ? 'bg-danger' : 'bg-primary' ?>" 
                                             style="width: <?= min($percentage, 100) ?>%">
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($budget['notes'])): ?>
                            <div class="alert alert-info mt-3">
                                <strong>Catatan:</strong> <?= nl2br(htmlspecialchars($budget['notes'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($budget['file_excel'])): ?>
                            <div class="mt-3">
                                <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success" target="_blank" download>
                                    <i class="fas fa-file-excel"></i> Download Lampiran Excel
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Items by Category (item-level) -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-list"></i> Detail per Kategori & Item</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kode</th>
                                        <th>Kategori</th>
                                        <th>Rincian</th>
                                        <th>Qty</th>
                                        <th>Satuan</th>
                                        <th>Jml Hari</th>
                                        <th>Jml Minggu</th>
                                        <th>Harga Satuan</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $current_category = null;
                                    $total_allocation = 0;
                                    while ($item = mysqli_fetch_assoc($items_result)):
                                        $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
                                        $total_allocation += $line_total;
                                        if ($current_category !== $item['id_category']):
                                            $current_category = $item['id_category'];
                                    ?>
                                        <tr class="table-secondary">
                                            <td colspan="10"><strong><?= htmlspecialchars($item['category_code']) ?> - <?= htmlspecialchars($item['category_name']) ?></strong></td>
                                        </tr>
                                    <?php endif; ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($item['category_code']) ?></td>
                                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                                            <td><?= htmlspecialchars($item['rincian']) ?></td>
                                            <td class="text-center"><?= intval($item['qty']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($item['satuan']) ?></td>
                                            <td class="text-center"><?= $item['jumlah_hari'] > 0 ? intval($item['jumlah_hari']) : '-' ?></td>
                                            <td class="text-center"><?= $item['jumlah_minggu'] > 0 ? intval($item['jumlah_minggu']) : '-' ?></td>
                                            <td class="text-right">Rp <?= number_format(floatval($item['harga_satuan']), 0, ',', '.') ?></td>
                                            <td class="text-right">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="9" class="text-right">Total:</th>
                                        <th class="text-right">Rp <?= number_format($total_allocation, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Log -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-history"></i> Riwayat Pemakaian</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="logTable">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>No. PPX</th>
                                        <th>Kategori</th>
                                        <th>Jumlah</th>
                                        <th>Tipe</th>
                                        <th>Diproses Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($log_result) > 0): ?>
                                        <?php while ($log = mysqli_fetch_assoc($log_result)): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                                <td>
                                                    <a href="../z_approval_ppx_finance/detail.php?no_ppx=<?= urlencode($log['no_ppx']) ?>">
                                                        <?= htmlspecialchars($log['no_ppx']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($log['category_name']) ?></td>
                                                <td class="text-danger">Rp <?= number_format($log['usage_amount'], 0, ',', '.') ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?= $log['transaction_type'] ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($log['created_by_name']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Belum ada riwayat pemakaian</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include_once('../layouts/footer.php'); ?>
    </div>
</div>


<script>
$(document).ready(function() {
    $('#logTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        }
    });
});
</script>
</html>

<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_budget_finance\detail.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - hanya Finance dan Super Admin
if (!$auth->isFinance() && !$auth->isSuperAdmin()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk halaman ini!';
    header("Location: ../index.php");
    exit();
}

$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget <= 0) {
    $_SESSION['error'] = 'ID Budget tidak valid!';
    header("Location: dashboard.php");
    exit();
}

// Use temporary variable for binding to avoid modifying $id_budget
$budget_id = $id_budget;

// Get budget data dengan join ke user dan cabang
$query = "SELECT 
    mb.id_budget,
    mb.master_id,
    mb.period_year,
    mb.period_month,
    mb.budget_name,
    mb.total_allocation,
    mb.master_allocation,
    mb.total_remaining,
    mb.status,
    mb.created_at,
    mb.created_by,
    mb.approved_by,
    mb.approved_at,
    mb.notes,
    mb.file_excel,
    u.nama_user as creator_name,
    u.departemen as creator_departemen,
    u.divisi as creator_divisi,
    mc.nama_cabang as creator_cabang,
    approver.nama_user as approver_name
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN user_dfm approver ON mb.approved_by = approver.id_user
WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $budget_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Data budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Get detailed budget items (per-item) grouped by category
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $budget_id);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

// Collect items and compute total used
$total_used = 0;
$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    // determine line total: prefer stored 'total' field if present
    $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
    $item['line_total'] = $line_total;
    $total_used += $line_total;
    $items[] = $item;
}

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Debug - print $id_budget value before HTML rendering
error_log("detail.php - id_budget before HTML: " . var_export($id_budget, true));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Anggaran Bulanan - Finance</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <!-- Include required CSS files -->
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    }
    ?>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .info-card {
            border-left: 4px solid #17a2b8;
        }

        .table-items th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge-status {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .progress-wrapper {
            margin-bottom: 0.5rem;
        }

        .progress {
            height: 25px;
        }

        .progress-bar {
            font-size: 0.875rem;
            font-weight: 600;
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
                            <h3 class="page-title">Detail Anggaran Bulanan</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Approval Realisasi</a></li>
                                <li class="breadcrumb-item active">Detail</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group" role="group">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
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

                <!-- Budget Info Card -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card info-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0 text-white">
                                    <i class="fas fa-info-circle"></i> Informasi Budget
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <th width="40%" class="text-muted">Periode</th>
                                                <td><strong><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Nama Budget</th>
                                                <td><?= htmlspecialchars($budget['budget_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Master Alokasi</th>
                                                <td>Rp <?= number_format($budget['master_allocation'], 0, ',', '.') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Dibuat Oleh</th>
                                                <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Cabang</th>
                                                <td><?= htmlspecialchars($budget['creator_cabang'] ?? '-') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Tanggal Dibuat</th>
                                                <td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <th width="40%" class="text-muted">Total Alokasi</th>
                                                <td><strong class="text-primary">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Total Terpakai</th>
                                                <td><strong class="text-warning">Rp <?= number_format($total_used, 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Total Sisa</th>
                                                <td><strong class="text-success">Rp <?= number_format($budget['total_remaining'], 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Status</th>
                                                <td>
                                                    <?php if ($budget['status'] === 'Pending'): ?>
                                                        <span class="badge badge-warning badge-status">Menunggu Realisasi</span>
                                                    <?php elseif ($budget['status'] === 'Active'): ?>
                                                        <span class="badge badge-success badge-status">Aktif</span>
                                                    <?php elseif ($budget['status'] === 'Rejected'): ?>
                                                        <span class="badge badge-danger badge-status">Ditolak</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary badge-status"><?= $budget['status'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php if ($budget['approved_by']): ?>
                                                <tr>
                                                    <th class="text-muted">Disetujui Oleh</th>
                                                    <td><?= htmlspecialchars($budget['approver_name']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-muted">Tanggal Approval</th>
                                                    <td><?= date('d/m/Y H:i', strtotime($budget['approved_at'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>

                                        <?php if ($budget['notes']): ?>
                                            <div class="alert alert-info mt-2 mb-0">
                                                <small><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($budget['notes'])) ?></small>
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
                    </div>
                </div>

                <!-- Budget Items -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0 text-white">
                                    <i class="fas fa-list"></i> Rincian Item Budget (<?= count($items) ?> Item)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="bg-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="10%">Kode</th>
                                                <th width="25%">Kategori</th>
                                                <th>Rincian</th>
                                                <th width="8%">Qty</th>
                                                <th width="8%">Satuan</th>
                                                <th width="15%">Harga Satuan</th>
                                                <th width="15%">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($items) > 0): ?>
                                                <?php
                                                $no = 1;
                                                $current_category = null;
                                                $total_allocation = 0;
                                                foreach ($items as $item):
                                                    $line_total = isset($item['line_total']) ? floatval($item['line_total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
                                                    $total_allocation += $line_total;
                                                    if ($current_category !== $item['id_category']):
                                                        $current_category = $item['id_category'];
                                                ?>
                                                    <tr class="table-secondary">
                                                        <td colspan="8"><strong><?= htmlspecialchars($item['category_code'] ?? '') ?></strong></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><?= htmlspecialchars($item['category_code'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($item['category_name'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($item['rincian'] ?? '-') ?></td>
                                                        <td class="text-center"><?= intval($item['qty'] ?? 0) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                                                        <td class="text-right">Rp <?= number_format(floatval($item['harga_satuan'] ?? 0), 0, ',', '.') ?></td>
                                                        <td class="text-right">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                        <p class="text-muted">Tidak ada item budget</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="bg-light">
                                            <tr>
                                                <th colspan="6" class="text-right">Total:</th>
                                                <th></th>
                                                <th>Rp <?= number_format($total_allocation ?? 0, 0, ',', '.') ?></th>
                                            </tr>
                                        </tfoot>
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
</body>

</html>

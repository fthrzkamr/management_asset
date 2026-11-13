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

// Cek permission - Approval Realisasi (Finance) sesuai pengaturan Administrator
if (!$auth->hasPermission('approval_budget_finance', 'approve') && !$auth->hasPermission('approval_budget_finance', 'read')) {
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
    $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
    $item['line_total'] = $line_total;
    $total_used += $line_total;
    $items[] = $item;
}

// Ambil semua realisasi detail per item dari ppx_realisasi_detail2
$reals_by_item = [];
$total_real_approved = 0;
$total_real_pending  = 0;
$items_realized_count = 0;

$q_reals = mysqli_query($conn, "
    SELECT rd.*, u.nama_user as approver_name
    FROM ppx_realisasi_detail2 rd
    LEFT JOIN user_dfm u ON rd.approved_by = u.id_user
    WHERE rd.id_budget = $budget_id
    ORDER BY rd.tanggal_realisasi DESC, rd.id_real_detail DESC
");
if ($q_reals) {
    while ($rl = mysqli_fetch_assoc($q_reals)) {
        $reals_by_item[$rl['id_detail_budget']][] = $rl;
        if ($rl['status'] === 'Disetujui') {
            $total_real_approved += (float)$rl['nominal_realisasi'];
        } elseif ($rl['status'] === 'Menunggu Approval') {
            $total_real_pending += (float)$rl['nominal_realisasi'];
        }
    }
}

foreach ($items as $it) {
    $it_reals = $reals_by_item[$it['id']] ?? [];
    foreach ($it_reals as $r) {
        if ($r['status'] === 'Disetujui') {
            $items_realized_count++;
            break;
        }
    }
}

$total_sisa = max(0, floatval($budget['total_allocation']) - $total_real_approved);

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
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
            padding: 0.4rem 0.8rem;
        }

        .bukti-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e0f2fe;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 11px;
            color: #0369a1;
            text-decoration: none;
            margin: 2px;
        }
        .bukti-link:hover { background: #bae6fd; text-decoration: none; }
        .real-entry-box {
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 6px 10px;
            margin-top: 4px;
            border-radius: 4px;
            font-size: 11.5px;
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
                            <h3 class="page-title">Detail Anggaran Bulanan & Realisasi</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Approval Realisasi (Finance)</a></li>
                                <li class="breadcrumb-item active">Detail</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex" style="gap:8px;">
                            <a href="../z_realisasi_ppx/detail.php?id_budget=<?= $budget_id ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-list-check mr-1"></i> Buka Modul Input Realisasi
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
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
                        <div class="card info-card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0 text-white">
                                    <i class="fas fa-info-circle"></i> Informasi Budget & Progress Realisasi
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
                                                <td><strong><?= htmlspecialchars($budget['budget_name']) ?></strong></td>
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
                                                <th width="45%" class="text-muted">Total Alokasi Budget</th>
                                                <td><strong class="text-primary" style="font-size:15px;">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Realisasi Disetujui</th>
                                                <td><strong class="text-success" style="font-size:15px;">Rp <?= number_format($total_real_approved, 0, ',', '.') ?></strong> (<?= $items_realized_count ?> dari <?= count($items) ?> item)</td>
                                            </tr>
                                            <?php if ($total_real_pending > 0): ?>
                                            <tr>
                                                <th class="text-muted">Menunggu Approval Asmen</th>
                                                <td><strong class="text-warning">Rp <?= number_format($total_real_pending, 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th class="text-muted">Sisa Alokasi</th>
                                                <td><strong class="text-info" style="font-size:15px;">Rp <?= number_format($total_sisa, 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Status Budget</th>
                                                <td>
                                                    <?php if ($budget['status'] === 'Finance (Realisasi)'): ?>
                                                        <span class="badge badge-warning badge-status">Menunggu Realisasi / Aktivasi</span>
                                                    <?php elseif ($budget['status'] === 'Active'): ?>
                                                        <span class="badge badge-success badge-status">Aktif</span>
                                                    <?php elseif ($budget['status'] === 'Rejected'): ?>
                                                        <span class="badge badge-danger badge-status">Ditolak</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary badge-status"><?= $budget['status'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>

                                        <?php if ($budget['notes']): ?>
                                            <div class="alert alert-info mt-2 mb-0 py-2">
                                                <small><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($budget['notes'])) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($budget['file_excel'])): ?>
                                            <div class="mt-2">
                                                <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success" target="_blank" download>
                                                    <i class="fas fa-file-excel mr-1"></i> Download Lampiran Excel
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Items with Realization Tracking -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0 text-white">
                                    <i class="fas fa-list"></i> Rincian Item Budget & Realisasi (<?= count($items) ?> Item)
                                </h5>
                                <a href="../z_realisasi_ppx/detail.php?id_budget=<?= $budget_id ?>" class="btn btn-sm btn-light font-weight-bold">
                                    <i class="fas fa-plus-circle text-primary mr-1"></i> Input / Kelola Realisasi
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm table-hover">
                                        <thead class="bg-light">
                                            <tr>
                                                <th width="4%">No</th>
                                                <th width="10%">Kode</th>
                                                <th width="18%">Kategori</th>
                                                <th width="24%">Rincian Item</th>
                                                <th width="8%" class="text-center">Qty</th>
                                                <th width="12%" class="text-right">Alokasi Item</th>
                                                <th width="12%" class="text-right">Realisasi Disetujui</th>
                                                <th width="12%" class="text-center">Status Realisasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($items) > 0): ?>
                                                <?php
                                                $no = 1;
                                                $current_category = null;
                                                $total_allocation_sum = 0;
                                                $total_real_sum = 0;

                                                foreach ($items as $item):
                                                    $it_id = (int)$item['id'];
                                                    $line_total = (float)$item['line_total'];
                                                    $total_allocation_sum += $line_total;

                                                    $it_reals = $reals_by_item[$it_id] ?? [];
                                                    $it_app = 0;
                                                    $it_pend = 0;
                                                    foreach ($it_reals as $r) {
                                                        if ($r['status'] === 'Disetujui') $it_app += (float)$r['nominal_realisasi'];
                                                        elseif ($r['status'] === 'Menunggu Approval') $it_pend += (float)$r['nominal_realisasi'];
                                                    }
                                                    $total_real_sum += $it_app;

                                                    if ($current_category !== $item['id_category']):
                                                        $current_category = $item['id_category'];
                                                ?>
                                                    <tr class="table-secondary">
                                                        <td colspan="8">
                                                            <strong><i class="fas fa-tag mr-1 text-primary"></i> <?= htmlspecialchars($item['category_code'] ?? '') ?> — <?= htmlspecialchars($item['category_name'] ?? '') ?></strong>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><?= htmlspecialchars($item['category_code'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($item['category_name'] ?? '') ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($item['rincian'] ?? '-') ?></strong>
                                                            <?php if (!empty($it_reals)): ?>
                                                                <div class="mt-2">
                                                                    <?php foreach ($it_reals as $rl): ?>
                                                                        <div class="real-entry-box">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <span>
                                                                                    <strong>#<?= htmlspecialchars($rl['no_realisasi'] ?: '-') ?></strong>
                                                                                    (<?= $rl['tanggal_realisasi'] ? date('d/m/Y', strtotime($rl['tanggal_realisasi'])) : '-' ?>)
                                                                                    : <span class="text-success font-weight-bold">Rp <?= number_format($rl['nominal_realisasi'], 0, ',', '.') ?></span>
                                                                                </span>
                                                                                <span>
                                                                                    <?php if ($rl['status'] === 'Disetujui'): ?>
                                                                                        <span class="badge badge-success px-2"><i class="fas fa-check"></i> Disetujui</span>
                                                                                    <?php elseif ($rl['status'] === 'Menunggu Approval'): ?>
                                                                                        <span class="badge badge-warning px-2"><i class="fas fa-clock"></i> Menunggu Asmen</span>
                                                                                    <?php elseif ($rl['status'] === 'Ditolak'): ?>
                                                                                        <span class="badge badge-danger px-2"><i class="fas fa-times"></i> Ditolak</span>
                                                                                    <?php else: ?>
                                                                                        <span class="badge badge-secondary px-2">Draft</span>
                                                                                    <?php endif; ?>
                                                                                </span>
                                                                            </div>
                                                                            <?php if ($rl['catatan']): ?>
                                                                                <div class="text-muted mt-1"><i class="fas fa-comment mr-1"></i> <?= htmlspecialchars($rl['catatan']) ?></div>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($rl['bukti_files'])): ?>
                                                                                <div class="mt-1">
                                                                                    <?php foreach (explode(',', $rl['bukti_files']) as $bf): 
                                                                                        $bf = trim($bf); if (!$bf) continue;
                                                                                        $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                                                                        $ic = in_array($ext, ['jpg','jpeg','png']) ? 'fa-image' : 'fa-file-pdf';
                                                                                    ?>
                                                                                    <a href="../uploads/realisasi/<?= htmlspecialchars($bf) ?>" target="_blank" class="bukti-link">
                                                                                        <i class="fas <?= $ic ?>"></i> <?= htmlspecialchars($bf) ?>
                                                                                    </a>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center"><?= intval($item['qty'] ?? 0) ?> <?= htmlspecialchars($item['satuan'] ?? '') ?></td>
                                                        <td class="text-right font-weight-bold">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                                        <td class="text-right text-success font-weight-bold">
                                                            <?= $it_app > 0 ? 'Rp ' . number_format($it_app, 0, ',', '.') : '—' ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($it_app >= $line_total && $line_total > 0): ?>
                                                                <span class="badge badge-success"><i class="fas fa-check-double"></i> Selesai</span>
                                                            <?php elseif ($it_pend > 0): ?>
                                                                <span class="badge badge-warning"><i class="fas fa-clock"></i> Menunggu Asmen</span>
                                                            <?php elseif ($it_app > 0): ?>
                                                                <span class="badge badge-info"><i class="fas fa-pie-chart"></i> Sebagian</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-light border text-muted">Belum Realisasi</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                                        <p>Tidak ada rincian item budget</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="bg-light font-weight-bold">
                                            <tr>
                                                <th colspan="5" class="text-right">Total Alokasi & Realisasi:</th>
                                                <th class="text-right text-primary">Rp <?= number_format($total_allocation_sum, 0, ',', '.') ?></th>
                                                <th class="text-right text-success">Rp <?= number_format($total_real_sum, 0, ',', '.') ?></th>
                                                <th class="text-center text-info">Sisa: Rp <?= number_format(max(0, $total_allocation_sum - $total_real_sum), 0, ',', '.') ?></th>
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

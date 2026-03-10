<?php

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

$auth = new AuthHelper($conn);
$helper = new ApprovalHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('approval_budget_asmen', 'approve');

$id_budget = $_GET['id'] ?? null;
if (!$id_budget) {
    $_SESSION['error'] = 'ID budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data budget
$query = "SELECT mb.*, u.nama_user as creator_name, u.id_cabang as creator_id_cabang, u.divisi as creator_id_divisi, mc.nama_cabang
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
          WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Data budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Cek status
if ($budget['status'] !== 'Asmen Finance') {
    $_SESSION['error'] = 'Budget ini tidak dalam status menunggu approval Asmen Finance!';
    header("Location: dashboard.php");
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes  = trim($_POST['approval_notes'] ?? '');

    if ($action === 'approve') {
        $next_status = $helper->getNextStatusFrom('Asmen Finance', $budget['creator_id_cabang'], $budget['creator_id_divisi']);

        $update = "UPDATE ppx_monthly_budget SET status = ?, approved_at = NOW() WHERE id_budget = ?";
        $stmt_up = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt_up, 'si', $next_status, $id_budget);

        if (mysqli_stmt_execute($stmt_up)) {
            $_SESSION['success'] = "Budget berhasil disetujui. Status selanjutnya: " . $next_status;
            header("Location: dashboard.php");
            exit();
        }
    } elseif ($action === 'reject') {
        if (empty($notes)) {
            $_SESSION['error'] = "Catatan penolakan harus diisi!";
        } else {
            $rejected_status = 'Ditolak';
            $update = "UPDATE ppx_monthly_budget SET status = ?, approval_notes = ?, approved_at = NOW() WHERE id_budget = ?";
            $stmt_up = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt_up, 'ssi', $rejected_status, $notes, $id_budget);
            if (mysqli_stmt_execute($stmt_up)) {
                $_SESSION['success'] = "Budget ditolak.";
                header("Location: dashboard.php");
                exit();
            }
        }
    }
}

// Ambil detail items dari tabel detail
$items_query = "SELECT mbid.*, bc.category_name 
                FROM ppx_monthly_budget_item_detail mbid
                LEFT JOIN ppx_budget_category bc ON mbid.id_category = bc.id_category
                WHERE mbid.id_budget = ?
                ORDER BY bc.category_name";
$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proses Approval Anggaran - Asmen Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title"><i class="fas fa-user-check mr-2"></i>Detail Approval Anggaran - Asmen Finance</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Approval Asmen Finance</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?= htmlspecialchars($budget['budget_name']) ?>
                        <span class="badge badge-info ml-2">Menunggu Asmen Finance</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Periode:</strong> <?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></div>
                        <div class="col-md-3"><strong>Pengaju:</strong> <?= htmlspecialchars($budget['creator_name']) ?></div>
                        <div class="col-md-3"><strong>Cabang:</strong> <?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></div>
                        <div class="col-md-3"><strong>Total:</strong> Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></div>
                    </div>

                    <h6 class="mt-4 mb-2">Rincian Per Kategori:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Rincian Item</th>
                                    <th width="10%">Qty</th>
                                    <th width="10%">Satuan</th>
                                    <th width="15%">Harga</th>
                                    <th width="15%">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $current_cat = null;
                                $cat_total = 0;
                                $grand_total = 0;
                                $items_data = [];
                                while ($item = mysqli_fetch_assoc($items_result)) {
                                    $items_data[] = $item;
                                }

                                if (empty($items_data)): ?>
                                    <tr><td colspan="5" class="text-center">Tidak ada rincian item.</td></tr>
                                <?php else:
                                    $grouped = [];
                                    foreach ($items_data as $it) {
                                        $grouped[$it['category_name']][] = $it;
                                    }

                                    foreach ($grouped as $cat_name => $rows): 
                                        $sub_total = 0;
                                ?>
                                    <tr class="bg-light">
                                        <td colspan="5"><strong>Kategori: <?= htmlspecialchars($cat_name) ?></strong></td>
                                    </tr>
                                    <?php foreach ($rows as $r): 
                                        $sub_total += $r['total'];
                                        $grand_total += $r['total'];
                                    ?>
                                        <tr>
                                            <td class="pl-4"><?= htmlspecialchars($r['rincian']) ?></td>
                                            <td class="text-center"><?= $r['qty'] ?></td>
                                            <td><?= htmlspecialchars($r['satuan']) ?></td>
                                            <td class="text-right">Rp <?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                                            <td class="text-right">Rp <?= number_format($r['total'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="font-weight-bold">
                                        <td colspan="4" class="text-right">Total <?= htmlspecialchars($cat_name) ?>:</td>
                                        <td class="text-right">Rp <?= number_format($sub_total, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                    <tr class="bg-dark text-white font-weight-bold">
                                        <td colspan="4" class="text-right">GRAND TOTAL:</td>
                                        <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" class="mt-4">
                        <div class="form-group">
                            <label>Catatan Approval <small class="text-muted">(wajib diisi jika menolak)</small></label>
                            <textarea name="approval_notes" class="form-control" rows="3" placeholder="Tulis catatan jika ada..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success mr-2">
                                <i class="fas fa-check"></i> Setujui
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="return confirm('Anda yakin menolak anggaran ini?')">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary ml-auto">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php include '../layouts/footer.php'; ?>
        </div>
    </div>
</div>
</body>
</html>

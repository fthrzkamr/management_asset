<?php

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

$auth = new AuthHelper($conn);
$helper = new ApprovalHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('approval_budget_kacab', 'approve');

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

// Cek status - harus Kacab
if ($budget['status'] !== 'Kacab') {
    $_SESSION['error'] = 'Budget ini tidak dalam status menunggu approval Kacab!';
    header("Location: dashboard.php");
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['approval_notes'] ?? '');
    
    if ($action === 'approve') {
        $next_status = $helper->getNextStatusFrom('Kacab', $budget['creator_id_cabang'], $budget['creator_id_divisi']);
        
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
            $update = "UPDATE ppx_monthly_budget SET status = 'Rejected', notes = CONCAT(IFNULL(notes,''), '\n\nRejected by Kacab: ', ?) WHERE id_budget = ?";
            $stmt_up = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt_up, 'si', $notes, $id_budget);
            if (mysqli_stmt_execute($stmt_up)) {
                $_SESSION['success'] = "Budget telah ditolak.";
                header("Location: dashboard.php");
                exit();
            }
        }
    }
}

// Fetch items for display
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name";
$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items = mysqli_stmt_get_result($stmt_items);

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Proses Approval Kacab - <?= htmlspecialchars($budget['budget_name']) ?></title>
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
                    <h3>Review Pengajuan Anggaran</h3>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">Informasi Pengajuan</div>
                            <div class="card-body">
                                <p><strong>Periode:</strong> <?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></p>
                                <p><strong>Nama:</strong> <?= htmlspecialchars($budget['budget_name']) ?></p>
                                <p><strong>PIC:</strong> <?= htmlspecialchars($budget['creator_name']) ?></p>
                                <p><strong>Cabang:</strong> <?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></p>
                                <p><strong>Total:</strong> <span class="text-primary font-weight-bold">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></span></p>
                                <?php if (!empty($budget['file_excel'])): ?>
                                    <p><strong>Lampiran:</strong> 
                                        <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success mt-1" target="_blank" download>
                                            <i class="fas fa-file-excel"></i> Download Excel
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning">Tindakan Approval</div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label>Catatan (Opsional saat approve, wajib saat reject)</label>
                                        <textarea name="approval_notes" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" name="action" value="approve" class="btn btn-success">Setujui</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Tolak anggaran ini?')">Tolak</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Rincian Item</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Rincian</th>
                                        <th>Qty</th>
                                        <th>Harga</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $items_data = [];
                                    while ($it = mysqli_fetch_assoc($items)) {
                                        $items_data[] = $it;
                                    }

                                    if (empty($items_data)): ?>
                                        <tr><td colspan="5" class="text-center">Tidak ada rincian item.</td></tr>
                                    <?php else:
                                        $grouped = [];
                                        foreach ($items_data as $it) {
                                            $grouped[$it['category_name']][] = $it;
                                        }

                                        $grand_total = 0;
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
                                                <td class="pl-4"><?= htmlspecialchars($r['category_name']) ?></td>
                                                <td><?= htmlspecialchars($r['rincian']) ?></td>
                                                <td class="text-center"><?= $r['qty'] ?> <?= $r['satuan'] ?></td>
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
                    </div>
                </div>
                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html>

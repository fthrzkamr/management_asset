<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

$auth   = new AuthHelper($conn);
$helper = new ApprovalHelper($conn);

if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_budget_manajer_ops', 'approve');

$id_budget = $_GET['id'] ?? null;
if (!$id_budget) {
    $_SESSION['error'] = 'ID budget tidak ditemukan!';
    header("Location: dashboard.php"); exit();
}

// Ambil data budget beserta info divisi pembuat
$query = "SELECT mb.*, u.nama_user as creator_name, u.id_cabang as creator_id_cabang,
                 u.divisi as creator_id_divisi, mc.nama_cabang, dv.nama_divisi
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
          LEFT JOIN divisi dv ON u.divisi = dv.id_divisi
          WHERE mb.id_budget = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Data budget tidak ditemukan!';
    header("Location: dashboard.php"); exit();
}
$budget = mysqli_fetch_assoc($result);

if ($budget['status'] !== 'Manager Operasional') {
    $_SESSION['error'] = 'Budget ini tidak dalam status Manager Operasional!';
    header("Location: dashboard.php"); exit();
}

// Ambil detail items
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";
$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action'] ?? '';
    $approval_notes = trim($_POST['approval_notes'] ?? '');

    if ($action === 'approve') {
        $combined_notes = trim($budget['notes'] ?? '');
        if (!empty($approval_notes)) {
            $combined_notes .= ($combined_notes ? "\n\n" : "") . "Approved by Manager Operasional: " . $approval_notes;
        }

        $next_status = $helper->getNextStatusFrom(
            'Manager Operasional',
            $budget['creator_id_cabang'],
            $budget['creator_id_divisi']
        );

        $update = "UPDATE ppx_monthly_budget SET status=?, approved_by=?, approved_at=NOW(), notes=?
                   WHERE id_budget=? AND status='Manager Operasional'";
        $stmt_u = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt_u, 'sisi', $next_status, $_SESSION['id_user'], $combined_notes, $id_budget);

        if (mysqli_stmt_execute($stmt_u) && mysqli_stmt_affected_rows($stmt_u) > 0) {
            $_SESSION['success'] = "Budget berhasil disetujui! Status berikutnya: $next_status";
            header("Location: dashboard.php"); exit();
        } else {
            $_SESSION['error'] = 'Gagal menyetujui budget.';
        }

    } elseif ($action === 'reject') {
        if (empty($approval_notes)) {
            $_SESSION['error'] = 'Alasan penolakan harus diisi!';
        } else {
            $combined_notes = trim($budget['notes'] ?? '');
            $combined_notes .= ($combined_notes ? "\n\n" : "") . "Rejected by Manager Operasional: " . $approval_notes;

            $update = "UPDATE ppx_monthly_budget SET status='Rejected', approved_by=?, approved_at=NOW(), notes=?
                       WHERE id_budget=? AND status='Manager Operasional'";
            $stmt_u = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt_u, 'isi', $_SESSION['id_user'], $combined_notes, $id_budget);

            if (mysqli_stmt_execute($stmt_u) && mysqli_stmt_affected_rows($stmt_u) > 0) {
                $_SESSION['success'] = 'Budget berhasil ditolak!';
                header("Location: dashboard.php"); exit();
            } else {
                $_SESSION['error'] = 'Gagal menolak budget.';
            }
        }
    }
}

$error_message = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<?php include '../layouts/navbar.php'; ?>
<body>
<div class="main-wrapper">
<div class="page-wrapper">
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title">Detail Approval Anggaran - Manager Operasional</h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Approval Manager Ops</a></li>
                    <li class="breadcrumb-item active">Proses Approval</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Info Budget -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h4 class="card-title mb-0">Informasi Pengajuan</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><th width="40%">Periode</th><td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td></tr>
                                <tr><th>Nama Anggaran</th><td><?= htmlspecialchars($budget['budget_name']) ?></td></tr>
                                <tr><th>Pengaju</th><td><?= htmlspecialchars($budget['creator_name']) ?></td></tr>
                                <tr><th>Cabang</th><td><span class="badge badge-info"><?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></span></td></tr>
                                <tr><th>Divisi</th><td><span class="badge badge-secondary"><?= htmlspecialchars($budget['nama_divisi'] ?? '-') ?></span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><th width="40%">Total Alokasi</th><td><strong class="text-primary">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></strong></td></tr>
                                <tr><th>Status</th><td><span class="badge badge-warning"><?= $budget['status'] ?></span></td></tr>
                                <tr><th>Tanggal Dibuat</th><td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td></tr>
                                <tr><th>Catatan</th><td><?= htmlspecialchars($budget['notes']) ?: '-' ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Items -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title mb-0">Rincian Item per Kategori</h4></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th><th>Kode</th><th>Kategori</th><th>Rincian</th>
                                    <th>Qty</th><th>Satuan</th><th>Harga Satuan</th><th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $items_data = [];
                            while ($item = mysqli_fetch_assoc($items_result)) $items_data[] = $item;

                            if (empty($items_data)): ?>
                                <tr><td colspan="8" class="text-center text-muted">Tidak ada rincian item.</td></tr>
                            <?php else:
                                $grouped = [];
                                foreach ($items_data as $it) $grouped[$it['category_name']][] = $it;
                                $grand_total = 0;
                                foreach ($grouped as $cat_name => $rows):
                                    $sub_total = 0; $no_item = 1;
                            ?>
                                <tr class="bg-light"><td colspan="8"><strong>Kategori: <?= htmlspecialchars($cat_name) ?></strong></td></tr>
                                <?php foreach ($rows as $r):
                                    $line_total = $r['total'];
                                    $sub_total += $line_total;
                                    $grand_total += $line_total;
                                ?>
                                <tr>
                                    <td><?= $no_item++ ?></td>
                                    <td><?= htmlspecialchars($r['category_code']) ?></td>
                                    <td><?= htmlspecialchars($r['category_name']) ?></td>
                                    <td><?= htmlspecialchars($r['rincian']) ?></td>
                                    <td class="text-center"><?= intval($r['qty']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                                    <td class="text-right">Rp <?= number_format(floatval($r['harga_satuan']), 0, ',', '.') ?></td>
                                    <td class="text-right">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="font-weight-bold">
                                    <td colspan="7" class="text-right">Subtotal <?= htmlspecialchars($cat_name) ?>:</td>
                                    <td class="text-right">Rp <?= number_format($sub_total, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                                <tr class="bg-dark text-white font-weight-bold">
                                    <td colspan="7" class="text-right">GRAND TOTAL:</td>
                                    <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Approval -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white"><h4 class="card-title mb-0 text-white">Proses Persetujuan</h4></div>
                <div class="card-body">
                    <form method="POST" id="approvalForm">
                        <div class="form-group">
                            <label>Catatan <span class="text-muted">(Opsional untuk Setujui, Wajib untuk Tolak)</span></label>
                            <textarea name="approval_notes" id="approval_notes" class="form-control" rows="3" placeholder="Masukkan catatan..."></textarea>
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-success" onclick="doAction('approve')">
                                <i class="fas fa-check mr-1"></i> Setujui
                            </button>
                            <button type="button" class="btn btn-danger" onclick="doAction('reject')">
                                <i class="fas fa-times mr-1"></i> Tolak
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                            </a>
                        </div>
                        <input type="hidden" name="action" id="action_field">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<?php include '../layouts/footer.php'; ?>
<script>
function doAction(action) {
    const notes = document.getElementById('approval_notes').value.trim();
    if (action === 'reject' && !notes) {
        alert('Catatan wajib diisi untuk menolak budget!');
        return;
    }
    document.getElementById('action_field').value = action;
    document.getElementById('approvalForm').submit();
}
</script>
</body>
</html>

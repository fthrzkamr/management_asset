<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || (!$auth->isManagerFinance() && !$auth->isSuperAdmin())) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Terima ID budget dari GET
$id_budget = $_GET['id'] ?? null;

if (!$id_budget) {
    $_SESSION['error'] = 'ID budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data budget
$query = "SELECT mb.*, u.nama_user as creator_name
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
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

// Cek status - harus Pending atau Revisi
if ($budget['status'] !== 'Pending' && $budget['status'] !== 'Revisi') {
    $_SESSION['error'] = 'Budget ini tidak dalam status yang dapat diproses!';
    header("Location: dashboard.php");
    exit();
}

// Ambil detail items (item level)
$items_query = "SELECT d.*, bc.category_name, bc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category bc ON d.id_category = bc.id_category
                WHERE d.id_budget = ?
                ORDER BY bc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Handle POST untuk approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $approval_notes = trim($_POST['approval_notes'] ?? '');
    
    if ($action === 'approve') {
        // Approve budget - ubah status jadi Approved
        $update_sql = "UPDATE ppx_monthly_budget SET 
                      status = 'Approved',
                      approved_by = ?,
                      approved_at = NOW(),
                      approval_notes = ?
                      WHERE id_budget = ?";
        
        $stmt_update = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt_update, 'isi', $_SESSION['id_user'], $approval_notes, $id_budget);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $_SESSION['success'] = 'Budget berhasil disetujui!';
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = 'Gagal menyetujui budget: ' . mysqli_error($conn);
        }
    } 
    elseif ($action === 'reject') {
        // Reject budget - ubah status jadi Rejected
        if (empty($approval_notes)) {
            $_SESSION['error'] = 'Alasan penolakan harus diisi!';
        } else {
            $update_sql = "UPDATE ppx_monthly_budget SET 
                          status = 'Rejected',
                          approved_by = ?,
                          approved_at = NOW(),
                          approval_notes = ?
                          WHERE id_budget = ?";
            
            $stmt_update = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_update, 'isi', $_SESSION['id_user'], $approval_notes, $id_budget);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success'] = 'Budget berhasil ditolak!';
                header("Location: dashboard.php");
                exit();
            } else {
                $_SESSION['error'] = 'Gagal menolak budget: ' . mysqli_error($conn);
            }
        }
    }
}

$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
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
                    <h3 class="page-title">Proses Approval Anggaran Bulanan</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Approval Anggaran</a></li>
                        <li class="breadcrumb-item active">Proses Approval</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Budget Info Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0 text-white">Informasi Budget</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Periode</th>
                                        <td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
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
                                        <th>Sumber Dana</th>
                                        <td><?= htmlspecialchars($budget['master_id'] ?? '-') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Total Alokasi</th>
                                        <td><strong class="text-primary">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td><span class="badge badge-warning"><?= $budget['status'] ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td><?= htmlspecialchars($budget['notes']) ?: '-' ?></td>
                                    </tr>
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
                    <div class="card-header">
                        <h4 class="card-title mb-0">Rincian Item Anggaran</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="bg-light">
                                    <tr>
                                        <th>No</th>
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
                                    $total = 0;
                                    while ($item = mysqli_fetch_assoc($items_result)): 
                                        $total += $item['total'];
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><small><?= htmlspecialchars($item['category_name']) ?></small></td>
                                            <td><?= htmlspecialchars($item['rincian']) ?></td>
                                            <td class="text-center"><?= number_format($item['qty'], 0) ?></td>
                                            <td><?= htmlspecialchars($item['satuan']) ?></td>
                                            <td class="text-center"><?= $item['jumlah_hari'] ?: '-' ?></td>
                                            <td class="text-center"><?= $item['jumlah_minggu'] ?: '-' ?></td>
                                            <td class="text-right">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                            <td class="text-right font-weight-bold">Rp <?= number_format($item['total'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="8" class="text-right">Total:</th>
                                        <th class="text-right">Rp <?= number_format($total, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Form -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="card-title mb-0">Proses Approval</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="approvalForm">
                            <div class="form-group">
                                <label>Catatan Approval <span class="text-muted">(Opsional untuk Approve, Wajib untuk Reject)</span></label>
                                <textarea name="approval_notes" id="approval_notes" class="form-control" rows="4" placeholder="Masukkan catatan approval Anda..."></textarea>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-success btn-lg" onclick="submitApproval('approve')">
                                    <i class="fas fa-check"></i> Setujui Budget
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" onclick="submitApproval('reject')">
                                    <i class="fas fa-times"></i> Tolak Budget
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>

                            <input type="hidden" name="action" id="action">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('../layouts/footer.php'); ?>
</div>

<script>
function submitApproval(action) {
    const notes = document.getElementById('approval_notes').value.trim();
    
    if (action === 'reject' && notes === '') {
        alert('Catatan wajib diisi untuk menolak budget!');
        return;
    }
    
    let confirmMsg = action === 'approve' 
        ? 'Apakah Anda yakin ingin menyetujui budget ini?' 
        : 'Apakah Anda yakin ingin menolak budget ini?';
    
    if (confirm(confirmMsg)) {
        document.getElementById('action').value = action;
        document.getElementById('approvalForm').submit();
    }
}
</script>
</html>

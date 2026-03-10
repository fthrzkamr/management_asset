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

// Check if user has permission
if (!$auth->isManagerFinance() && !$auth->isSuperAdmin()) {
    header("Location: ../index.php");
    exit();
}

$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get budget data
$query = "SELECT mb.*, u.nama_user as creator_name, a.nama_user as approver_name
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN user_dfm a ON mb.approved_by = a.id_user
          WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Get item-level details grouped by category
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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
                    <h3 class="page-title">Detail Anggaran Bulanan</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Approval Anggaran</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- Budget Info Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4 class="card-title mb-0 text-white">Informasi Budget</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Periode</th>
                                        <td><strong><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Nama Budget</th>
                                        <td><?= htmlspecialchars($budget['budget_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Sumber Dana</th>
                                        <td><?= htmlspecialchars($budget['master_id']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Dibuat</th>
                                        <td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
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
                                        <th>Total Sisa</th>
                                        <td><strong class="text-success">Rp <?= number_format($budget['total_remaining'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            $status_text = $budget['status'];
                                            switch ($budget['status']) {
                                                case 'Draft': 
                                                    $badge_class = 'warning';
                                                    $status_text = 'Menunggu Approval';
                                                    break;
                                                case 'Active': 
                                                    $badge_class = 'success';
                                                    $status_text = 'Disetujui';
                                                    break;
                                                case 'Closed': 
                                                    $badge_class = 'danger';
                                                    $status_text = 'Ditutup';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge badge-<?= $badge_class ?>"><?= $status_text ?></span>
                                        </td>
                                    </tr>
                                    <?php if ($budget['approved_by']): ?>
                                        <tr>
                                            <th>Disetujui Oleh</th>
                                            <td><?= htmlspecialchars($budget['approver_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Approval</th>
                                            <td><?= date('d/m/Y H:i', strtotime($budget['approved_at'])) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Catatan</th>
                                        <td><?= nl2br(htmlspecialchars($budget['notes'])) ?: '-' ?></td>
                                    </tr>
                                    <?php if (!empty($budget['file_excel'])): ?>
                                    <tr>
                                        <th>File Excel</th>
                                        <td>
                                            <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success" target="_blank" download>
                                                <i class="fas fa-file-excel"></i> Download Lampiran
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Approve / Tolak (tampil jika status = Manager Finance) -->
        <?php if ($budget['status'] === 'Manager Finance'): ?>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-tasks mr-2"></i>Tindakan Persetujuan</h5>
                    </div>
                    <div class="card-body d-flex align-items-center gap-3">
                        <a href="process_approve.php?id=<?= $id_budget ?>&action=approve"
                           class="btn btn-success btn-lg mr-3">
                            <i class="fas fa-check-circle mr-1"></i> Setujui Anggaran
                        </a>
                        <button type="button" class="btn btn-danger btn-lg" data-toggle="modal" data-target="#modalTolak">
                            <i class="fas fa-times-circle mr-1"></i> Tolak Anggaran
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tolak -->
        <div class="modal fade" id="modalTolak" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Tolak Anggaran</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <form action="process_approve.php" method="GET">
                        <input type="hidden" name="id" value="<?= $id_budget ?>">
                        <input type="hidden" name="action" value="reject">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Alasan Penolakan <span class="text-danger">*</span></label>
                                <textarea name="reject_reason" class="form-control" rows="3" required
                                          placeholder="Berikan alasan penolakan..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Konfirmasi Tolak</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detail Items -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Detail Alokasi per Kategori</h4>
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
                                    <?php
                                    $no = 1;
                                    $total_allocation = 0;
                                    $current_category = null;
                                    while ($item = mysqli_fetch_assoc($items_result)):
                                        $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
                                        $total_allocation += $line_total;
                                        // When category changes, optionally show a category header row
                                        if ($current_category !== $item['id_category']):
                                            $current_category = $item['id_category'];
                                    ?>
                                        <tr class="table-secondary">
                                            <td colspan="8"><strong><?= htmlspecialchars($item['category_code']) ?></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($item['category_code']) ?></td>
                                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                                            <td><?= htmlspecialchars($item['rincian']) ?></td>
                                            <td class="text-center"><?= intval($item['qty']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($item['satuan']) ?></td>
                                            <td class="text-right">Rp <?= number_format(floatval($item['harga_satuan']), 0, ',', '.') ?></td>
                                            <td class="text-right">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="6" class="text-right">Total:</th>
                                        <th></th>
                                        <th>Rp <?= number_format($total_allocation, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once('../layouts/footer.php'); ?>
</div>
</html>

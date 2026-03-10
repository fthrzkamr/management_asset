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
          WHERE mb.id_monthly_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Get items
$items_query = "SELECT mbi.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item mbi
                LEFT JOIN ppx_budget_category pbc ON mbi.id_category = pbc.id_category
                WHERE mbi.monthly_budget_id = ?
                ORDER BY pbc.category_name";

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
                                            switch ($budget['status']) {
                                                case 'Pending': $badge_class = 'warning'; break;
                                                case 'Approved': $badge_class = 'success'; break;
                                                case 'Rejected': $badge_class = 'danger'; break;
                                            }
                                            ?>
                                            <span class="badge badge-<?= $badge_class ?>"><?= $budget['status'] ?></span>
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
                                    <?php if ($budget['approval_notes']): ?>
                                        <tr>
                                            <th>Catatan Approval</th>
                                            <td><?= nl2br(htmlspecialchars($budget['approval_notes'])) ?></td>
                                        </tr>
                                    <?php endif; ?>
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
                                        <th width="15%">Alokasi</th>
                                        <th width="15%">Sisa</th>
                                        <th width="10%">Carryover</th>
                                        <th width="20%">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    $total_allocation = 0;
                                    $total_remaining = 0;
                                    while ($item = mysqli_fetch_assoc($items_result)): 
                                        $total_allocation += $item['allocation'];
                                        $total_remaining += $item['remaining'];
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($item['category_code']) ?></td>
                                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                                            <td>Rp <?= number_format($item['allocation'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format($item['remaining'], 0, ',', '.') ?></td>
                                            <td class="text-center">
                                                <?php if ($item['allow_carryover']): ?>
                                                    <span class="badge badge-success">Ya</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Tidak</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['notes']) ?: '-' ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="3" class="text-right">Total:</th>
                                        <th>Rp <?= number_format($total_allocation, 0, ',', '.') ?></th>
                                        <th>Rp <?= number_format($total_remaining, 0, ',', '.') ?></th>
                                        <th colspan="2"></th>
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

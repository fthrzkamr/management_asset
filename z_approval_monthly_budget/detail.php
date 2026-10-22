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

// Get detailed items
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
                                        <th>Tipe Anggaran</th>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($budget['tipe_anggaran'] ?? 'N/A') ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Sumber Dana</th>
                                        <td><?= htmlspecialchars($budget['master_id'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($budget['creator_name']) ?></td>
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
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            switch ($budget['status']) {
                                                case 'Pending': $badge_class = 'warning'; break;
                                                case 'Approved': case 'Active': $badge_class = 'success'; break;
                                                case 'Rejected': $badge_class = 'danger'; break;
                                                case 'Revisi': $badge_class = 'info'; break;
                                            }
                                            ?>
                                            <span class="badge badge-<?= $badge_class ?>"><?= $budget['status'] ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Dibuat</th>
                                        <td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td><?= nl2br(htmlspecialchars($budget['notes'])) ?: '-' ?></td>
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
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Kategori</th>
                                        <th>Rincian</th>
                                        <th width="8%">Qty</th>
                                        <th width="8%">Satuan</th>
                                        <th width="8%">Jml Hari</th>
                                        <th width="8%">Jml Minggu</th>
                                        <th width="12%">Harga Satuan</th>
                                        <th width="15%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    $total_allocation = 0;
                                    while ($item = mysqli_fetch_assoc($items_result)): 
                                        $total_allocation += $item['total'];
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
                                        <th class="text-right">Rp <?= number_format($total_allocation, 0, ',', '.') ?></th>
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

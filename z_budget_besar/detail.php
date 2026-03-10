<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('budget_besar', 'read');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: dashboard.php"); exit(); }

// Ambil data budget
$stmt = mysqli_prepare($conn, "SELECT mb.*, u.nama_user as creator_name, mc.nama_cabang, d.nama_divisi
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN divisi d ON u.divisi = d.id_divisi
    WHERE mb.id_budget = ? AND mb.budget_type = 'besar'");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$budget) { header("Location: dashboard.php"); exit(); }

// Ambil item detail
$items_res = mysqli_query($conn, "SELECT d.*, c.category_name
    FROM ppx_monthly_budget_item_detail d
    LEFT JOIN ppx_budget_category c ON d.id_category = c.id_category
    WHERE d.id_budget = $id ORDER BY c.category_name, d.id");

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
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
                <h3 class="page-title"><i class="fas fa-money-bill-wave text-success mr-2"></i>
                    Detail Anggaran Besar
                </h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Besar</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Info Header -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><?= htmlspecialchars($budget['budget_name']) ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Periode</strong><br>
                    <?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?>
                </div>
                <div class="col-md-3">
                    <strong>Pengaju</strong><br>
                    <?= htmlspecialchars($budget['creator_name'] ?? '-') ?>
                </div>
                <div class="col-md-3">
                    <strong>Cabang / Divisi</strong><br>
                    <?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?> /
                    <?= htmlspecialchars($budget['nama_divisi'] ?? '-') ?>
                </div>
                <div class="col-md-3">
                    <strong>Status</strong><br>
                    <span class="badge badge-warning badge-lg px-3 py-2"><?= htmlspecialchars($budget['status']) ?></span>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <strong>Total Alokasi</strong><br>
                    <h4 class="text-success">Rp <?= number_format($budget['total_allocation'],0,',','.') ?></h4>
                </div>
                <div class="col-md-8">
                    <strong>Catatan</strong><br>
                    <?= nl2br(htmlspecialchars($budget['notes'] ?? '-')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Detail Item -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list mr-2"></i>Rincian Item</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Kategori</th>
                            <th>Rincian</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Harga Satuan</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($items_res && mysqli_num_rows($items_res) > 0):
                        $no = 1; $grand = 0;
                        while ($item = mysqli_fetch_assoc($items_res)):
                            $grand += $item['total'];
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($item['category_name'] ?? '-') ?></span></td>
                            <td><?= htmlspecialchars($item['rincian']) ?></td>
                            <td><?= $item['qty'] ?></td>
                            <td><?= htmlspecialchars($item['satuan']) ?></td>
                            <td>Rp <?= number_format($item['harga_satuan'],0,',','.') ?></td>
                            <td><strong>Rp <?= number_format($item['total'],0,',','.') ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                        <tr class="table-success font-weight-bold">
                            <td colspan="6" class="text-right">Grand Total:</td>
                            <td>Rp <?= number_format($grand,0,',','.') ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">Tidak ada item detail</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-left mt-3">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>
    <?php include '../layouts/footer.php'; ?>
</div>
</div>
</div>
</body>
</html>

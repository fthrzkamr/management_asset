<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_budget_asmen', 'approve');

$step_name = 'Asmen Finance';

// Biasa
$result_biasa = mysqli_query($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status = '$step_name' AND (mb.budget_type = 'biasa' OR mb.budget_type IS NULL)
ORDER BY mb.created_at DESC");

// Besar
$result_besar = mysqli_query($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status = '$step_name' AND mb.budget_type = 'besar'
ORDER BY mb.created_at DESC");

$count_besar = mysqli_num_rows($result_besar);

$success_message = $_SESSION['success'] ?? ''; $error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$month_names = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Anggaran - Asmen Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title"><i class="fas fa-user-check mr-2"></i>Approval Anggaran (Asmen Finance)</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Approval Anggaran</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_message) ?>
                <button class="close" data-dismiss="alert">&times;</button></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_message) ?>
                <button class="close" data-dismiss="alert">&times;</button></div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#panel-biasa"><i class="fas fa-wallet mr-1"></i>Anggaran Biasa</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-besar"><i class="fas fa-money-bill-wave mr-1 text-success"></i>Anggaran Besar<?php if ($count_besar > 0): ?> <span class="badge badge-success"><?= $count_besar ?></span><?php endif; ?></a></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="panel-biasa">
                    <div class="card"><div class="card-body">
                    <div class="table-responsive"><table class="table table-striped datatable-b">
                        <thead><tr><th>Periode</th><th>Nama Budget</th><th>Total Alokasi</th><th>PIC</th><th>Cabang</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result_biasa)): ?>
                        <tr>
                            <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                            <td><?= htmlspecialchars($row['budget_name']) ?></td>
                            <td><strong>Rp <?= number_format($row['total_allocation'],0,',','.') ?></strong></td>
                            <td><?= htmlspecialchars($row['creator_name']) ?></td>
                            <td><?= htmlspecialchars($row['creator_cabang']) ?></td>
                            <td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
                            <td><a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Proses</a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table></div>
                    </div></div>
                </div>
                <div class="tab-pane fade" id="panel-besar">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Anggaran Besar - Menunggu Asmen Finance</h5></div>
                        <div class="card-body">
                        <div class="table-responsive"><table class="table table-striped datatable-bs">
                            <thead><tr><th>Periode</th><th>Nama Anggaran</th><th>Total Alokasi</th><th>PIC</th><th>Cabang</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result_besar)): ?>
                            <tr>
                                <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                <td><strong>Rp <?= number_format($row['total_allocation'],0,',','.') ?></strong></td>
                                <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                <td><?= htmlspecialchars($row['creator_cabang']) ?></td>
                                <td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
                                <td><a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Proses</a></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../layouts/footer.php'; ?>
        </div>
    </div>
</div>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('.datatable-b, .datatable-bs').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({ pageLength: 10, order: [[5,'desc']],
                language: { search: 'Cari:', zeroRecords: 'Tidak ada data', info: '_START_-_END_ dari _TOTAL_', paginate: { previous: '&laquo;', next: '&raquo;' } }
            });
        }
    });
});
</script>
</body></html>

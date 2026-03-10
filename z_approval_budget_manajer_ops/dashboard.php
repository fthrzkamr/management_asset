<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }

if (!$auth->hasPermission('approval_budget_manajer_ops', 'read')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini!';
    header("Location: ../index.php"); exit();
}

// Biasa
$result = mysqli_query($conn, "SELECT mb.*, u.nama_user as creator_name, mc.nama_cabang, dv.nama_divisi
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN divisi dv ON u.divisi = dv.id_divisi
    WHERE mb.status = 'Manager Operasional' AND (mb.budget_type = 'biasa' OR mb.budget_type IS NULL)
    ORDER BY mb.created_at DESC");

// Besar
$result_besar = mysqli_query($conn, "SELECT mb.*, u.nama_user as creator_name, mc.nama_cabang, dv.nama_divisi
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN divisi dv ON u.divisi = dv.id_divisi
    WHERE mb.status = 'Manager Operasional' AND mb.budget_type = 'besar'
    ORDER BY mb.created_at DESC");
$count_besar = mysqli_num_rows($result_besar);

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error   = $_SESSION['error']   ?? ''; unset($_SESSION['error']);
$month_names = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
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
                <h3 class="page-title"><i class="fas fa-tasks mr-2"></i>Approval Anggaran - Manager Operasional</h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Approval Manager Operasional</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#panel-ops-biasa"><i class="fas fa-wallet mr-1"></i>Anggaran Biasa</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-ops-besar"><i class="fas fa-money-bill-wave mr-1 text-success"></i>Anggaran Besar<?php if ($count_besar > 0): ?> <span class="badge badge-success"><?= $count_besar ?></span><?php endif; ?></a></li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="panel-ops-biasa">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h4 class="card-title mb-0 text-dark"><i class="fas fa-clock mr-2"></i>Menunggu Persetujuan Manager Operasional</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr><th>#</th><th>Nama Anggaran</th><th>Periode</th><th>Cabang</th><th>Divisi</th><th>Pengaju</th><th>Total Nominal</th><th>Tanggal</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                            <?php if (mysqli_num_rows($result) == 0): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>Tidak ada anggaran biasa yang perlu disetujui</td></tr>
                            <?php else: $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['budget_name']) ?></strong></td>
                                <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($row['nama_cabang'] ?? '-') ?></span></td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($row['nama_divisi'] ?? '-') ?></span></td>
                                <td><?= htmlspecialchars($row['creator_name'] ?? '-') ?></td>
                                <td class="text-right"><strong>Rp <?= number_format($row['total_allocation'],0,',','.') ?></strong></td>
                                <td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
                                <td><a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye mr-1"></i>Detail &amp; Proses</a></td>
                            </tr>
                            <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- end panel-ops-biasa -->

    <div class="tab-pane fade" id="panel-ops-besar">
        <div class="card border-success">
            <div class="card-header bg-success text-white"><h4 class="card-title mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Anggaran Besar - Menunggu Manager Operasional</h4></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>#</th><th>Nama Anggaran</th><th>Periode</th><th>Cabang</th><th>Pengaju</th><th>Total Nominal</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php if (mysqli_num_rows($result_besar) == 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>Tidak ada anggaran besar</td></tr>
                        <?php else: $no2 = 1; while ($rB = mysqli_fetch_assoc($result_besar)): ?>
                        <tr>
                            <td><?= $no2++ ?></td>
                            <td><strong><?= htmlspecialchars($rB['budget_name']) ?></strong></td>
                            <td><?= $month_names[$rB['period_month']] ?> <?= $rB['period_year'] ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($rB['nama_cabang'] ?? '-') ?></span></td>
                            <td><?= htmlspecialchars($rB['creator_name'] ?? '-') ?></td>
                            <td class="text-right"><strong>Rp <?= number_format($rB['total_allocation'],0,',','.') ?></strong></td>
                            <td><?= date('d/m/Y',strtotime($rB['created_at'])) ?></td>
                            <td><a href="process_approve.php?id=<?= $rB['id_budget'] ?>" class="btn btn-sm btn-success"><i class="fas fa-eye mr-1"></i>Detail &amp; Proses</a></td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div><!-- end tab-content -->
</div>
</div>
</div>
<?php include '../layouts/footer.php'; ?>
</body>
</html>

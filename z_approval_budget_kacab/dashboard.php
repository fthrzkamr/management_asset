<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_budget_kacab', 'approve');

$user_id_cabang = $_SESSION['id_cabang'] ?? ''; // id_cabang bisa berupa string (VARCHAR)
$current_user_id = (int)($_SESSION['id_user'] ?? 0);

// Cari semua id_cabang yang routing Kacab-nya mengarah ke user ini
// (via ppx_budget_routing_cabang override ATAU cabang sendiri)
$routed_cabang = [$user_id_cabang]; // selalu include cabang sendiri
$sq = mysqli_query($conn, 
    "SELECT DISTINCT c.id_cabang 
     FROM ppx_budget_routing_cabang c
     JOIN ppx_budget_routing r ON c.id_routing_step = r.id
     WHERE r.step_name = 'Kacab'
       AND c.approver_id = $current_user_id
       AND c.is_active = 1
       AND c.skip_step = 0");
if ($sq) {
    while ($rc = mysqli_fetch_assoc($sq)) {
        if (!in_array($rc['id_cabang'], $routed_cabang)) {
            $routed_cabang[] = $rc['id_cabang'];
        }
    }
}
// Buat IN clause yang aman (semua string)
$placeholders = implode(',', array_fill(0, count($routed_cabang), '?'));
$types_biasa  = str_repeat('s', count($routed_cabang));
$types_besar  = str_repeat('s', count($routed_cabang));

// Anggaran BIASA — budget_type = 'biasa' (atau NULL untuk backward compat)
$stmt_biasa = mysqli_prepare($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status = 'Kacab' AND (mb.budget_type = 'biasa' OR mb.budget_type IS NULL)
  AND u.id_cabang IN ($placeholders)
ORDER BY mb.created_at DESC");
mysqli_stmt_bind_param($stmt_biasa, $types_biasa, ...$routed_cabang);
mysqli_stmt_execute($stmt_biasa);
$result_biasa = mysqli_stmt_get_result($stmt_biasa);

// Anggaran BESAR — budget_type = 'besar'
$stmt_besar = mysqli_prepare($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status = 'Kacab' AND mb.budget_type = 'besar'
  AND u.id_cabang IN ($placeholders)
ORDER BY mb.created_at DESC");
mysqli_stmt_bind_param($stmt_besar, $types_besar, ...$routed_cabang);
mysqli_stmt_execute($stmt_besar);
$result_besar = mysqli_stmt_get_result($stmt_besar);

$count_besar = mysqli_num_rows($result_besar);

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Anggaran - Kacab</title>
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
                            <h3 class="page-title">Approval Anggaran (Kepala Cabang)</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Anggaran</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Nav Tabs -->
                <ul class="nav nav-tabs mb-3" id="budgetTabs">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-biasa" data-toggle="tab" href="#panel-biasa">
                            <i class="fas fa-wallet mr-1"></i> Anggaran Biasa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-besar" data-toggle="tab" href="#panel-besar">
                            <i class="fas fa-money-bill-wave mr-1 text-success"></i> Anggaran Besar
                            <?php if ($count_besar > 0): ?>
                                <span class="badge badge-success ml-1"><?= $count_besar ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Biasa -->
                    <div class="tab-pane fade show active" id="panel-biasa">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped datatable-biasa">
                                        <thead>
                                            <tr>
                                                <th>Periode</th><th>Nama Budget</th><th>Total Alokasi</th>
                                                <th>PIC</th><th>Tanggal</th><th>Status</th><th width="10%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result_biasa)): ?>
                                            <tr>
                                                <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                                <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                                <td><strong>Rp <?= number_format($row['total_allocation'],0,',','.') ?></strong></td>
                                                <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                                <td><span class="badge badge-warning badge-sm">Menunggu Kacab</span></td>
                                                <td>
                                                    <a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-check"></i> Proses
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Besar -->
                    <div class="tab-pane fade" id="panel-besar">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Anggaran Besar - Menunggu Persetujuan Kacab</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped datatable-besar">
                                        <thead>
                                            <tr>
                                                <th>Periode</th><th>Nama Anggaran</th><th>Total Alokasi</th>
                                                <th>PIC</th><th>Tanggal</th><th>Status</th><th width="10%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result_besar)): ?>
                                            <tr>
                                                <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                                <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                                <td><strong>Rp <?= number_format($row['total_allocation'],0,',','.') ?></strong></td>
                                                <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                                <td><span class="badge badge-success badge-sm">Anggaran Besar</span></td>
                                                <td>
                                                    <a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Proses
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
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
            $('.datatable-biasa, .datatable-besar').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        language: {
                            search: "Cari:", lengthMenu: "Tampilkan _MENU_ entri",
                            zeroRecords: "Tidak ada data", info: "_START_-_END_ dari _TOTAL_",
                            paginate: { previous: "&laquo;", next: "&raquo;" }
                        },
                        pageLength: 10, order: [[4, 'desc']]
                    });
                }
            });
        });
    </script>
</body>
</html>

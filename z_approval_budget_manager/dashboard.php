<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_budget_manager', 'approve');

// Selalu tampilkan semua anggaran biasa (tidak ada filter lagi)
$query = "SELECT mb.id_budget, mb.master_id, mb.period_year, mb.period_month, mb.budget_name,
    mb.total_allocation, mb.master_allocation, mb.total_remaining, mb.status, mb.created_at,
    mb.created_by, mb.notes, u.nama_user as creator_name, u.departemen as creator_departemen,
    u.divisi as creator_divisi, mc.nama_cabang as creator_cabang,
    COUNT(mbi.id_budget_item) as total_items
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN ppx_monthly_budget_item mbi ON mb.id_budget = mbi.id_budget
WHERE (mb.budget_type = 'biasa' OR mb.budget_type IS NULL)
GROUP BY mb.id_budget
ORDER BY mb.created_at DESC";

$result = mysqli_query($conn, $query);
if (!$result) die("Query Error: " . mysqli_error($conn));

// Hitung berapa yang pending approval
$pending_biasa = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ppx_monthly_budget WHERE status = 'Manager Finance' AND (budget_type='biasa' OR budget_type IS NULL)");
$pending_biasa_count = (int)(mysqli_fetch_assoc($pending_biasa)['cnt'] ?? 0);

// Anggaran Besar — tab besar
$result_besar = mysqli_query($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status = 'Manager Finance' AND mb.budget_type = 'besar'
ORDER BY mb.created_at DESC");
$count_besar = mysqli_num_rows($result_besar);

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$month_names = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Approval Anggaran Bulanan (Manager Finance)</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">

    <style>
        .table td, .table th { vertical-align: middle; }
        .badge-sm { font-size: 0.7rem; }
        .notif-pending {
            border-left: 4px solid #ffc107;
            background: #fffbea;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php
        $layout_dir = realpath(__DIR__ . '/../layouts');
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title mb-1">Dashboard Approval Anggaran Bulanan <small class="text-muted">(Manager Finance)</small></h3>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Anggaran Bulanan</li>
                            </ul>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Tab navigasi Biasa / Besar -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#panel-mng-biasa">
                            <i class="fas fa-wallet mr-1"></i>Anggaran Biasa
                            <?php if ($pending_biasa_count > 0): ?>
                                <span class="badge badge-warning"><?= $pending_biasa_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#panel-mng-besar">
                            <i class="fas fa-money-bill-wave mr-1 text-success"></i>Anggaran Besar
                            <?php if ($count_besar > 0): ?>
                                <span class="badge badge-success"><?= $count_besar ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                <!-- === TAB ANGGARAN BIASA === -->
                <div class="tab-pane fade show active" id="panel-mng-biasa">

                    <!-- Notifikasi jika ada yang menunggu approval -->
                    <?php if ($pending_biasa_count > 0): ?>
                    <div class="alert notif-pending d-flex align-items-center mb-3">
                        <i class="fas fa-bell text-warning fa-lg mr-3"></i>
                        <div>
                            <strong><?= $pending_biasa_count ?> anggaran biasa</strong> sedang menunggu persetujuan Anda.
                            Klik tombol <i class="fas fa-eye"></i> <strong>Detail</strong> pada baris yang berstatus <span class="badge badge-warning">Menunggu</span> untuk memprosesnya.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-list fa-sm"></i> Daftar Pengajuan Anggaran Bulanan</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="budgetTable" class="table table-striped table-sm">
                                            <thead>
                                                <tr>
                                                    <th width="3%">No.</th>
                                                    <th width="8%">Periode</th>
                                                    <th width="15%">Nama Budget</th>
                                                    <th width="12%">Total Alokasi</th>
                                                    <th width="5%">Items</th>
                                                    <th width="10%">PIC</th>
                                                    <th width="10%">Cabang</th>
                                                    <th width="9%">Tanggal</th>
                                                    <th width="8%">Status</th>
                                                    <th width="15%">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (mysqli_num_rows($result) > 0):
                                                    $row_num = 1;
                                                    while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td><small class="font-weight-bold"><?= $row_num++ ?></small></td>
                                                    <td><small class="font-weight-bold"><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['budget_name']) ?></small></td>
                                                    <td><small class="font-weight-bold">Rp <?= number_format($row['total_allocation'],0,',','.') ?></small></td>
                                                    <td><small><?= $row['total_items'] ?> item</small></td>
                                                    <td><small><?= htmlspecialchars($row['creator_name'] ?? '-') ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['creator_cabang'] ?? '-') ?></small></td>
                                                    <td><small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small></td>
                                                    <td><?php
                                                        $bc='secondary'; $st=$row['status'];
                                                        if ($row['status']==='Manager Finance'){$bc='warning';$st='Menunggu';}
                                                        elseif (in_array($row['status'],['Active','Approved'])){$bc='success';$st='Disetujui';}
                                                        elseif (in_array($row['status'],['Rejected','Closed'])){$bc='danger';$st='Ditolak';}
                                                        ?><span class="badge badge-<?= $bc ?> badge-sm"><?= htmlspecialchars($st) ?></span>
                                                    </td>
                                                    <td>
                                                        <!-- Hanya tombol Detail — approval dilakukan dari halaman detail -->
                                                        <a href="detail.php?id=<?= $row['id_budget'] ?>" class="btn btn-outline-info btn-sm" title="Lihat Detail">
                                                            <i class="fas fa-eye fa-xs"></i>
                                                        </a>
                                                        <?php if ($row['status'] === 'Manager Finance'): ?>
                                                        <a href="process_approve.php?id=<?= $row['id_budget'] ?>&action=approve"
                                                           class="btn btn-success btn-sm" title="Setujui">
                                                            <i class="fas fa-check fa-xs"></i>
                                                        </a>
                                                        <a href="process_approve.php?id=<?= $row['id_budget'] ?>&action=reject"
                                                           class="btn btn-danger btn-sm" title="Tolak">
                                                            <i class="fas fa-times fa-xs"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- end panel-mng-biasa -->

                <!-- === TAB ANGGARAN BESAR === -->
                <div class="tab-pane fade" id="panel-mng-besar">

                    <!-- Notifikasi jika ada yang menunggu approval -->
                    <?php if ($count_besar > 0): ?>
                    <div class="alert notif-pending d-flex align-items-center mb-3" style="border-left-color: #28a745; background: #f0fff4;">
                        <i class="fas fa-bell text-success fa-lg mr-3"></i>
                        <div>
                            <strong><?= $count_besar ?> anggaran besar</strong> sedang menunggu persetujuan Anda.
                            Klik tombol <i class="fas fa-eye"></i> <strong>Detail</strong> untuk memprosesnya.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Anggaran Besar - Menunggu Manager Finance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="budgetBesarTable" class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Periode</th>
                                            <th>Nama Anggaran</th>
                                            <th>Total Alokasi</th>
                                            <th>PIC</th>
                                            <th>Cabang</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if ($count_besar > 0): while ($row = mysqli_fetch_assoc($result_besar)): ?>
                                    <tr>
                                        <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                        <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                        <td>Rp <?= number_format($row['total_allocation'],0,',','.') ?></td>
                                        <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                        <td><?= htmlspecialchars($row['creator_cabang']) ?></td>
                                        <td><?= date('d/m/Y',strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <a href="process_approve.php?id=<?= $row['id_budget'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div><!-- end panel-mng-besar -->

                </div><!-- end tab-content -->

                <?php include '../layouts/footer.php';?>
            </div>
        </div>
    </div>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#budgetTable').DataTable({
                "ordering": false,
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "zeroRecords": "Tidak ada data yang sesuai",
                    "emptyTable": "Tidak ada data",
                    "paginate": {
                        "first": "Pertama", "last": "Terakhir",
                        "next": "&raquo;", "previous": "&laquo;"
                    }
                }
            });

            $('#budgetBesarTable').DataTable({
                "ordering": false,
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "zeroRecords": "Tidak ada data yang sesuai",
                    "emptyTable": "Tidak ada anggaran besar yang menunggu persetujuan",
                    "paginate": {
                        "first": "Pertama", "last": "Terakhir",
                        "next": "&raquo;", "previous": "&laquo;"
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('budget_besar', 'read');

$query = "SELECT mb.id_budget, mb.period_year, mb.period_month, mb.budget_name,
                 mb.total_allocation, mb.status, mb.created_at, mb.notes,
                 u.nama_user as creator_name, mc.nama_cabang
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
          WHERE mb.budget_type = 'besar'
          ORDER BY mb.created_at DESC";
$result = mysqli_query($conn, $query);

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error   = $_SESSION['error']   ?? ''; unset($_SESSION['error']);

$status_badge = [
    'Active'                => 'badge-success',
    'Selesai'               => 'badge-success',
    'Ditolak'               => 'badge-danger',
    'Draft'                 => 'badge-secondary',
    'Finance (Realisasi)'   => 'badge-info',
    'Kacab'                 => 'badge-warning',
    'Asmen Finance'         => 'badge-warning',
    'Manager Finance'       => 'badge-warning',
    'Manager Operasional'   => 'badge-warning',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggaran Bulanan Besar</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <!-- DataTables CSS Bootstrap 4 formattings -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        .sidebar { z-index: 1051 !important; }
        .header { z-index: 1052 !important; }
        .sidebar-overlay { display: none !important; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">

                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title"><i class="fas fa-money-bill-wave text-success"></i> Anggaran Bulanan Besar</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Anggaran Besar</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <?php if ($auth->hasPermission('budget_besar','create')): ?>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Buat Anggaran Besar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table"></i> Daftar Anggaran Besar</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <!-- Menghilangkan class "datatable" agar script bawaan tidak menimpanya -->
                                    <table id="budgetTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th>Nama Anggaran</th>
                                                <th>Periode</th>
                                                <th>Total Alokasi</th>
                                                <th>Pengaju</th>
                                                <th>Cabang</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th width="8%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($result && mysqli_num_rows($result) > 0): $no=1; while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><strong><?= htmlspecialchars($row['budget_name']) ?></strong></td>
                                                <td><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></td>
                                                <td>Rp <?= number_format($row['total_allocation'],0,',','.') ?></td>
                                                <td><?= htmlspecialchars($row['creator_name'] ?? '-') ?></td>
                                                <td><span class="badge badge-info"><?= htmlspecialchars($row['nama_cabang'] ?? '-') ?></span></td>
                                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge <?= $status_badge[$row['status']] ?? 'badge-warning' ?>">
                                                        <?= htmlspecialchars($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="detail.php?id=<?= $row['id_budget'] ?>" class="btn btn-outline-info btn-sm" title="Detail">
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
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- DataTables JS & Bootstrap 4 integration -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTable khusus untuk format tampilan yang rapi dengan Bootstrap 4
            $('#budgetTable').DataTable({
                "order": [[6, "desc"]], // Urutkan berdasarkan tanggal terbaru (kolom Tanggal, 0-indexed: 6)
                "pageLength": 10,
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "Semua"] ],
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ entri",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 entri",
                    "infoFiltered": "(disaring dari _MAX_ total data)",
                    "zeroRecords": "Tidak ditemukan data yang sesuai",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        });
    </script>
</body>
</html>

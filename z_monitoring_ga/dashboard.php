<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';
date_default_timezone_set('Asia/Jakarta');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('monitoring_ga', 'read');

$canCreate = $auth->hasPermission('monitoring_ga', 'create');
$canUpdate = $auth->hasPermission('monitoring_ga', 'update');
$canDelete = $auth->hasPermission('monitoring_ga', 'delete');

// Ambil bagian_ga milik user yang login (jika PIC) - membatasi scope data
$myBagian = null;
$q_me = mysqli_query($conn, "SELECT bagian_ga FROM user_dfm WHERE id_user = '" . mysqli_real_escape_string($conn, $_SESSION['id_user']) . "'");
if ($q_me && $row_me = mysqli_fetch_assoc($q_me)) {
    $myBagian = $row_me['bagian_ga'] ?: null;
}
$isScoped = !$auth->isSuperAdmin() && !empty($myBagian);

$BAGIAN_LIST = [
    'Aset Management & Kebersihan',
    'Kendaraan',
    'Administrasi',
    'Building Management & Mechanical Electrical',
];
$STATUS_LIST = ['Belum Dikerjakan', 'Sedang Dikerjakan', 'Sudah Dikerjakan'];

// --- Filter ---
$f_bagian = $_GET['bagian'] ?? '';
$f_status = $_GET['status'] ?? '';
$f_from   = $_GET['from'] ?? '';
$f_to     = $_GET['to'] ?? '';

if ($isScoped) {
    $f_bagian = $myBagian; // PIC terkunci ke bagiannya sendiri
}

$where = [];
if ($f_bagian !== '' && in_array($f_bagian, $BAGIAN_LIST)) {
    $where[] = "t.bagian = '" . mysqli_real_escape_string($conn, $f_bagian) . "'";
}
if ($f_status !== '' && in_array($f_status, $STATUS_LIST)) {
    $where[] = "t.status = '" . mysqli_real_escape_string($conn, $f_status) . "'";
}
if ($f_from !== '') {
    $where[] = "t.tanggal_mulai >= '" . mysqli_real_escape_string($conn, $f_from) . "'";
}
if ($f_to !== '') {
    $where[] = "t.tanggal_mulai <= '" . mysqli_real_escape_string($conn, $f_to) . "'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Summary cards (ikut filter bagian/scope, tapi bukan filter status/tanggal) ---
$sumWhere = $isScoped ? "WHERE t.bagian = '" . mysqli_real_escape_string($conn, $myBagian) . "'" : '';
$sum = ['total' => 0, 'Belum Dikerjakan' => 0, 'Sedang Dikerjakan' => 0, 'Sudah Dikerjakan' => 0];
$q_sum = mysqli_query($conn, "SELECT status, COUNT(*) AS jml FROM ga_tasks t $sumWhere GROUP BY status");
if ($q_sum) {
    while ($r = mysqli_fetch_assoc($q_sum)) {
        $sum[$r['status']] = (int)$r['jml'];
        $sum['total'] += (int)$r['jml'];
    }
}

// --- List data ---
$sql = "SELECT t.*, u.nama_user
        FROM ga_tasks t
        LEFT JOIN user_dfm u ON t.id_user = u.id_user
        $whereSql
        ORDER BY t.tanggal_mulai DESC, t.id_task DESC";
$result = mysqli_query($conn, $sql);

$statusBadge = [
    'Belum Dikerjakan'  => 'badge-danger',
    'Sedang Dikerjakan' => 'badge-warning',
    'Sudah Dikerjakan'  => 'badge-success',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoring Pekerjaan GA</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        /* Tabel tetap enak dibaca di layar mobile - scroll ke samping daripada kolom himpit */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #eef2f6;
            border-radius: 8px;
            padding-bottom: 4px;
        }
        #gaTaskTable {
            width: 100% !important;
            min-width: 1000px;
            margin-bottom: 0 !important;
        }
        #gaTaskTable thead th {
            white-space: nowrap !important;
            vertical-align: middle !important;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            background-color: #f8fafc;
            border-top: none;
            border-bottom: 2px solid #e2e8f0;
            padding: 10px 8px !important;
        }
        #gaTaskTable tbody td {
            vertical-align: middle !important;
            white-space: nowrap;
            padding: 8px !important;
            font-size: 13px;
        }
        #gaTaskTable tbody td.col-deskripsi,
        #gaTaskTable tbody td.col-note {
            white-space: normal !important;
            min-width: 160px;
            max-width: 240px;
            line-height: 1.35;
        }
        @media (max-width: 767px) {
            .page-header .page-title { font-size: 1.25rem; }
            .card-body .row > div { margin-bottom: 8px; }
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="page-title">
                            <i class="fas fa-tasks text-primary"></i> Monitoring Pekerjaan GA
                            <?php if ($isScoped): ?>
                                <small class="text-muted" style="font-size:0.9rem"> — Bagian: <?= htmlspecialchars($myBagian) ?></small>
                            <?php endif; ?>
                        </h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Monitoring GA</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="card"><div class="card-body text-center">
                        <h4 class="mb-0"><?= $sum['total'] ?></h4>
                        <small class="text-muted">Total Pekerjaan</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card"><div class="card-body text-center">
                        <h4 class="mb-0 text-danger"><?= $sum['Belum Dikerjakan'] ?></h4>
                        <small class="text-muted">Belum Dikerjakan</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card"><div class="card-body text-center">
                        <h4 class="mb-0 text-warning"><?= $sum['Sedang Dikerjakan'] ?></h4>
                        <small class="text-muted">Sedang Dikerjakan</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card"><div class="card-body text-center">
                        <h4 class="mb-0 text-success"><?= $sum['Sudah Dikerjakan'] ?></h4>
                        <small class="text-muted">Sudah Dikerjakan</small>
                    </div></div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row">
                        <?php if (!$isScoped): ?>
                        <div class="col-12 col-md-3 mb-2">
                            <label>Bagian</label>
                            <select name="bagian" class="form-control">
                                <option value="">-- Semua Bagian --</option>
                                <?php foreach ($BAGIAN_LIST as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= $f_bagian === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12 col-md-3 mb-2">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">-- Semua Status --</option>
                                <?php foreach ($STATUS_LIST as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $f_status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 mb-2">
                            <label class="text-nowrap">Dari Tanggal</label>
                            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($f_from) ?>">
                        </div>
                        <div class="col-12 col-md-2 mb-2">
                            <label class="text-nowrap">Sampai Tanggal</label>
                            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($f_to) ?>">
                        </div>
                        <div class="col-12 col-md-2 mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body">
                    <?php if ($canCreate): ?>
                        <a href="task_add.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Tambah Pekerjaan</a>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="gaTaskTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Tanggal Mulai</th>
                                    <?php if (!$isScoped): ?><th>Bagian</th><?php endif; ?>
                                    <th>PIC</th>
                                    <th>Keterangan Pekerjaan</th>
                                    <th>Status</th>
                                    <th>Tanggal Selesai</th>
                                    <th>Durasi</th>
                                    <th>Note</th>
                                    <th>Lampiran</th>
                                    <?php if ($canUpdate || $canDelete): ?><th>Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?></td>
                                            <?php if (!$isScoped): ?><td><?= htmlspecialchars($row['bagian']) ?></td><?php endif; ?>
                                            <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                                            <td class="col-deskripsi"><?= nl2br(htmlspecialchars($row['deskripsi_pekerjaan'])) ?></td>
                                            <td><span class="badge <?= $statusBadge[$row['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                            <td><?= !empty($row['tanggal_selesai']) ? date('d/m/Y', strtotime($row['tanggal_selesai'])) : '-' ?></td>
                                            <?php
                                                // Durasi inklusif: mulai = selesai dihitung 1 hari
                                                $durasiHari = null;
                                                if (!empty($row['tanggal_mulai']) && !empty($row['tanggal_selesai']) && $row['tanggal_selesai'] >= $row['tanggal_mulai']) {
                                                    $durasiHari = (new DateTime($row['tanggal_mulai']))->diff(new DateTime($row['tanggal_selesai']))->days + 1;
                                                }
                                            ?>
                                            <td data-order="<?= $durasiHari ?? 0 ?>"><?= $durasiHari !== null ? '<span class="badge badge-info">' . $durasiHari . ' hari</span>' : '-' ?></td>
                                            <td class="col-note"><?= $row['keterangan'] ? nl2br(htmlspecialchars($row['keterangan'])) : '-' ?></td>
                                            <td>
                                                <?php if (!empty($row['lampiran'])): ?>
                                                    <a href="../uploads/ga_tasks/<?= htmlspecialchars($row['lampiran']) ?>" target="_blank"><i class="fas fa-paperclip"></i> Lihat</a>
                                                <?php else: ?>-<?php endif; ?>
                                            </td>
                                            <?php if ($canUpdate || $canDelete): ?>
                                            <td class="text-nowrap">
                                                <?php if ($canUpdate): ?>
                                                    <a href="task_edit.php?id=<?= (int)$row['id_task'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <?php if ($canDelete): ?>
                                                    <a href="task_delete.php?id=<?= (int)$row['id_task'] ?>" class="btn btn-sm btn-danger btn-delete-confirm"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    <?php include '../layouts/footer.php'; ?>

<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function() {
    $('#gaTaskTable').DataTable({
        order: [],
        pageLength: 25,
        autoWidth: false,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "&raquo;",
                previous: "&laquo;"
            },
            emptyTable: "Belum ada data pekerjaan.",
            zeroRecords: "Tidak ada data yang cocok"
        }
    });
});
</script>
</body>
</html>

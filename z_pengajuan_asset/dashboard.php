<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\dashboard.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');



$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission (Bisa masuk jika punya izin Pengajuan Umum ATAU Approver IT/GA)
if (!$auth->hasPermission('pengajuan_asset', 'read') && 
    !$auth->hasPermission('approval_asset_it', 'read') && 
    !$auth->hasPermission('approval_asset_ga', 'read')) {
    $auth->requirePermission('pengajuan_asset', 'read'); // Ini akan memicu alert akses ditolak
}

// Buat kondisi WHERE berdasarkan permission
// User dengan 'approve'
$isGlobalApprover = $auth->hasPermission('pengajuan_asset', 'approve');

// Build WHERE conditions
$where_conditions = [];
$user_id = (int)$_SESSION['id_user'];
$own_request_condition = "ar.user_id = $user_id";

// Permission spesifik untuk sub kategori (menggunakan permission baru dari config)
$canApproveIT = $auth->hasPermission('approval_asset_it', 'approve');
$canApproveGA = $auth->hasPermission('approval_asset_ga', 'approve');

$canApproveAsset = $isGlobalApprover || $canApproveIT || $canApproveGA;

if ($auth->isSuperAdmin() || $auth->isHr()) {
    // Super Admin & HR see everything
} elseif ($canApproveAsset) {
    // User dengan approve permission
    $role_filters = [];
    if ($canApproveIT && !$canApproveGA) {
        $role_filters[] = "ka.sub_kategori = 'IT'";
    } elseif ($canApproveGA && !$canApproveIT) {
        $role_filters[] = "ka.sub_kategori = 'GA'";
    }
    
    if (!empty($role_filters)) {
        // Jika user adalah approver spesifik (punya IT atau GA tapi BUKAN global approver)
        if (!$isGlobalApprover) {
            $where_conditions[] = "(" . implode(" OR ", $role_filters) . " OR $own_request_condition)";
        }
        // Jika isGlobalApprover == true, kondisi tidak ditambahkan (sehingga bisa melihat semua data)
    } else {
        // Jika tidak punya IT atau GA, tapi punya global approver -> lihat semua
        if (!$isGlobalApprover) {
            $where_conditions[] = $own_request_condition;
        }
    }
} else {
    // Normal users ONLY see their own
    $where_conditions[] = $own_request_condition;
}

// Filter soft-delete
$where_conditions[] = "(ar.is_deleted IS NULL OR ar.is_deleted = 0)";

// Build WHERE clause
$whereClause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT ar.*, ka.kategori_asset, ka.sub_kategori, u.nama_user as nama_pengaju
         FROM asset_request ar
         LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
         LEFT JOIN user_dfm u ON ar.user_id = u.id_user
         $whereClause
         ORDER BY 
         CASE ar.status 
             WHEN 'Pending' THEN 1 
             WHEN 'Disetujui' THEN 2 
             WHEN 'Ditolak' THEN 3 
             ELSE 4 
         END,
         ar.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);

// Statistics query - Untuk semua user, tapi filter sesuai permission
$stats = [];
// Base query
$stats_query = "SELECT 
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'Disetujui' AND (status_pendistribusian IS NULL OR status_pendistribusian != 'Sudah didistribusikan') THEN 1 ELSE 0 END) as menunggu_distribusi,
    SUM(CASE WHEN status_pendistribusian = 'Sudah didistribusikan' THEN 1 ELSE 0 END) as selesai_distribusi,
    COUNT(*) as total
FROM asset_request ar";

// Tambahkan join jika filter sub_kategori diperlukan
$stats_joins = "";
$stats_where_conditions = [];

// Filter berdasarkan sub_kategori untuk GA atau IT (hanya jika punya permission approve)
$user_id = (int)$_SESSION['id_user'];
$own_request_condition = "ar.user_id = $user_id";

if ($auth->isSuperAdmin() || $auth->isHr()) {
    // No filter
} elseif ($canApproveAsset) {
    $stats_joins = "LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori";
    $role_filters = [];
    if ($canApproveIT && !$canApproveGA) {
        $role_filters[] = "ka.sub_kategori = 'IT'";
    } elseif ($canApproveGA && !$canApproveIT) {
        $role_filters[] = "ka.sub_kategori = 'GA'";
    }
    
    if (!empty($role_filters)) {
        if (!$isGlobalApprover) {
            $stats_where_conditions[] = "(" . implode(" OR ", $role_filters) . " OR $own_request_condition)";
        }
    } else {
        if (!$isGlobalApprover) {
            $stats_where_conditions[] = $own_request_condition;
        }
    }
} else {
    $stats_where_conditions[] = $own_request_condition;
}

// Filter soft-delete
$stats_where_conditions[] = "(ar.is_deleted IS NULL OR ar.is_deleted = 0)";

// Gabungkan query
$full_stats_query = $stats_query;
if (!empty($stats_joins)) {
    $full_stats_query .= " " . $stats_joins;
}
if (!empty($stats_where_conditions)) {
    $full_stats_query .= " WHERE " . implode(" AND ", $stats_where_conditions);
}

$stats_result = mysqli_query($conn, $full_stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Function untuk menentukan badge distribusi
function getDistributionBadge($status) {
    if (!$status) return ['badge' => 'secondary', 'text' => 'Belum diproses'];
    
    switch ($status) {
        case 'Belum di order':
            return ['badge' => 'warning', 'text' => $status];
        case 'Sudah diorder':
            return ['badge' => 'info', 'text' => $status];
        case 'Barang sampai':
            return ['badge' => 'primary', 'text' => $status];
        case 'Sudah didistribusikan':
            return ['badge' => 'success', 'text' => $status];
        default:
            return ['badge' => 'secondary', 'text' => $status];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Pengajuan Asset</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        /* ═════════════════════════════════════════════════════════════════════
           RESPONSIVE & COMPACT TABLE FIX (MENCEGAH TEKS TERPOTONG DI LAYAR 100%)
           ═════════════════════════════════════════════════════════════════════ */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #eef2f6;
            border-radius: 8px;
            padding-bottom: 4px;
        }

        #pengajuanTable {
            width: 100% !important;
            min-width: 1150px; /* Menjamin seluruh kolom tampil rapi tanpa terhimpit */
            margin-bottom: 0 !important;
        }

        #pengajuanTable thead th {
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

        #pengajuanTable tbody td {
            vertical-align: middle !important;
            white-space: nowrap; /* Mencegah nomor, nama, tanggal, status terpotong berbaris-baris */
            padding: 8px 8px !important;
            font-size: 13px;
        }

        /* Kolom deskripsi boleh wrap secara terkontrol */
        #pengajuanTable tbody td.col-deskripsi {
            white-space: normal !important;
            min-width: 150px;
            max-width: 220px;
            line-height: 1.35;
        }

        /* Group tombol aksi agar tetap berjejer rapi horizontal */
        .action-btn-group {
            display: inline-flex;
            gap: 3px;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
        }

        .btn-action {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 28px !important;
            height: 28px !important;
            padding: 0 !important;
            font-size: 11px !important;
            line-height: 1 !important;
            border-radius: 4px !important;
            margin: 0 !important;
            transition: all 0.15s ease;
        }

        .btn-action:hover {
            transform: scale(1.08);
        }

        /* ═══════════════════════════════════════════
           KOLOM AKSI MENGAMBANG (STICKY FLOATING RIGHT)
           ═══════════════════════════════════════════ */
        #pengajuanTable th.col-action,
        #pengajuanTable td.col-action {
            position: sticky !important;
            right: 0 !important;
            border-left: 1px solid #e2e8f0 !important;
            box-shadow: -4px 0 8px -2px rgba(0, 0, 0, 0.08);
        }

        #pengajuanTable thead th.col-action {
            background-color: #f8fafc !important;
            z-index: 5;
        }

        #pengajuanTable tbody tr td.col-action {
            background-color: #ffffff;
            z-index: 4;
        }

        /* Zebra-striped background agar tidak tembus pandang */
        #pengajuanTable tbody tr:nth-of-type(odd) td.col-action {
            background-color: #f8fafc;
        }

        #pengajuanTable tbody tr:hover td.col-action {
            background-color: #f1f5f9 !important;
        }

        .card-stats {
            transition: all 0.2s ease;
            height: 100px;
        }

        .card-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            opacity: 0.6;
            font-size: 1.3rem;
        }

        .stats-card-title {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #6c757d;
            line-height: 1.2;
        }

        .stats-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .card-stats .card-body {
            padding: 0.75rem;
        }
        
        .badge-it {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-ga {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <!-- Left Side: Title & Breadcrumb -->
                                <div>
                                    <h3 class="page-title mb-2">Dashboard Pengajuan Asset</h3>
                                    <ul class="breadcrumb mb-2">
                                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Pengajuan Asset</li>
                                    </ul>
                                    
                                    <!-- Button Group - Berjejer Horizontal -->
                                    <div class="d-flex align-items-center" style="gap: 10px;">
                                        <!-- Button Tambah Pengajuan -->
                                        <?php if ($auth->canCreateAsset()): ?>
                                            <a href="add.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus fa-sm"></i> Tambah Pengajuan
                                            </a>
                                        <?php endif; ?>
                                        <a href="print.php" target="_blank" class="btn btn-info btn-sm">
                                            <i class="fas fa-print fa-sm"></i> Print Laporan
                                        </a>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
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

                <!-- Role Info -->
                <?php if (!$canApproveAsset): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <i class="fas fa-info-circle fa-sm"></i> 
                        <strong>Info:</strong> Anda hanya melihat pengajuan asset yang Anda ajukan sendiri.
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php elseif ($canApproveIT && !$canApproveGA): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <i class="fas fa-laptop-code fa-sm"></i> 
                        <strong>Info IT:</strong> Anda melihat pengajuan asset sub kategori IT & pengajuan milik Anda sendiri.
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php elseif ($canApproveGA && !$canApproveIT): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-building fa-sm"></i> 
                        <strong>Info GA:</strong> Anda melihat pengajuan asset sub kategori GA & pengajuan milik Anda sendiri.
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php elseif ($auth->isHr() || $auth->isSuperAdmin()): ?>
                    <div class="alert alert-primary alert-dismissible fade show">
                        <i class="fas fa-user-shield fa-sm"></i> 
                        <strong>Info:</strong> Anda melihat semua pengajuan asset dari semua divisi.
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- STATISTIK UNTUK HR, GA, IT & SUPER ADMIN -->
                <!-- <?php if ($auth->isHr() || $auth->isGa() || $auth->isSuperAdmin() || $auth->isIT()): ?>
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-info">Total Pengajuan</div>
                                            <h3 class="stats-number text-info"><?= $stats['total'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-clipboard-list stats-icon text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-warning">Pending</div>
                                            <h3 class="stats-number text-warning"><?= $stats['pending'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-clock stats-icon text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-success">Disetujui</div>
                                            <h3 class="stats-number text-success"><?= $stats['approved'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-check-circle stats-icon text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-danger">Ditolak</div>
                                            <h3 class="stats-number text-danger"><?= $stats['rejected'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-times-circle stats-icon text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-primary">Menunggu Distribusi</div>
                                            <h3 class="stats-number text-primary"><?= $stats['menunggu_distribusi'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-hourglass-half stats-icon text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center h-100">
                                        <div>
                                            <div class="stats-card-title text-dark">Selesai Distribusi</div>
                                            <h3 class="stats-number text-dark"><?= $stats['selesai_distribusi'] ?? 0 ?></h3>
                                        </div>
                                        <i class="fas fa-check-double stats-icon text-dark"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?> -->

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan Asset
                                    <?php if (!$canApproveAsset): ?>
                                        <small class="text-muted">- Pengajuan Saya</small>
                                    <?php elseif ($canApproveIT && !$canApproveGA): ?>
                                        <small class="text-muted">- Sub Kategori IT & Milik Saya</small>
                                    <?php elseif ($canApproveGA && !$canApproveIT): ?>
                                        <small class="text-muted">- Sub Kategori GA & Milik Saya</small>
                                    <?php elseif ($auth->isHr() || $auth->isSuperAdmin() || ($canApproveIT && $canApproveGA)): ?>
                                        <small class="text-muted">- Semua Pengajuan</small>
                                    <?php else: ?>
                                        <small class="text-muted">- Semua Pengajuan</small>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="pengajuanTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 40px;">No</th>
                                                <th style="min-width: 140px;">No. Pengajuan</th>
                                                <th style="min-width: 90px;">Tanggal</th>
                                                <th style="min-width: 130px;">Pengaju</th>
                                                <th style="min-width: 130px;">Kategori</th>
                                                <th style="min-width: 160px;">Deskripsi</th>
                                                <th class="text-center" style="min-width: 90px;">Status</th>
                                                <th class="text-center" style="min-width: 110px;">Status Distribusi</th>
                                                <!-- Kolom khusus GA, IT & Super Admin -->
                                                <?php if ($canApproveGA || $auth->isSuperAdmin() || $canApproveIT): ?>
                                                    <th class="text-center" style="min-width: 110px;">Kelola Distribusi</th>
                                                <?php endif; ?>
                                                <th class="text-center col-action" style="min-width: 135px; width: 135px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result)): 
                                            ?>
                                                <tr>
                                                    <td class="text-center text-nowrap"><small class="font-weight-bold"><?= $no++ ?></small></td>
                                                    <td class="text-nowrap"><small class="font-weight-bold"><?= htmlspecialchars($row['no_pengajuan']) ?></small></td>
                                                    <td class="text-nowrap"><small><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></small></td>
                                                    <td class="text-nowrap"><small class="font-weight-bold"><?= htmlspecialchars($row['nama_pic']) ?></small></td>
                                                    <td class="text-nowrap">
                                                        <small>
                                                            <?= htmlspecialchars($row['kategori_asset']) ?>
                                                            <?php if ($row['sub_kategori'] == 'IT'): ?>
                                                                <span class="badge badge-it">IT</span>
                                                            <?php elseif ($row['sub_kategori'] == 'GA'): ?>
                                                                <span class="badge badge-ga">GA</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                    <td class="col-deskripsi">
                                                        <small>
                                                            <?= htmlspecialchars(substr($row['deskripsi_asset'], 0, 50)) ?>
                                                            <?= strlen($row['deskripsi_asset']) > 50 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <!-- Status Pengajuan -->
                                                    <td>
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        if ($row['status'] == 'Pending') $badge_class = 'warning';
                                                        if ($row['status'] == 'Disetujui') $badge_class = 'success';
                                                        if ($row['status'] == 'Ditolak') $badge_class = 'danger';
                                                        ?>
                                                        <span class="badge badge-<?= $badge_class ?> badge-sm">
                                                            <?= htmlspecialchars($row['status']) ?>
                                                        </span>
                                                    </td>
                                                    <!-- Status Distribusi untuk Semua Role -->
                                                    <td class="text-center">
                                                        <?php if ($row['status'] == 'Disetujui'): ?>
                                                            <?php 
                                                            $distribution = getDistributionBadge($row['status_pendistribusian']); 
                                                            ?>
                                                            <span class="badge badge-<?= $distribution['badge'] ?> badge-sm">
                                                                <?= htmlspecialchars($distribution['text']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <small class="text-muted">-</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    
                                                    <!-- Kolom Kelola Distribusi - DITAMBAH UNTUK IT -->
                                                    <?php if ($canApproveGA || $auth->isSuperAdmin() || $canApproveIT): ?>
                                                        <td class="text-center">
                                                            <?php 
                                                            // GA hanya bisa distribusi GA asset
                                                            // IT hanya bisa distribusi IT asset
                                                            $canDistribute = true;
                                                            if ($canApproveGA && !$canApproveIT && $row['sub_kategori'] == 'IT') {
                                                                $canDistribute = false;
                                                            } elseif ($canApproveIT && !$canApproveGA && $row['sub_kategori'] != 'IT') {
                                                                $canDistribute = false;
                                                            }
                                                            ?>
                                                            
                                                            <?php if ($row['status'] == 'Disetujui' && $canDistribute): ?>
                                                                <button type="button" class="btn btn-outline-success btn-action"
                                                                    onclick="processDistribution('<?= $row['no_pengajuan'] ?>', '<?= $row['status_pendistribusian'] ?? '' ?>')"
                                                                    title="Kelola Pendistribusian">
                                                                    <i class="fas fa-truck fa-xs"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <small class="text-muted">-</small>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Aksi -->
                                                    <td class="text-center text-nowrap col-action">
                                                        <div class="action-btn-group">
                                                            <!-- Detail Button -->
                                                            <button type="button" class="btn btn-outline-info btn-action"
                                                                onclick="showDetail('<?= $row['no_pengajuan'] ?>')" title="Detail">
                                                                <i class="fas fa-eye fa-xs"></i>
                                                            </button>

                                                            <!-- Print Button -->
                                                            <a href="print.php?id=<?= urlencode($row['no_pengajuan']) ?>" 
                                                               target="_blank" class="btn btn-outline-secondary btn-action" title="Print Form">
                                                                <i class="fas fa-print fa-xs"></i>
                                                            </a>

                                                            <!-- Process Button untuk HR/GA/IT/Admin -->
                                                            <?php 
                                                            $canApprove = false;
                                                            
                                                            if ($auth->isSuperAdmin()) {
                                                                // Super admin approve semua
                                                                $canApprove = true;
                                                            } elseif ($auth->isHr()) {
                                                                // HR approve semua
                                                                $canApprove = true;
                                                            } elseif ($canApproveAsset) {
                                                                // Jika dia adalah approver, cek sub_kategori yang boleh diapprove
                                                                if ($canApproveGA && $row['sub_kategori'] == 'GA') {
                                                                    $canApprove = true;
                                                                } elseif ($canApproveIT && $row['sub_kategori'] == 'IT') {
                                                                    $canApprove = true;
                                                                } elseif ($canApproveGA && $canApproveIT) {
                                                                    // Jika punya dua-duanya, bisa approve semuanya
                                                                    $canApprove = true;
                                                                } elseif (!$canApproveGA && !$canApproveIT) {
                                                                    // Punya 'approve' tapi bukan IT/GA (misal manager), approve semua
                                                                    $canApprove = true;
                                                                }
                                                            }
                                                            
                                                            if ($canApprove && $row['status'] == 'Pending'):
                                                            ?>
                                                                <button type="button" class="btn btn-outline-primary btn-action"
                                                                    onclick="processAction('<?= $row['no_pengajuan'] ?>', '<?= $row['sub_kategori'] ?>')" 
                                                                    title="Proses Pengajuan">
                                                                    <i class="fas fa-cog fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <!-- Edit untuk user yang mengajukan sendiri jika pending dan punya hak create/update -->
                                                            <?php if (($auth->canUpdateAsset() || ($auth->canCreateAsset() && $row['status'] == 'Pending')) && $row['user_id'] == $_SESSION['id_user']): ?>
                                                                <a href="edit.php?id=<?= $row['no_pengajuan'] ?>"
                                                                    class="btn btn-outline-warning btn-action" title="Edit">
                                                                    <i class="fas fa-edit fa-xs"></i>
                                                                </a>
                                                            <?php endif; ?>

                                                            <!-- Delete dengan aturan role -->
                                                            <?php 
                                                            $can_delete = false;
                                                            if ($auth->isSuperAdmin()) {
                                                                // Super Admin bisa hapus semua
                                                                $can_delete = true;
                                                            } elseif ($row['status'] == 'Pending') {
                                                                // Jika status Pending
                                                                if ($row['user_id'] == $_SESSION['id_user']) {
                                                                    // User yang mengajukan bisa hapus pengajuan sendiri
                                                                    $can_delete = true;
                                                                } elseif ($auth->isHr()) {
                                                                    // HR bisa hapus semua pengajuan Pending
                                                                    $can_delete = true;
                                                                } elseif ($canApproveGA && $row['sub_kategori'] == 'GA') {
                                                                    // GA bisa hapus pengajuan Pending sub_kategori GA
                                                                    $can_delete = true;
                                                                } elseif ($canApproveIT && $row['sub_kategori'] == 'IT') {
                                                                    // IT bisa hapus pengajuan Pending sub_kategori IT
                                                                    $can_delete = true;
                                                                }
                                                            }
                                                            ?>
                                                            
                                                            <?php if ($can_delete): ?>
                                                                <button type="button" class="btn btn-outline-danger btn-action"
                                                                    onclick="deleteRequest('<?= $row['no_pengajuan'] ?>', '<?= $row['status'] ?>')" title="Hapus">
                                                                    <i class="fas fa-trash fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
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

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-info-circle fa-sm"></i> Detail Pengajuan</h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="detailContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times fa-xs"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Aksi - Untuk HR/GA/IT/Super Admin -->
    <?php if ($auth->isHr() || $canApproveGA || $auth->isSuperAdmin() || $canApproveIT): ?>
        <div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">
                            <i class="fas fa-cog fa-sm text-primary"></i> Proses Pengajuan
                        </h6>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" action="process_approval.php">
                        <div class="modal-body">
                            <input type="hidden" name="no_pengajuan" id="action_no_pengajuan">
                            <input type="hidden" name="sub_kategori" id="action_sub_kategori">

                            <!-- Info Pengajuan -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle fa-sm"></i>
                                <strong>No. Pengajuan:</strong> <span id="display_no_pengajuan"></span><br>
                                <strong>Sub Kategori:</strong> <span id="display_sub_kategori"></span>
                                <span id="kategori_badge" class="badge ml-1"></span>
                            </div>
                            
                            <!-- Info peran -->
                            <?php if ($canApproveIT && !$canApproveGA): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-laptop-code fa-sm"></i> 
                                    <small>Anda bertindak sebagai <strong>IT</strong> untuk menyetujui pengajuan ini.</small>
                                </div>
                            <?php elseif ($canApproveGA && !$canApproveIT): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-building fa-sm"></i> 
                                    <small>Anda bertindak sebagai <strong>GA</strong> untuk menyetujui pengajuan ini.</small>
                                </div>
                            <?php endif; ?>

                            <!-- Pilihan Aksi -->
                            <div class="form-group">
                                <label class="small font-weight-bold">Keputusan <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="custom-control custom-radio">
                                            <input type="radio" class="custom-control-input" id="action_approve"
                                                name="decision" value="approve" onchange="updateAction('approve')">
                                            <label class="custom-control-label text-success" for="action_approve">
                                                <i class="fas fa-check-circle fa-sm"></i> Setujui
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="custom-control custom-radio">
                                            <input type="radio" class="custom-control-input" id="action_reject"
                                                name="decision" value="reject" onchange="updateAction('reject')">
                                            <label class="custom-control-label text-danger" for="action_reject">
                                                <i class="fas fa-times-circle fa-sm"></i> Tolak
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Catatan -->
                            <div class="form-group">
                                <label class="small font-weight-bold">
                                    <span id="catatan_label">Catatan</span>
                                    <span class="text-danger" id="catatan_required" style="display:none;">*</span>
                                </label>
                                <textarea name="catatan" id="catatan_approval" class="form-control form-control-sm"
                                    rows="3" placeholder="Masukkan catatan untuk keputusan ini..."></textarea>
                                <small class="text-muted" id="catatan_help">
                                    Berikan alasan atau catatan untuk keputusan Anda
                                </small>
                            </div>

                            <!-- Alert Status -->
                            <div class="alert alert-success" id="approve_alert" style="display:none;">
                                <i class="fas fa-check-circle fa-sm"></i>
                                <small>Pengajuan akan diubah statusnya menjadi <strong>"Disetujui"</strong></small>
                            </div>

                            <div class="alert alert-danger" id="reject_alert" style="display:none;">
                                <i class="fas fa-times-circle fa-sm"></i>
                                <small>Pengajuan akan diubah statusnya menjadi <strong>"Ditolak"</strong></small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                                <i class="fas fa-times fa-xs"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm" id="submit_action" disabled>
                                <i class="fas fa-paper-plane fa-xs"></i> Proses
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Pendistribusian - Untuk GA, IT & Super Admin -->
    <?php if ($canApproveGA || $auth->isSuperAdmin() || $canApproveIT): ?>
        <div class="modal fade" id="distributionModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">
                            <i class="fas fa-truck fa-sm text-success"></i> Kelola Pendistribusian
                        </h6>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" action="process_distribution.php">
                        <div class="modal-body">
                            <input type="hidden" name="no_pengajuan" id="dist_no_pengajuan">

                            <!-- Info Pengajuan -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle fa-sm"></i>
                                <strong>No. Pengajuan:</strong> <span id="dist_display_no_pengajuan"></span>
                            </div>

                            <!-- Info peran -->
                            <?php if ($canApproveIT && !$canApproveGA): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-laptop-code fa-sm"></i> 
                                    <small>Anda mengelola distribusi sebagai <strong>IT Support</strong>.</small>
                                </div>
                            <?php elseif ($canApproveGA && !$canApproveIT): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-building fa-sm"></i> 
                                    <small>Anda mengelola distribusi sebagai <strong>GA</strong>.</small>
                                </div>
                            <?php endif; ?>

                            <!-- Status Pendistribusian -->
                            <div class="form-group">
                                <label class="small font-weight-bold">Status Pendistribusian <span class="text-danger">*</span></label>
                                <select name="status_pendistribusian" id="status_pendistribusian" class="form-control form-control-sm" required>
                                    <option value="">-- Pilih Status --</option>
                                    <option value="Belum di order">Belum di order</option>
                                    <option value="Sudah diorder">Sudah diorder</option>
                                    <option value="Barang sampai">Barang sampai</option>
                                    <option value="Sudah didistribusikan">Sudah didistribusikan</option>
                                </select>
                            </div>

                            <!-- Status Progress Info -->
                            <div class="alert alert-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Tahapan:</strong><br>
                                    1. Belum di order → 2. Sudah diorder → 3. Barang sampai → 4. Sudah didistribusikan
                                </small>
                            </div>

                            <!-- TAMBAHAN: Info akses -->
                            <div class="alert alert-warning">
                                <small>
                                    <i class="fas fa-lock"></i>
                                    <strong>Akses Terbatas:</strong> 
                                    <?php if ($auth->isIT()): ?>
                                        IT hanya dapat mengelola distribusi untuk asset dengan sub kategori IT.
                                    <?php elseif ($auth->isGa()): ?>
                                        GA hanya dapat mengelola distribusi untuk asset dengan sub kategori GA.
                                    <?php else: ?>
                                        Admin dapat mengelola distribusi untuk semua kategori asset.
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                                <i class="fas fa-times fa-xs"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-save fa-xs"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

<!-- Load DataTables CDN -->
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
// Pastikan jQuery sudah loaded, baru jalankan script
if (typeof jQuery === 'undefined') {
    console.error('jQuery belum loaded!');
} else {
    console.log('jQuery version:', $.fn.jquery);
}

$(document).ready(function() {
    console.log('Document ready, initializing...');
    
    // DataTable initialization
    try {
        $('#pengajuanTable').DataTable({
            "order": [],
            "ordering": false,
            "pageLength": 10,
            "autoWidth": false,
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ data",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "&raquo;",
                    "previous": "&laquo;"
                },
                "emptyTable": "Tidak ada data yang tersedia",
                "zeroRecords": "Tidak ada data yang cocok"
            }
        });
        console.log('DataTable initialized successfully');
    } catch(e) {
        console.error('DataTable initialization error:', e);
    }

    // Event handlers for modal close
    $(document).on('click', '.modal .close, .modal [data-dismiss="modal"]', function() {
        $(this).closest('.modal').modal('hide');
    });

    // Force close modal dengan ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.modal').modal('hide');
        }
    });

    // Reset form ketika modal ditutup
    $('.modal').on('hidden.bs.modal', function() {
        $(this).find('form')[0]?.reset();
        $(this).find('.alert').hide();
        $(this).find('input[type="radio"]').prop('checked', false);
    });
    
    // Confirm delete button handler
    $(document).on('click', '#confirmDeleteBtn', function(e) {
        e.preventDefault();
        console.log('confirmDeleteBtn clicked!');
        
        var no_pengajuan = $('#delete_no_pengajuan').val();
        console.log('no_pengajuan:', no_pengajuan);
        
        if (no_pengajuan) {
            $('#deleteModal').modal('hide');
            setTimeout(function() {
                console.log('Redirecting to delete.php?id=' + no_pengajuan);
                window.location.href = 'delete.php?id=' + encodeURIComponent(no_pengajuan);
            }, 300);
        } else {
            console.error('no_pengajuan is empty!');
        }
    });
    
    console.log('All event handlers attached');
});

// Global function untuk handle delete (dipanggil dari onclick)
function handleDelete() {
    console.log('handleDelete called from onclick');
    var no_pengajuan = $('#delete_no_pengajuan').val();
    console.log('no_pengajuan from onclick:', no_pengajuan);
    
    if (no_pengajuan) {
        $('#deleteModal').modal('hide');
        setTimeout(function() {
            console.log('Redirecting to delete.php?id=' + no_pengajuan);
            window.location.href = 'delete.php?id=' + encodeURIComponent(no_pengajuan);
        }, 300);
    } else {
        alert('Error: No pengajuan ID found!');
        console.error('no_pengajuan is empty!');
    }
}

        function showDetail(no_pengajuan) {
            $.ajax({
                url: 'detail.php',
                type: 'GET',
                data: { no_pengajuan: no_pengajuan },
                success: function(response) {
                    $('#detailContent').html(response);
                    $('#detailModal').modal({
                        backdrop: 'static',
                        keyboard: true
                    }).modal('show');
                },
                error: function() {
                    alert('Gagal memuat detail pengajuan');
                }
            });
        }

        function processAction(no_pengajuan, sub_kategori) {
            $('#action_no_pengajuan').val(no_pengajuan);
            $('#display_no_pengajuan').text(no_pengajuan);
            $('#action_sub_kategori').val(sub_kategori);
            $('#display_sub_kategori').text(sub_kategori);
            
            // Set badge sub kategori
            if (sub_kategori === 'IT') {
                $('#kategori_badge').text('IT').removeClass('badge-ga').addClass('badge-it');
            } else if (sub_kategori === 'GA') {
                $('#kategori_badge').text('GA').removeClass('badge-it').addClass('badge-ga');
            } else {
                $('#kategori_badge').text(sub_kategori).removeClass('badge-it badge-ga').addClass('badge-secondary');
            }
            
            // Reset form
            $('input[name="decision"]').prop('checked', false);
            $('#catatan_approval').val('').prop('required', false);
            $('#submit_action').prop('disabled', true);
            
            // Hide alerts
            $('#approve_alert, #reject_alert').hide();
            $('#catatan_required').hide();
            $('#catatan_help').text('Berikan alasan atau catatan untuk keputusan Anda');
            
            $('#actionModal').modal({
                backdrop: 'static',
                keyboard: true
            }).modal('show');
        }

        function processDistribution(no_pengajuan, current_status) {
            $('#dist_no_pengajuan').val(no_pengajuan);
            $('#dist_display_no_pengajuan').text(no_pengajuan);
            
            if (current_status) {
                $('#status_pendistribusian').val(current_status);
            }
            
            $('#distributionModal').modal({
                backdrop: 'static',
                keyboard: true
            }).modal('show');
        }

        function updateAction(action) {
            if (action === 'approve') {
                $('#catatan_label').text('Catatan Persetujuan');
                $('#catatan_required').hide();
                $('#catatan_approval').prop('required', false);
                $('#catatan_help').text('Catatan untuk persetujuan ini (opsional)');
                
                $('#approve_alert').show();
                $('#reject_alert').hide();
                
                $('#submit_action').removeClass('btn-primary btn-danger').addClass('btn-success');
                $('#submit_action').html('<i class="fas fa-check fa-xs"></i> Setujui');
                
            } else if (action === 'reject') {
                $('#catatan_label').text('Alasan Penolakan');
                $('#catatan_required').show();
                $('#catatan_approval').prop('required', true);
                $('#catatan_help').text('Wajib memberikan alasan penolakan');
                
                $('#reject_alert').show();
                $('#approve_alert').hide();
                
            $('#submit_action').removeClass('btn-primary btn-success').addClass('btn-danger');
            $('#submit_action').html('<i class="fas fa-times fa-xs"></i> Tolak');
        }
        
        $('#submit_action').prop('disabled', false);
        }
            
        function deleteRequest(no_pengajuan, status) {
            console.log('deleteRequest called:', no_pengajuan, status);
            
            // Buat pesan konfirmasi
            var message = '<strong>Apakah Anda yakin ingin menghapus pengajuan ini?</strong><br><br>';
            message += '<strong>No. Pengajuan:</strong> ' + no_pengajuan + '<br>';
            message += '<strong>Status:</strong> ' + status + '<br><br>';
            message += '</div>';
            
            // Pakai showConfirm dari confirm_modal.php
            showConfirm('Konfirmasi Hapus', message, function() {
                console.log('User confirmed delete, redirecting...');
                window.location.href = 'delete.php?id=' + encodeURIComponent(no_pengajuan);
            }, null, 'delete');
        }
    </script>
</body>

</html>
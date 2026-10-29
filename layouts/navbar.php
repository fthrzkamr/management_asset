<?php
include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Buat instance baru
$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Tentukan base path berdasarkan current directory
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base_path = ($current_dir === 'asset_dfm') ? './' : '../';

// BASE_URL is now defined in dist/config/config.php

// Get user role untuk badge
$user_role = $auth->getUserRole();

// Get user divisi dan cabang dari database untuk menu visibility
$user_divisi = null;
$user_cabang = null;
if (isset($_SESSION['id_user'])) {
    $query_user = "SELECT u.divisi, mc.nama_cabang 
                   FROM user_dfm u 
                   LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang 
                   WHERE u.id_user = '" . mysqli_real_escape_string($conn, $_SESSION['id_user']) . "'";
    $result_user = mysqli_query($conn, $query_user);
    if ($result_user && $row_user = mysqli_fetch_assoc($result_user)) {
        $user_divisi = (int)$row_user['divisi'];
        $user_cabang = strtolower(trim($row_user['nama_cabang'] ?? ''));
        // Update session jika belum ada
        if (!isset($_SESSION['divisi'])) {
            $_SESSION['divisi'] = $user_divisi;
        }
    }
}
$user_divisi = $user_divisi ?? ($_SESSION['divisi'] ?? 0);
$user_cabang = $user_cabang ?? '';

// ═══════════════════════════════════════════════
// SIDEBAR BADGE - Hitung pending items per modul
// ═══════════════════════════════════════════════
$badge_counts = [];

if (isset($_SESSION['id_user'])) {
    $uid = (int)$_SESSION['id_user'];

    // Helper: jalankan query dan ambil angka
    function getCount($conn, $sql) {
        $r = mysqli_query($conn, $sql);
        if (!$r) return 0;
        $row = mysqli_fetch_row($r);
        return (int)($row[0] ?? 0);
    }

    // Pengajuan Aset - hanya yang masih Pending (belum diproses)
    if ($auth->hasPermission('pengajuan_asset', 'approve')) {
        // Global approver: tampilkan semua pending
        $badge_counts['pengajuan_asset'] = getCount($conn,
            "SELECT COUNT(*) FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             WHERE ar.status = 'Pending' AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)");
    } elseif ($auth->hasPermission('approval_asset_ga', 'approve') && $auth->hasPermission('approval_asset_it', 'approve')) {
        // Punya keduanya: tampilkan semua pending
        $badge_counts['pengajuan_asset'] = getCount($conn,
            "SELECT COUNT(*) FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             WHERE ar.status = 'Pending' AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)");
    } elseif ($auth->hasPermission('approval_asset_ga', 'approve')) {
        // GA approver: hanya GA
        $badge_counts['pengajuan_asset'] = getCount($conn,
            "SELECT COUNT(*) FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             WHERE ar.status = 'Pending' AND ka.sub_kategori = 'GA' AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)");
    } elseif ($auth->hasPermission('approval_asset_it', 'approve')) {
        // IT approver: hanya IT
        $badge_counts['pengajuan_asset'] = getCount($conn,
            "SELECT COUNT(*) FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             WHERE ar.status = 'Pending' AND ka.sub_kategori = 'IT' AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)");
    }

    // Pengajuan Renovasi - hanya yang masih Pending
    if ($auth->hasPermission('pengajuan_renovasi', 'approve')) {
        $badge_counts['pengajuan_renovasi'] = getCount($conn,
            "SELECT COUNT(*) FROM renovation_request WHERE status = 'Pending'");
    }

    // Service Aset - hanya yang masih aktif butuh tindakan (exclude Selesai & Dibatalkan)
    if ($auth->hasPermission('pengajuan_service', 'approve')) {
        $badge_counts['pengajuan_service'] = getCount($conn,
            "SELECT COUNT(*) FROM service_asset WHERE status_service NOT IN ('Selesai', 'Dibatalkan', 'Ditolak', 'Cancelled')");
    }

    // Approval Realisasi Asmen - hanya yang masih menunggu
    if ($auth->hasPermission('approval_realisasi_asmen', 'approve')) {
        $badge_counts['approval_realisasi_asmen'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_realisasi_monthly WHERE status = 'Menunggu Approval'");
    }

    // PPX - hanya yang menunggu approval di tahap role masing-masing
    if ($auth->hasPermission('approval_ppx_kacab', 'approve')) {
        $badge_counts['approval_ppx_kacab'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Kacab'");
    }
    if ($auth->hasPermission('approval_ppx_hr', 'approve')) {
        $badge_counts['approval_ppx_hr'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'HR'");
    }
    if ($auth->hasPermission('approval_ppx_asmen', 'approve')) {
        $badge_counts['approval_ppx_asmen'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Asmen Finance'");
    }
    if ($auth->hasPermission('approval_ppx_finance', 'approve')) {
        $badge_counts['approval_ppx_finance'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Finance'");
    }
    if ($auth->hasPermission('approval_ppx_operasional', 'approve')) {
        $badge_counts['approval_ppx_operasional'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Operasional'");
    }
    if ($auth->hasPermission('approval_ppx_direktur', 'approve')) {
        $badge_counts['approval_ppx_direktur'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Direktur'");
    }
    if ($auth->hasPermission('approval_ppx_manager', 'approve')) {
        $badge_counts['approval_ppx_manager'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx WHERE status = 'Manager'");
    }

    // Budget approval - hanya yang ada di tahap approval masing-masing (belum final)
    // Budget approval - Sync dengan status di dashboard masing-masing
    if ($auth->hasPermission('approval_budget_kacab', 'approve')) {
        $badge_counts['approval_budget_kacab'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_monthly_budget WHERE status = 'Kepala Cabang'");
    }
    if ($auth->hasPermission('approval_budget_asmen', 'approve')) {
        $badge_counts['approval_budget_asmen'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_monthly_budget WHERE status = 'Asmen Finance'");
    }
    if ($auth->hasPermission('approval_budget_manager', 'approve')) {
        $badge_counts['approval_budget_manager'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_monthly_budget WHERE status = 'Manager Finance'");
    }
    if ($auth->hasPermission('approval_budget_finance', 'approve')) {
        $badge_counts['approval_budget_finance'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_monthly_budget WHERE status = 'Finance'");
    }

    // Inventory ATK Pending
    if ($auth->hasPermission('inventory_atk_pengambilan', 'read')) {
        $badge_counts['inventory_atk_pengambilan'] = getCount($conn,
            "SELECT COUNT(*) FROM atk_pengambilan WHERE status = 'Pending'");
    }
    if ($auth->hasPermission('inventory_atk_po', 'read')) {
        $badge_counts['inventory_atk_po'] = getCount($conn,
            "SELECT COUNT(*) FROM atk_po_pengajuan WHERE status = 'Pending'");
    }

    // Approval PPX Leader - PPX yang masih di status 'Leader'
    if ($auth->hasPermission('approval_ppx_leader', 'approve')) {
        $badge_counts['approval_ppx_leader'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_request WHERE status = 'Leader'");
    }

    // Approval PPX GA - PPX yang menunggu di tahap 'Finance' (GA approves Finance step)
    if ($auth->hasPermission('approval_ppx_ga', 'approve')) {
        $badge_counts['approval_ppx_ga'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_request WHERE status = 'Finance'");
    }

    // Approval Anggaran Manajer Ops - budget yang menunggu Manager Operasional
    if ($auth->hasPermission('approval_budget_manajer_ops', 'approve')) {
        $badge_counts['approval_budget_manajer_ops'] = getCount($conn,
            "SELECT COUNT(*) FROM ppx_monthly_budget WHERE status_approval = 'Manager Operasional'");
    }

    // Inventory ATK Pengambilan - pengambilan yang masih Pending
    if ($auth->hasPermission('inventory_atk_pengambilan', 'approve')) {
        $badge_counts['inventory_atk_pengambilan'] = getCount($conn,
            "SELECT COUNT(*) FROM atk_pengambilan WHERE status = 'Pending'");
    }
}

// Helper render badge - hanya muncul jika ada item pending
function navBadge($key, $counts) {
    $n = $counts[$key] ?? 0;
    if ($n <= 0) return ''; // tidak tampil jika tidak ada pending
    $display = $n > 99 ? '99+' : $n;
    return ' <span class="nav-badge">' . $display . '</span>';
}

// Role badge definitions
$role_badge = [
    'super_admin' => ['class' => 'text-danger', 'text' => 'Super Admin'],
    'hr' => ['class' => 'text-warning', 'text' => 'HR'],
    'koordinator' => ['class' => 'text-info', 'text' => 'Koordinator'],
    'GA' => ['class' => 'text-success', 'text' => 'General Affairs'],
    'kacab' => ['class' => 'text-primary', 'text' => 'Kepala Cabang'],
    'finance' => ['class' => 'text-warning', 'text' => 'Finance'],
    'finance_ap' => ['class' => 'text-success', 'text' => 'Finance AP'],
    'manag_finance' => ['class' => 'text-danger', 'text' => 'Manager Finance'],
    'manag_operasional' => ['class' => 'text-primary', 'text' => 'Manager Operasional'],
    'it' => ['class' => 'text-info', 'text' => 'IT'],
    'direktur' => ['class' => 'text-dark', 'text' => 'Direktur']
];

// Helper function to check for active menu
function is_active($patterns) {
    if (empty($patterns)) return false;
    $current_page = $_SERVER['PHP_SELF'];
    if (is_array($patterns)) {
        foreach ($patterns as $pattern) {
            if (strpos($current_page, $pattern) !== false) return true;
        }
    } else {
        if (strpos($current_page, $patterns) !== false) return true;
    }
    return false;
}
?>

<!-- Header dan Sidebar -->

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DF GROUP</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/select2/css/select2.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>" />

    <!-- JS moved to footer to ensure jQuery loads first -->
    <style>
        /* ═══════════════════════════════════════════
           SIDEBAR BADGE NOTIFIKASI - PREMIUM DESIGN
           ═══════════════════════════════════════════ */
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 5px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background: linear-gradient(135deg, #ff416c 0%, #e8132b 100%);
            border-radius: 4px;
            margin-left: auto; /* Memastikan badge selalu rapat ke kanan setelah text jika sisa space banyak */
            margin-right: 5px; /* Memberi jarak sedikit dari tepi */
            vertical-align: middle;
            flex-shrink: 0;
            position: relative;
            letter-spacing: normal;
            box-shadow: 0 2px 8px rgba(232, 19, 43, 0.45), 0 1px 3px rgba(0,0,0,0.2);
            animation: badgePulse 2.5s ease-in-out infinite;
            cursor: default;
        }
        /* Dot indikator kecil di dalam badge */
        .nav-badge-dot {
            display: none; /* disembunyikan untuk badge yang punya angka */
        }
        /* Animasi pulse lebih halus dan premium */
        @keyframes badgePulse {
            0%   { box-shadow: 0 2px 8px rgba(232,19,43,0.45), 0 0 0 0 rgba(255,65,108,0.5); }
            50%  { box-shadow: 0 2px 12px rgba(232,19,43,0.6), 0 0 0 5px rgba(255,65,108,0); }
            100% { box-shadow: 0 2px 8px rgba(232,19,43,0.45), 0 0 0 0 rgba(255,65,108,0); }
        }
        /* Hover effect pada menu item yang punya badge */
        #sidebar-menu ul li a:hover .nav-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee0979 100%);
            box-shadow: 0 4px 15px rgba(238, 9, 121, 0.5);
            transform: scale(1.1);
            transition: all 0.25s ease;
        }
        .nav-badge {
            transition: all 0.25s ease;
        }
        /* Badge untuk submenu (ukuran sedikit lebih kecil) */
        #sidebar-menu ul li ul li a .nav-badge {
            min-width: 20px;
            height: 20px;
            font-size: 10px;
            padding: 0 4px;
            margin-left: auto;
            margin-right: 0px;
            background: linear-gradient(135deg, #ff416c 0%, #c0392b 100%);
            box-shadow: 0 2px 6px rgba(192,57,43,0.4);
        }
        /* Pastikan teks menu tidak wrap karena badge */
        #sidebar-menu ul li a {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
        }
        #sidebar-menu ul li a span:not(.menu-arrow):not(.nav-badge) {
            flex: 1;
        }

        /* ═════════════════════════════════════════════════════════════════════
           PERBAIKAN SCROLL SIDEBAR & LAYOUT (MENCEGAH TERPOTONG DI LAYAR 100%)
           ═════════════════════════════════════════════════════════════════════ */
        .sidebar {
            bottom: 0 !important;
            height: calc(100vh - 48px) !important;
            overflow: hidden !important;
        }
        .sidebar-inner {
            height: 100% !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            -webkit-overflow-scrolling: touch;
        }
        /* Scrollbar ramping & halus untuk menu sidebar */
        .sidebar-inner::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar-inner::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-inner::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .sidebar-inner::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        /* Ruang padding bawah agar menu paling bawah (seperti PPX) tidak terpotong tepi layar */
        #sidebar-menu {
            padding-bottom: 70px !important;
        }

        /* Penyesuaian responsif desktop layar 100% */
        @media (min-width: 992px) {
            .page-wrapper > .content {
                padding: 1.25rem 1.25rem 0 !important;
            }
        }

        /* Scrollbar horizontal elegan untuk semua table-responsive */
        .table-responsive::-webkit-scrollbar {
            height: 7px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="logo">
            <img src="<?= BASE_URL ?>/assets/img/dua.png" alt="Logo"><span> DF GROUP</span>
        </div>
        <div class="logo logo-small">
            <img src="<?= BASE_URL ?>/assets/img/dua.png" alt="Logo" width="30" height="30">
        </div>
    </div>
    <a href="#" id="toggle_btn"><i class="fas fa-align-left"></i></a>
    <a class="mobile_btn" id="mobile_btn"><i class="fas fa-bars"></i></a>

    <ul class="nav user-menu">
        <li class="nav-item dropdown noti-dropdown">
            <a href="#" class="dropdown-toggle nav-link" data-toggle="dropdown">
                <i class="far fa-bell"></i> <span class="badge badge-pill">0</span>
            </a>
            <div class="dropdown-menu notifications">
                <div class="topnav-dropdown-header">
                    <span class="notification-title">Notifikasi</span>
                    <a href="javascript:void(0)" class="clear-noti"> Hapus Semua </a>
                </div>
                <div class="topnav-dropdown-footer">
                    <a href="#">Lihat Semua Notifikasi</a>
                </div>
            </div>
        </li>
        <li class="nav-item dropdown has-arrow">
            <a href="#" class="dropdown-toggle nav-link" data-toggle="dropdown">
                <span class="user-img">
                    <img class="rounded-circle" src="<?= BASE_URL ?>/assets/img/profiles/default-avatar.svg" width="31" height="31" alt="User Image">
                </span>
            </a>
            <div class="dropdown-menu">
                <div class="user-header">
                    <div class="avatar avatar-sm">
                        <img src="<?= BASE_URL ?>/assets/img/profiles/default-avatar.svg" alt="User Image" class="avatar-img rounded-circle">
                    </div>
                    <div class="user-text">
                        <h6><?= htmlspecialchars($_SESSION['nama_user']) ?></h6>
                        <p class="text-muted mb-0 <?= $role_badge[$user_role]['class'] ?? 'text-secondary' ?>">
                            <?= $role_badge[$user_role]['text'] ?? 'Unknown' ?>
                        </p>
                    </div>
                </div>
                <a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Keluar</a>
            </div>
        </li>
    </ul>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="menu-title"><span>Menu Utama</span></li>

                <!-- DASHBOARD - SEMUA ROLE BISA AKSES -->
                <li class="<?= is_active('index.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>


                <!-- ═══════════════════════════════════════════════ -->
                <!-- SIDEBAR MENU - DYNAMIC PERMISSION BASED -->
                <!-- ═══════════════════════════════════════════════ -->

                <!-- PENGAJUAN ASET -->
                <?php 
                $canReadAsset = $auth->hasPermission('pengajuan_asset', 'read') || 
                                $auth->hasPermission('approval_asset_it', 'read') || 
                                $auth->hasPermission('approval_asset_ga', 'read');
                if ($canReadAsset): 
                ?>
                    <li class="<?= is_active('z_pengajuan_asset') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_pengajuan_asset/dashboard.php">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Pengajuan Aset</span>
                            <?= navBadge('pengajuan_asset', $badge_counts) ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- PENGAJUAN RENOVASI -->
                <?php if ($auth->hasPermission('pengajuan_renovasi', 'read')): ?>
                    <li class="<?= is_active('z_pengajuan_renovasi') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_pengajuan_renovasi/dashboard.php">
                            <i class="fa-solid fa-house-circle-exclamation"></i>
                            <span>Pengajuan Renovasi Kantor</span>
                            <?= navBadge('pengajuan_renovasi', $badge_counts) ?>
                        </a>
                    </li>
                <?php endif; ?>



                <!-- SERVICE ASET -->
                <?php if ($auth->hasPermission('pengajuan_service', 'read')): ?>
                    <li class="<?= is_active('z_service_asset') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_service_asset/dashboard.php">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            <span>Servis Asset</span>
                            <?= navBadge('pengajuan_service', $badge_counts) ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- MANAJEMEN ASET -->
                <?php 
                $canAssetIT = $auth->hasPermission('management_asset', 'read');
                $canAssetGA = $auth->hasPermission('management_asset_ga', 'read');
                $isAssetActive = is_active(['z_tambah_asset', 'z_management_asset_ga']);
                
                // Fetch Cabang List for Submenu
                $nav_cabangs = [];
                $q_nav_cabg = mysqli_query($conn, "SELECT id_cabang, nama_cabang FROM master_cabang ORDER BY id_cabang ASC");
                if($q_nav_cabg) {
                    while($r_nav = mysqli_fetch_assoc($q_nav_cabg)){
                        $nav_cabangs[] = $r_nav['nama_cabang'];
                    }
                }
                
                if ($canAssetIT && $canAssetGA): ?>
                    <!-- Departemen IT -->
                    <li class="<?= is_active('z_tambah_asset') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_tambah_asset/dashboard.php">
                            <i class="fas fa-desktop"></i>
                            <span> Manajemen Aset IT</span>
                        </a>
                    </li>
                    <!-- Departemen GA -->
                    <li class="<?= is_active('z_management_asset_ga') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_management_asset_ga/dashboard.php">
                            <i class="fas fa-building"></i>
                            <span> Manajemen Aset GA</span>
                        </a>
                    </li>
                <?php elseif ($canAssetIT): ?>
                    <li class="<?= is_active('z_tambah_asset') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_tambah_asset/dashboard.php">
                            <i class="fas fa-table"></i>
                            <span> Manajemen Aset IT</span>
                        </a>
                    </li>
                <?php elseif ($canAssetGA): ?>
                    <li class="<?= is_active('z_management_asset_ga') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_management_asset_ga/dashboard.php">
                            <i class="fas fa-table"></i>
                            <span> Manajemen Aset GA</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- PENGAJUAN (NEW DROPDOWN) -->
                <?php
                $peng_patterns = ['z_pengajuan_ppx', 'monthly_budget_list.php', 'monthly_budget_create.php', 'monthly_budget_edit.php', 'monthly_budget_detail.php'];
                $isPengActive = is_active($peng_patterns);
                
                $hasPengMenu = $auth->hasPermission('ppx', 'create') || $auth->hasPermission('ppx_budget', 'update');
                if ($hasPengMenu): ?>
                    <li class="submenu <?= $isPengActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isPengActive ? 'subdrop' : '' ?>">
                            <i class="fas fa-file-invoice"></i> <span>Pengajuan</span> <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isPengActive ? 'block' : 'none' ?>;">
                            <?php if ($auth->hasPermission('ppx_budget', 'update')): ?>
                                <li class="<?= is_active(['monthly_budget_list.php', 'monthly_budget_create.php', 'monthly_budget_edit.php', 'monthly_budget_detail.php']) ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_ppx_budget/monthly_budget_list.php">Monthly Budget</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('ppx', 'create')): ?>
                                <li class="<?= is_active('z_pengajuan_ppx') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_pengajuan_ppx/dashboard.php">Non Budget</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- REALISASI ANGGARAN (TOP LEVEL) -->
                <?php if ($auth->hasPermission('realisasi_ppx', 'read')): ?>
                    <li class="<?= is_active('z_realisasi_ppx') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_realisasi_ppx/dashboard.php"><i class="fa-solid fa-check-double"></i> <span>Realisasi Anggaran</span></a>
                    </li>
                <?php endif; ?>

                <?php if ($auth->hasPermission('approval_realisasi_asmen', 'approve')): ?>
                    <li class="<?= is_active('z_approval_realisasi_asmen') ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>/z_approval_realisasi_asmen/dashboard.php">
                            <i class="fa-solid fa-stamp"></i>
                            <span>Approval Realisasi</span>
                            <?= navBadge('approval_realisasi_asmen', $badge_counts) ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- PPX MANAGEMENT (grouped dropdown, setiap item dikontrol permission dari Administrator) -->
                <?php
                // Note: z_pengajuan_ppx is no longer in this pattern as it was moved to Pengajuan
                $ppx_patterns = ['z_approval_ppx_kacab', 'z_approval_ppx_hr', 'z_approval_ppx_asmen', 'z_approval_ppx_finance', 'z_approval_ppx_operasional', 'z_approval_ppx_direktur', 'z_approval_ppx_manager'];
                $isPpxActive = is_active($ppx_patterns);

                // Hitung total notifikasi PPX untuk menu induk
                $totalPpxBadge = ($badge_counts['approval_ppx_leader'] ?? 0) + 
                                 ($badge_counts['approval_ppx_kacab'] ?? 0) + 
                                 ($badge_counts['approval_ppx_hr'] ?? 0) + 
                                 ($badge_counts['approval_ppx_asmen'] ?? 0) + 
                                 ($badge_counts['approval_ppx_finance'] ?? 0) + 
                                 ($badge_counts['approval_ppx_operasional'] ?? 0) + 
                                 ($badge_counts['approval_ppx_direktur'] ?? 0) + 
                                 ($badge_counts['approval_ppx_ga'] ?? 0) + 
                                 ($badge_counts['approval_ppx_manager'] ?? 0);
                
                $hasPpxMenu =
                    $auth->hasPermission('approval_ppx_leader', 'approve') ||
                    $auth->hasPermission('approval_ppx_kacab', 'approve') ||
                    $auth->hasPermission('approval_ppx_hr', 'approve') ||
                    $auth->hasPermission('approval_ppx_asmen', 'approve') ||
                    $auth->hasPermission('approval_ppx_finance', 'approve') ||
                    $auth->hasPermission('approval_ppx_operasional', 'approve') ||
                    $auth->hasPermission('approval_ppx_direktur', 'approve') ||
                    $auth->hasPermission('approval_ppx_ga', 'approve') ||
                    $auth->hasPermission('approval_ppx_manager', 'approve');
                if ($hasPpxMenu): ?>
                    <li class="submenu <?= $isPpxActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isPpxActive ? 'subdrop' : '' ?>">
                            <i class="fa-solid fa-money-bill-1-wave"></i> 
                            <span>PPX</span> 
                            <?php if ($totalPpxBadge > 0): ?>
                                <span class="badge badge-danger" style="margin-left: 10px; margin-right: 25px; width: 18px !important; height: 18px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 4px; padding: 0 !important; flex: none !important; font-size: 11px; vertical-align: middle;">!</span>
                            <?php endif; ?>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isPpxActive ? 'block' : 'none' ?>;">

                            <?php if ($auth->hasPermission('approval_ppx_leader', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_leader') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_leader/dashboard.php">Approval PPX Leader<?= navBadge('approval_ppx_leader', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_kacab', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_kacab') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_kacab/dashboard.php">Approval PPX Kacab<?= navBadge('approval_ppx_kacab', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_hr', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_hr') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_hr/dashboard.php">Approval PPX HR<?= navBadge('approval_ppx_hr', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_asmen', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_asmen') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_asmen/dashboard.php">Approval PPX Asmen Finance<?= navBadge('approval_ppx_asmen', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_finance', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_finance') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_finance/dashboard.php">Approval PPX Finance<?= navBadge('approval_ppx_finance', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_operasional', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_operasional') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_operasional/dashboard.php">Approval PPX Operasional<?= navBadge('approval_ppx_operasional', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_direktur', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_direktur') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_direktur/dashboard.php">Approval PPX Direktur<?= navBadge('approval_ppx_direktur', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_ga', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_ga') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_ga/dashboard.php">Approval PPX GA<?= navBadge('approval_ppx_ga', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_ppx_manager', 'approve')): ?>
                                <li class="<?= is_active('z_approval_ppx_manager') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_ppx_manager/dashboard.php">Approval PPX Manager<?= navBadge('approval_ppx_manager', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- ANGGARAN (grouped dropdown, setiap item dikontrol permission dari Administrator) -->
                 <?php
                $budget_patterns = ['z_ppx_budget/dashboard.php', 'z_ppx_budget/category_manage.php', 'z_ppx_budget/usage_report.php', 'z_budget_besar/', 'z_master_budget/', 'z_approval_budget_kacab/', 'z_approval_budget_asmen/', 'z_approval_budget_manager/', 'z_approval_budget_manajer_ops/', 'z_approval_budget_finance/'];
                $isBudgetActive = is_active($budget_patterns);
                
                // Hitung total notifikasi budget untuk menu induk
                $totalBudgetBadge = ($badge_counts['approval_budget_kacab'] ?? 0) + 
                                    ($badge_counts['approval_budget_asmen'] ?? 0) + 
                                    ($badge_counts['approval_budget_manager'] ?? 0) + 
                                    ($badge_counts['approval_budget_finance'] ?? 0);

                $hasAnggaranMenu =
                    $auth->hasPermission('ppx_budget', 'read') ||
                    $auth->hasPermission('ppx_budget', 'update') ||
                    $auth->hasPermission('ppx_budget', 'manage_category') ||
                    $auth->hasPermission('master_budget', 'read') ||
                    $auth->hasPermission('approval_budget_kacab', 'approve') ||
                    $auth->hasPermission('approval_budget_asmen', 'approve') ||
                    $auth->hasPermission('approval_budget_manager', 'approve') ||
                    $auth->hasPermission('approval_budget_manajer_ops', 'read') ||
                    $auth->hasPermission('approval_budget_finance', 'approve') ||
                    $auth->hasPermission('budget_besar', 'read');
                if ($hasAnggaranMenu): ?>
                    <li class="submenu <?= $isBudgetActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isBudgetActive ? 'subdrop' : '' ?>">
                            <i class="fas fa-wallet"></i> 
                            <span>Anggaran</span> 
                            <?php if ($totalBudgetBadge > 0): ?>
                                <span class="badge badge-danger" style="margin-left: 10px; margin-right: 25px; width: 18px !important; height: 18px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 4px; padding: 0 !important; flex: none !important; font-size: 11px; vertical-align: middle;">!</span>
                            <?php endif; ?>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isBudgetActive ? 'block' : 'none' ?>;">
                            <?php if ($auth->hasPermission('ppx_budget', 'read')): ?>
                                <li class="<?= is_active('z_ppx_budget/dashboard.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_ppx_budget/dashboard.php">Dashboard Anggaran</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('ppx_budget', 'manage_category')): ?>
                                <li class="<?= is_active('z_ppx_budget/category_manage.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_ppx_budget/category_manage.php">Kategori Budget</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('ppx_budget', 'read')): ?>
                                <li class="<?= is_active('z_ppx_budget/usage_report.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_ppx_budget/usage_report.php">Laporan Pemakaian</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('budget_besar', 'read')): ?>
                                <li class="<?= is_active('z_budget_besar/') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_budget_besar/dashboard.php"></i>Anggaran Besar BCA</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('master_budget', 'read')): ?>
                                <li class="<?= is_active('z_master_budget') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_budget/dashboard.php">Master Anggaran</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_budget_kacab', 'approve')): ?>
                                <li class="<?= is_active('z_approval_budget_kacab') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_budget_kacab/dashboard.php">Approval Budget (Kacab)<?= navBadge('approval_budget_kacab', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_budget_asmen', 'approve')): ?>
                                <li class="<?= is_active('z_approval_budget_asmen') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_budget_asmen/dashboard.php">Approval Budget (Asmen Finance)<?= navBadge('approval_budget_asmen', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_budget_manager', 'approve')): ?>
                                <li class="<?= is_active('z_approval_budget_manager/') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_budget_manager/dashboard.php">Approval Budget (Manager)<?= navBadge('approval_budget_manager', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_budget_manajer_ops', 'approve')): ?>
                                <li class="<?= is_active('z_approval_budget_manajer_ops/') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_budget_manajer_ops/dashboard.php">Approval Budget (Manajer Ops)<?= navBadge('approval_budget_manajer_ops', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('approval_budget_finance', 'approve')): ?>
                                <li class="<?= is_active('z_approval_budget_finance') ? 'active' : '' ?>">
                                    <a href="<?= BASE_URL ?>/z_approval_budget_finance/dashboard.php">Approval Realisasi (Finance)<?= navBadge('approval_budget_finance', $badge_counts) ?></a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- INVENTORY ATK -->
                <?php 
                    $hasInventoryATK = $auth->hasPermission('inventory_atk_master', 'read') || 
                                       $auth->hasPermission('inventory_atk_stok', 'read') || 
                                       $auth->hasPermission('inventory_atk_po', 'read') || 
                                       $auth->hasPermission('inventory_atk_penerimaan', 'read') || 
                                       $auth->hasPermission('inventory_atk_pengambilan', 'read');
                    if ($hasInventoryATK): 
                ?>
                    <?php 
                        $isInventoryActive = is_active(['z_inventory_atk', 'z_pt_bst_penerimaan']) && !(isset($_GET['ref']) && $_GET['ref'] === 'bst'); 
                        // Hitung total notifikasi ATK (gabungan semua yang butuh tindakan)
                        $totalATKBadge = ($badge_counts['inventory_atk_pengambilan'] ?? 0) + 
                                         ($badge_counts['inventory_atk_po'] ?? 0) + 
                                         ($badge_counts['inventory_atk_penerimaan'] ?? 0);
                    ?>
                    <li class="submenu <?= $isInventoryActive ? 'active' : '' ?>">
                        <a href="#" class="<?= $isInventoryActive ? 'subdrop' : '' ?>">
                            <i class="fas fa-boxes"></i> 
                            <span>Inventory ATK</span> 
                            <?php if ($totalATKBadge > 0): ?>
                                <span class="badge badge-danger" style="margin-left: 10px; margin-right: 25px; width: 18px !important; height: 18px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 4px; padding: 0 !important; flex: none !important; font-size: 11px; vertical-align: middle;">!</span>
                            <?php endif; ?>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isInventoryActive ? 'block' : 'none' ?>;">
                            <?php if ($auth->hasPermission('inventory_atk_master', 'read')): ?>
                                <li class="<?= (is_active('z_inventory_atk/inventory_list') && !(isset($_GET['ref']) && $_GET['ref'] === 'bst')) ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/inventory_list.php">Master Inventory</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('inventory_atk_stok', 'read')): ?>
                                <li class="<?= is_active('z_inventory_atk/stok_gudang') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/stok_gudang.php">Stok Gudang ATK</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('inventory_atk_po', 'read')): ?>
                                <li class="<?= is_active('z_inventory_atk/po_pengajuan') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/po_pengajuan/dashboard.php">Pengajuan PO</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('inventory_atk_penerimaan', 'read')): ?>
                                <li class="<?= is_active('z_inventory_atk/penerimaan') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/penerimaan/dashboard.php">Penerimaan Inventory</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('inventory_atk_pengambilan', 'read')): ?>
                                <li class="<?= is_active('z_inventory_atk/dashboard') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/dashboard.php">Pengambilan ATK<?= navBadge('inventory_atk_pengambilan', $badge_counts) ?></a></li>
                            <?php endif; ?>
                            <li><a href="<?= BASE_URL ?>/z_inventory_atk/form_public.php" target="_blank">Form Pengambilan</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- MODUL PT BST -->
                <?php 
                    $hasBSTMenu = $auth->hasPermission('inventory_atk_master', 'read') || 
                                  $auth->hasPermission('pt_bst_inventory', 'read') || 
                                  $auth->hasPermission('pt_bst_po_pembelian', 'read') || 
                                  $auth->hasPermission('pt_bst_po_masuk', 'read') ||
                                  $auth->hasPermission('pt_bst_asset', 'read');
                    if ($hasBSTMenu): 
                ?>
                    <?php 
                        // Tambahkan deteksi ?ref=bst agar dropdown BST menyala mandiri tanpa membangunkan dropdown ATK
                        $isBSTActive = is_active(['z_pt_bst/inventory', 'z_pt_bst/po_masuk', 'z_pt_bst/po_pembelian', 'z_pt_bst/asset']) || (isset($_GET['ref']) && $_GET['ref'] === 'bst'); 
                    ?>
                    <li class="submenu <?= $isBSTActive ? 'active' : '' ?>">
                        <a href="#" class="<?= $isBSTActive ? 'subdrop' : '' ?>"><i class="fas fa-truck-loading"></i> <span>PT BST</span> <span class="menu-arrow"></span></a>
                        <ul style="display: <?= $isBSTActive ? 'block' : 'none' ?>;">
                            <?php if ($auth->hasPermission('inventory_atk_master', 'read')): ?>
                                <li class="<?= (is_active('z_inventory_atk/inventory_list') && isset($_GET['ref']) && $_GET['ref'] == 'bst') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_inventory_atk/inventory_list.php?ref=bst">Master Inventory</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('pt_bst_inventory', 'read')): ?>
                                <li class="<?= is_active('z_pt_bst/inventory') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_pt_bst/inventory/dashboard.php">Inventory BST</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('pt_bst_po_pembelian', 'read')): ?>
                                <li class="<?= is_active('z_pt_bst/po_pembelian') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_pt_bst/po_pembelian/dashboard.php">PO Pembelian (Restock)</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('pt_bst_po_masuk', 'read')): ?>
                                <li class="<?= is_active('z_pt_bst/po_masuk') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_pt_bst/po_masuk/dashboard.php">Kelola PO Masuk</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('pt_bst_asset', 'read')): ?>
                                <li class="<?= is_active('z_pt_bst/asset') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_pt_bst/asset/dashboard.php">Manajemen Aset (BST)</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- ANALYTICS -->
                <?php if ($auth->hasPermission('analytics', 'read')): ?>
                    <li class="<?= is_active('z_analisis') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_analisis/index.php"><i class="fas fa-chart-pie"></i> <span>Dashboard Aset</span></a></li>
                <?php endif; ?>

                <?php 
                $hasReportMonthly = $auth->hasPermission('report_ppx', 'read');
                $hasReportAll     = $auth->hasPermission('report_ppx_all', 'read');
                if ($hasReportMonthly || $hasReportAll): ?>
                    <?php $isReportActive = is_active(['z_report_ppx']) || (strpos($_SERVER['PHP_SELF'], 'z_report_ppx') !== false); ?>
                    <li class="submenu <?= $isReportActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isReportActive ? 'subdrop' : '' ?>">
                            <i class="fas fa-file-alt"></i>
                            <span> Laporan</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isReportActive ? 'block' : 'none' ?>;">
                            <?php if ($hasReportMonthly): ?>
                                <li class="<?= is_active('z_report_ppx/dashboard.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_report_ppx/dashboard.php">Laporan Anggaran Bulanan</a></li>
                            <?php endif; ?>
                            <?php if ($hasReportAll): ?>
                                <li class="<?= is_active('z_report_ppx/report_all.php') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_report_ppx/report_all.php">Laporan PPX Regular & Monthly</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>



                <!-- ROUTING PPX -->
                <?php if ($auth->hasPermission('ppx_routing', 'read')): ?>
                    <li class="<?= is_active('z_master_ppx_routing') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_ppx_routing/dashboard.php"><i class="fas fa-route"></i> <span>Routing Approval PPX</span></a></li>
                <?php endif; ?>

                <!-- ROUTING ANGGARAN -->
                <?php if ($auth->hasPermission('budget_routing', 'read')): ?>
                    <li class="<?= is_active('z_master_budget_routing') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_budget_routing/dashboard.php"><i class="fas fa-route"></i> <span>Routing Approval Anggaran</span></a></li>
                <?php endif; ?>

                <!-- DATA MASTER -->
                <?php 
                $hasMasterMenu = $auth->hasPermission('master_data', 'read') || $auth->hasPermission('master_akun', 'read');
                if ($hasMasterMenu): ?>
                    <?php $isMasterActive = is_active(['z_master_data', 'z_master_akun']); ?>
                    <li class="submenu <?= $isMasterActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isMasterActive ? 'subdrop' : '' ?>">
                            <i class="fas fa-database"></i>
                            <span> Data Master</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isMasterActive ? 'block' : 'none' ?>;">
                            <?php if ($auth->hasPermission('master_data', 'read')): ?>
                                <li class="<?= is_active('z_master_data') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_data/dashboard.php">Dashboard</a></li>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('master_akun', 'read')): ?>
                                <li class="<?= is_active('z_master_akun') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_akun/dashboard.php">Master Akun</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- MANAJEMEN PENGGUNA -->
                <?php if ($auth->hasPermission('user_management', 'read')): ?>
                    <li class="<?= is_active('z_users') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_users/dashboard.php"><i class="fas fa-users"></i> <span>Manajemen Pengguna</span></a></li>
                <?php endif; ?>



                <!-- ADMINISTRATOR -->
                <?php if ($auth->isSuperAdmin()): ?>
                    <li class="<?= is_active('z_admin_permissions') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_admin_permissions/dashboard.php"><i class="fas fa-user-shield"></i> <span>Administrator</span></a></li>
                <?php endif; ?>


            </ul>
        </div>
    </div>
</div>

<!-- ALERT GLOBAL HANDLER -->
<?php if (isset($_SESSION['error'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: '<?= $_SESSION['error_title'] ?? 'Peringatan' ?>',
            text: '<?= $_SESSION['error'] ?>',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Tutup'
        });
    });
</script>
<?php 
    unset($_SESSION['error']); 
    unset($_SESSION['error_title']);
endif; 
?>

<?php if (isset($_SESSION['success'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '<?= $_SESSION['success'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
    });
</script>
<?php unset($_SESSION['success']); endif; ?>

<!-- Submenu handled by assets/js/script.js -->
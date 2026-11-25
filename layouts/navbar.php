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
    // Scope mengikuti permission approval (IT / GA / umum), sama dengan halaman Pengajuan Aset
    $assetScopeNav = $auth->getAssetApprovalScope();
    if ($assetScopeNav['any']) {
        $subFilterNav = '';
        if (!$assetScopeNav['all']) {
            $subs = [];
            if ($assetScopeNav['IT']) $subs[] = "'IT'";
            if ($assetScopeNav['GA']) $subs[] = "'GA'";
            $subFilterNav = " AND ka.sub_kategori IN (" . implode(',', $subs) . ")";
        }
        $badge_counts['pengajuan_asset'] = getCount($conn,
            "SELECT COUNT(*) FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             WHERE ar.status = 'Pending'$subFilterNav AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)");
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
            "SELECT COUNT(*) FROM ppx_realisasi_detail2 WHERE status = 'Menunggu Approval'");
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
        /* ═════════════════════════════════════════════
           LEBAR SIDEBAR & LAYOUT RESPONSIF TERBARU
           ═════════════════════════════════════════════ */
        :root {
            --sidebar-width: 290px;
        }

        @media (min-width: 992px) {
            .header .header-left {
                width: var(--sidebar-width) !important;
            }
            .sidebar {
                width: var(--sidebar-width) !important;
            }
            .page-wrapper {
                margin-left: var(--sidebar-width) !important;
            }

            /* Aturan saat sidebar di-minimize (mini-sidebar) */
            .mini-sidebar .header .header-left {
                width: 78px !important;
            }
            .mini-sidebar .sidebar {
                width: 78px !important;
            }
            .mini-sidebar .page-wrapper {
                margin-left: 78px !important;
            }

            /* Hilangkan gap menu-title saat mini-sidebar tidak di-hover agar tidak ada celah kosong */
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu .menu-title {
                display: none !important;
            }

            /* Sembunyikan semua teks, panah submenu, dan badge saat mini-sidebar agar tidak mendorong ikon */
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu ul > li > a span,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu ul > li > a .menu-arrow,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu ul > li > a .nav-parent-badge,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu ul > li > a .nav-badge,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu ul > li > a .badge {
                display: none !important;
            }

            /* Format wadah link dan item menu agar lurus vertikal sempurna di tengah 78px */
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu {
                padding: 10px 0 !important;
            }
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li {
                width: 78px !important;
                margin: 4px 0 !important;
                display: flex !important;
                justify-content: center !important;
            }
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li > a {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 48px !important;
                height: 48px !important;
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 10px !important;
                text-align: center !important;
                transition: all 0.2s ease !important;
            }
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li > a i {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 24px !important;
                height: 24px !important;
                font-size: 18px !important;
                line-height: 1 !important;
                margin: 0 !important;
                text-align: center !important;
            }

            /* Item aktif pada mini-sidebar: soft blue rounded pill yang elegan dan simetris */
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li.active > a,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li > a.active {
                background-color: rgba(24, 174, 250, 0.12) !important;
                color: #18aefa !important;
            }
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li.active > a::before,
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li > a.active::before {
                display: none !important;
            }

            /* Hover effect pada item mini-sidebar */
            .mini-sidebar:not(.expand-menu) .sidebar .sidebar-menu > ul > li > a:hover {
                background-color: rgba(24, 174, 250, 0.08) !important;
                color: #18aefa !important;
            }

            /* Saat kursor diarahkan ke mini-sidebar (expand on hover) */
            .mini-sidebar.expand-menu .sidebar {
                width: var(--sidebar-width) !important;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
            }
            .mini-sidebar.expand-menu .sidebar .sidebar-menu .menu-title {
                display: flex !important;
                visibility: visible !important;
            }
            .mini-sidebar.expand-menu .sidebar .sidebar-menu ul > li > a span {
                display: inline-block !important;
                opacity: 1 !important;
            }
            .mini-sidebar.expand-menu .sidebar .sidebar-menu ul > li > a .menu-arrow {
                display: inline-block !important;
            }
            .mini-sidebar.expand-menu .sidebar .sidebar-menu ul > li > a .nav-parent-badge {
                display: inline-flex !important;
            }
        }

        @media (max-width: 991.98px) {
            .header .header-left {
                width: 100% !important;
            }
            .sidebar {
                width: 270px !important;
                margin-left: -270px !important;
            }
            .page-wrapper {
                margin-left: 0 !important;
            }
        }

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
            margin-left: auto; /* Memastikan badge selalu rapat ke kanan setelah text */
            margin-right: 5px;
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
            display: none;
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
        /* Badge untuk submenu */
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

        /* Top-level menu item styling */
        #sidebar-menu > ul > li > a {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            margin: 0 -15px !important;
            padding: 10px 42px 10px 22px !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            transition: all 0.2s ease-in-out !important;
        }
        #sidebar-menu > ul > li > a:hover,
        #sidebar-menu > ul > li.active > a {
            background-color: #fff !important;
            color: #18aefa !important;
            padding-left: 26px !important;
        }
        #sidebar-menu > ul > li > a i {
            width: 22px !important;
            font-size: 14px !important;
            margin-right: 10px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
        }
        #sidebar-menu > ul > li > a > span:first-of-type {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            flex: 1 !important;
        }

        /* Menu Arrow: selalu presisi di kanan dan tidak bertabrakan dengan text */
        #sidebar-menu .menu-arrow {
            position: absolute !important;
            right: 16px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            margin-top: 0 !important;
            font-size: 13px !important;
            color: #9e9e9e !important;
            transition: transform 0.2s ease-in-out !important;
            pointer-events: none !important;
            line-height: 1 !important;
        }
        #sidebar-menu li a.subdrop .menu-arrow {
            transform: translateY(-50%) rotate(90deg) !important;
        }

        /* Submenu Child Styling */
        #sidebar-menu ul ul li a {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 8px 18px 8px 45px !important;
            font-size: 13px !important;
        }
        #sidebar-menu ul ul li a .nav-badge {
            margin-left: auto !important;
            margin-right: 0 !important;
        }

        /* Parent Submenu Alert Indicator (!) */
        .nav-parent-badge {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            border-radius: 4px !important;
            padding: 0 !important;
            margin-left: 6px !important;
            margin-right: 0 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            color: #fff !important;
            background: linear-gradient(135deg, #ff416c 0%, #e8132b 100%) !important;
            box-shadow: 0 2px 6px rgba(232, 19, 43, 0.4) !important;
            flex-shrink: 0 !important;
            animation: badgePulse 2.5s ease-in-out infinite;
        }

        /* Section Title Header */
        .sidebar-menu li.menu-title {
            padding: 14px 18px 4px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            text-transform: uppercase !important;
            color: #94a3b8 !important;
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
        /* Ruang padding bawah agar menu paling bawah tidak terpotong tepi layar */
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

        /* ═════════════════════════════════════════════════════════════════════
           PERBAIKAN KONTRAS WARNA PRIMARY & MODAL HEADER (MENCEGAH TEKS PUTIH PADA PALE YELLOW)
           ═════════════════════════════════════════════════════════════════════ */
        .bg-primary {
            background-color: #18aefa !important;
            color: #ffffff !important;
        }
        .modal-header.bg-primary {
            background-color: #18aefa !important;
            color: #ffffff !important;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .modal-header.bg-primary .modal-title,
        .modal-header.bg-primary h5,
        .modal-header.bg-primary i,
        .modal-header.bg-primary .close {
            color: #ffffff !important;
            opacity: 1 !important;
            text-shadow: none !important;
        }
        .badge-primary, .pro-bg-primary {
            background-color: #18aefa !important;
            color: #ffffff !important;
        }
        .modal-header.bg-warning.text-white {
            background-color: #d97706 !important;
            color: #ffffff !important;
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
                <li class="<?= is_active('index.php') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
                </li>


                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- 1. KELOMPOK: ASET & GENERAL AFFAIRS (GA)                   -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <?php 
                $canReadAsset    = $auth->canReadAsset();
                $canRenovasi     = $auth->hasPermission('pengajuan_renovasi', 'read');
                $canService      = $auth->hasPermission('pengajuan_service', 'read');
                $canAssetIT      = $auth->hasPermission('management_asset', 'read');
                $canAssetGA      = $auth->hasPermission('management_asset_ga', 'read');
                $canMonitoringGA = $auth->hasPermission('monitoring_ga', 'read');
                $canAnalytics    = $auth->hasPermission('analytics', 'read');

                $hasAssetSection = $canReadAsset || $canRenovasi || $canService || $canAssetIT || $canAssetGA || $canMonitoringGA || $canAnalytics;
                if ($hasAssetSection): ?>
                    <li class="menu-title"><span>Aset & General Affairs</span></li>

                    <!-- Dashboard Aset (Analytics) -->
                    <?php if ($canAnalytics): ?>
                        <li class="<?= is_active('z_analisis') ? 'active' : '' ?>">
                            <a href="<?= BASE_URL ?>/z_analisis/index.php"><i class="fas fa-chart-pie"></i> <span>Dashboard Aset</span></a>
                        </li>
                    <?php endif; ?>

                    <!-- Pengajuan & Layanan Aset -->
                    <?php 
                    $hasLayananAset = $canReadAsset || $canRenovasi || $canService;
                    if ($hasLayananAset): 
                        $layanan_patterns = ['z_pengajuan_asset', 'z_pengajuan_renovasi', 'z_service_asset'];
                        $isLayananActive = is_active($layanan_patterns);
                        $totalLayananBadge = ($badge_counts['pengajuan_asset'] ?? 0) + 
                                             ($badge_counts['pengajuan_renovasi'] ?? 0) + 
                                             ($badge_counts['pengajuan_service'] ?? 0);
                    ?>
                        <li class="submenu <?= $isLayananActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isLayananActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Pengajuan Aset & GA</span>
                                <?php if ($totalLayananBadge > 0): ?>
                                    <span class="nav-parent-badge">!</span>
                                <?php endif; ?>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isLayananActive ? 'block' : 'none' ?>;">
                                <?php if ($canReadAsset): ?>
                                    <li class="<?= is_active('z_pengajuan_asset') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_pengajuan_asset/dashboard.php">
                                            Pengajuan Aset<?= navBadge('pengajuan_asset', $badge_counts) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canRenovasi): ?>
                                    <li class="<?= is_active('z_pengajuan_renovasi') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_pengajuan_renovasi/dashboard.php">
                                            Pengajuan Renovasi<?= navBadge('pengajuan_renovasi', $badge_counts) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canService): ?>
                                    <li class="<?= is_active('z_service_asset') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_service_asset/dashboard.php">
                                            Servis Aset<?= navBadge('pengajuan_service', $badge_counts) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Manajemen Data Aset -->
                    <?php if ($canAssetIT || $canAssetGA): 
                        $isManajemenAssetActive = is_active(['z_tambah_asset', 'z_management_asset_ga']);
                    ?>
                        <li class="submenu <?= $isManajemenAssetActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isManajemenAssetActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-boxes"></i>
                                <span>Manajemen Aset</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isManajemenAssetActive ? 'block' : 'none' ?>;">
                                <?php if ($canAssetIT): ?>
                                    <li class="<?= is_active('z_tambah_asset') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_tambah_asset/dashboard.php">Manajemen Aset IT</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canAssetGA): ?>
                                    <li class="<?= is_active('z_management_asset_ga') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_management_asset_ga/dashboard.php">Manajemen Aset GA</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Monitoring GA -->
                    <?php if ($canMonitoringGA): ?>
                        <li class="<?= is_active('z_monitoring_ga') ? 'active' : '' ?>">
                            <a href="<?= BASE_URL ?>/z_monitoring_ga/dashboard.php">
                                <i class="fas fa-tasks"></i>
                                <span>Monitoring GA</span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- 2. KELOMPOK: PPX & ANGGARAN (SEMUA TENTANG PPX)            -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <?php
                // Hitung notifikasi PPX Approval
                $totalPpxBadge = ($badge_counts['approval_ppx_leader'] ?? 0) + 
                                 ($badge_counts['approval_ppx_kacab'] ?? 0) + 
                                 ($badge_counts['approval_ppx_hr'] ?? 0) + 
                                 ($badge_counts['approval_ppx_asmen'] ?? 0) + 
                                 ($badge_counts['approval_ppx_finance'] ?? 0) + 
                                 ($badge_counts['approval_ppx_operasional'] ?? 0) + 
                                 ($badge_counts['approval_ppx_direktur'] ?? 0) + 
                                 ($badge_counts['approval_ppx_ga'] ?? 0) + 
                                 ($badge_counts['approval_ppx_manager'] ?? 0);

                // Hitung notifikasi Budget Approval
                $totalBudgetBadge = ($badge_counts['approval_budget_kacab'] ?? 0) + 
                                    ($badge_counts['approval_budget_asmen'] ?? 0) + 
                                    ($badge_counts['approval_budget_manager'] ?? 0) + 
                                    ($badge_counts['approval_budget_finance'] ?? 0);

                // Hitung notifikasi Realisasi Asmen
                $totalRealisasiBadge = ($badge_counts['approval_realisasi_asmen'] ?? 0);

                $hasPengMenu = $auth->hasPermission('ppx', 'create') || $auth->hasPermission('ppx_budget', 'create') || $auth->hasPermission('ppx_budget', 'update');
                $hasRealisasiMenu = $auth->hasPermission('realisasi_ppx', 'read') || $auth->hasPermission('approval_realisasi_asmen', 'approve');
                $hasPpxApprovalMenu =
                    $auth->hasPermission('approval_ppx_leader', 'approve') ||
                    $auth->hasPermission('approval_ppx_kacab', 'approve') ||
                    $auth->hasPermission('approval_ppx_hr', 'approve') ||
                    $auth->hasPermission('approval_ppx_asmen', 'approve') ||
                    $auth->hasPermission('approval_ppx_finance', 'approve') ||
                    $auth->hasPermission('approval_ppx_operasional', 'approve') ||
                    $auth->hasPermission('approval_ppx_direktur', 'approve') ||
                    $auth->hasPermission('approval_ppx_ga', 'approve') ||
                    $auth->hasPermission('approval_ppx_manager', 'approve');
                $hasBudgetApprovalMenu =
                    $auth->hasPermission('approval_budget_kacab', 'approve') ||
                    $auth->hasPermission('approval_budget_asmen', 'approve') ||
                    $auth->hasPermission('approval_budget_manager', 'approve') ||
                    $auth->hasPermission('approval_budget_manajer_ops', 'read') ||
                    $auth->hasPermission('approval_budget_manajer_ops', 'approve') ||
                    $auth->hasPermission('approval_budget_finance', 'approve');
                $hasMasterBudgetMenu =
                    $auth->hasPermission('ppx_budget', 'read') ||
                    $auth->hasPermission('ppx_budget', 'manage_category') ||
                    $auth->hasPermission('master_budget', 'read') ||
                    $auth->hasPermission('ppx_routing', 'read') ||
                    $auth->hasPermission('budget_routing', 'read') ||
                    $auth->isSuperAdmin();
                $hasReportMonthly = $auth->hasPermission('report_ppx', 'read');
                $hasReportAll     = $auth->hasPermission('report_ppx_all', 'read');
                $hasReportMenu    = $hasReportMonthly || $hasReportAll || $auth->hasPermission('ppx_budget', 'read');

                $hasPpxSection = $hasPengMenu || $hasRealisasiMenu || $hasPpxApprovalMenu || $hasBudgetApprovalMenu || $hasMasterBudgetMenu || $hasReportMenu;
                if ($hasPpxSection): ?>
                    <li class="menu-title"><span>PPX & Anggaran</span></li>

                    <!-- 1. Pengajuan PPX & Budget -->
                    <?php if ($hasPengMenu): 
                        $peng_patterns = ['z_pengajuan_ppx', 'monthly_budget_list.php', 'monthly_budget_create.php', 'monthly_budget_edit.php', 'monthly_budget_detail.php'];
                        $isPengActive = is_active($peng_patterns);
                    ?>
                        <li class="submenu <?= $isPengActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isPengActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-file-invoice"></i>
                                <span>Pengajuan PPX & Budget</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isPengActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('ppx_budget', 'create') || $auth->hasPermission('ppx_budget', 'update')): ?>
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

                    <!-- 2. Realisasi Kas (BKK / BKM) -->
                    <?php if ($hasRealisasiMenu): 
                        $real_patterns = ['z_realisasi_ppx', 'z_approval_realisasi_asmen'];
                        $isRealActive = is_active($real_patterns);
                    ?>
                        <li class="submenu <?= $isRealActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isRealActive ? 'subdrop' : '' ?>">
                                <i class="fa-solid fa-check-double"></i>
                                <span>Realisasi Kas (BKK/BKM)</span>
                                <?php if ($totalRealisasiBadge > 0): ?>
                                    <span class="nav-parent-badge">!</span>
                                <?php endif; ?>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isRealActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('realisasi_ppx', 'read')): ?>
                                    <li class="<?= is_active('z_realisasi_ppx') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_realisasi_ppx/dashboard.php">Realisasi Anggaran</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('approval_realisasi_asmen', 'approve')): ?>
                                    <li class="<?= is_active('z_approval_realisasi_asmen') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_approval_realisasi_asmen/dashboard.php">
                                            Approval Realisasi<?= navBadge('approval_realisasi_asmen', $badge_counts) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- 3. Approval PPX -->
                    <?php if ($hasPpxApprovalMenu): 
                        $ppx_patterns = ['z_approval_ppx_leader', 'z_approval_ppx_kacab', 'z_approval_ppx_hr', 'z_approval_ppx_asmen', 'z_approval_ppx_finance', 'z_approval_ppx_operasional', 'z_approval_ppx_direktur', 'z_approval_ppx_ga', 'z_approval_ppx_manager'];
                        $isPpxActive = is_active($ppx_patterns);
                    ?>
                        <li class="submenu <?= $isPpxActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isPpxActive ? 'subdrop' : '' ?>">
                                <i class="fa-solid fa-money-bill-1-wave"></i> 
                                <span>Approval PPX</span> 
                                <?php if ($totalPpxBadge > 0): ?>
                                    <span class="nav-parent-badge">!</span>
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

                    <!-- 4. Approval Anggaran -->
                    <?php if ($hasBudgetApprovalMenu): 
                        $b_app_patterns = ['z_approval_budget_kacab', 'z_approval_budget_asmen', 'z_approval_budget_manager', 'z_approval_budget_manajer_ops', 'z_approval_budget_finance'];
                        $isBAppActive = is_active($b_app_patterns);
                    ?>
                        <li class="submenu <?= $isBAppActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isBAppActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-wallet"></i> 
                                <span>Approval Anggaran</span> 
                                <?php if ($totalBudgetBadge > 0): ?>
                                    <span class="nav-parent-badge">!</span>
                                <?php endif; ?>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isBAppActive ? 'block' : 'none' ?>;">
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
                                    <li class="<?= is_active('z_approval_budget_manager') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_approval_budget_manager/dashboard.php">Approval Budget (Manager)<?= navBadge('approval_budget_manager', $badge_counts) ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('approval_budget_manajer_ops', 'approve') || $auth->hasPermission('approval_budget_manajer_ops', 'read')): ?>
                                    <li class="<?= is_active('z_approval_budget_manajer_ops') ? 'active' : '' ?>">
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

                    <!-- 5. Kelola Anggaran & Routing -->
                    <?php if ($hasMasterBudgetMenu): 
                        $b_mgmt_patterns = ['z_ppx_budget/dashboard.php', 'z_ppx_budget/category_manage.php', 'z_master_budget', 'z_master_ppx_routing', 'z_master_budget_routing'];
                        $isBMgmtActive = is_active($b_mgmt_patterns);
                    ?>
                        <li class="submenu <?= $isBMgmtActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isBMgmtActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-coins"></i>
                                <span>Master & Routing Budget</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isBMgmtActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('ppx_budget', 'read')): ?>
                                    <li class="<?= is_active('z_ppx_budget/dashboard.php') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_ppx_budget/dashboard.php">Dashboard Anggaran</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('master_budget', 'read')): ?>
                                    <li class="<?= is_active('z_master_budget') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_master_budget/dashboard.php">Master Anggaran</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('ppx_budget', 'manage_category') || $auth->isSuperAdmin() || $auth->hasPermission('master_budget', 'read') || $auth->hasPermission('realisasi_ppx', 'read')): ?>
                                    <li class="<?= is_active('z_ppx_budget/category_manage.php') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_ppx_budget/category_manage.php">Kategori Budget</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('ppx_routing', 'read')): ?>
                                    <li class="<?= is_active('z_master_ppx_routing') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_master_ppx_routing/dashboard.php">Routing Approval PPX</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('budget_routing', 'read')): ?>
                                    <li class="<?= is_active('z_master_budget_routing') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_master_budget_routing/dashboard.php">Routing Approval Anggaran</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- 6. Laporan PPX & Anggaran -->
                    <?php if ($hasReportMenu): 
                        $rep_patterns = ['z_ppx_budget/usage_report.php', 'z_report_ppx'];
                        $isRepActive = is_active($rep_patterns);
                    ?>
                        <li class="submenu <?= $isRepActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isRepActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-file-alt"></i>
                                <span>Laporan PPX & Budget</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isRepActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('ppx_budget', 'read')): ?>
                                    <li class="<?= is_active('z_ppx_budget/usage_report.php') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_ppx_budget/usage_report.php">Laporan Pemakaian Budget</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($hasReportMonthly): ?>
                                    <li class="<?= is_active('z_report_ppx/dashboard.php') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_report_ppx/dashboard.php">Laporan Anggaran Bulanan</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($hasReportAll): ?>
                                    <li class="<?= is_active('z_report_ppx/report_all.php') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_report_ppx/report_all.php">Laporan PPX Regular & Monthly</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- 3. KELOMPOK: AKUNTANSI & KEUANGAN (ACCOUNTING ENGINE)      -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <?php
                $acc_patterns = ['z_acc_coa', 'z_master_cabang_ppx', 'z_acc_mutasi_bank', 'z_acc_cash_opname', 'z_acc_jurnal'];
                $isAccActive = is_active($acc_patterns);
                $hasAccMenu = $auth->hasPermission('realisasi_ppx', 'read') || 
                              $auth->hasPermission('master_budget', 'read') || 
                              $auth->hasPermission('master_akun', 'read') || 
                              $auth->isSuperAdmin();
                if ($hasAccMenu): ?>
                    <li class="menu-title"><span>Akuntansi & Keuangan</span></li>
                    <li class="submenu <?= $isAccActive ? 'active' : '' ?>">
                        <a href="javascript:void(0);" class="<?= $isAccActive ? 'subdrop' : '' ?>">
                            <i class="fa-solid fa-calculator"></i>
                            <span>Pembukuan & Kas</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul style="display: <?= $isAccActive ? 'block' : 'none' ?>;">
                            <li class="<?= is_active('z_acc_coa') ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_acc_coa/index.php">Master COA (Bagan Akun)</a>
                            </li>
                            <li class="<?= is_active('z_master_cabang_ppx') ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_master_cabang_ppx/index.php">Master Lokasi & Cabang Kas</a>
                            </li>
                            <li class="<?= is_active('z_acc_mutasi_bank') ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_acc_mutasi_bank/index.php">Mutasi Bank & Rekening</a>
                            </li>
                            <li class="<?= is_active('z_acc_cash_opname') ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_acc_cash_opname/index.php">Cash Opname Mingguan</a>
                            </li>
                            <li class="<?= is_active(['z_acc_jurnal/index.php', 'z_acc_jurnal/create.php']) ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_acc_jurnal/index.php">Jurnal Umum & Otomatis</a>
                            </li>
                            <li class="<?= is_active('z_acc_jurnal/buku_besar.php') ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/z_acc_jurnal/buku_besar.php">Buku Besar (Ledger)</a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>


                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- 4. KELOMPOK: LOGISTIK & OPERASIONAL LAIN                  -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <?php 
                $hasInventoryATK = $auth->hasPermission('inventory_atk_master', 'read') || 
                                   $auth->hasPermission('inventory_atk_stok', 'read') || 
                                   $auth->hasPermission('inventory_atk_po', 'read') || 
                                   $auth->hasPermission('inventory_atk_penerimaan', 'read') || 
                                   $auth->hasPermission('inventory_atk_pengambilan', 'read');
                $hasBSTMenu      = $auth->hasPermission('pt_bst_inventory', 'read') || 
                                   $auth->hasPermission('pt_bst_po_pembelian', 'read') || 
                                   $auth->hasPermission('pt_bst_po_masuk', 'read') ||
                                   $auth->hasPermission('pt_bst_asset', 'read');
                if ($hasInventoryATK || $hasBSTMenu): ?>
                    <li class="menu-title"><span>Logistik & Operasional</span></li>

                    <!-- INVENTORY ATK -->
                    <?php if ($hasInventoryATK): 
                        $isInventoryActive = is_active(['z_inventory_atk', 'z_pt_bst_penerimaan']) && !(isset($_GET['ref']) && $_GET['ref'] === 'bst'); 
                        $totalATKBadge = ($badge_counts['inventory_atk_pengambilan'] ?? 0) + 
                                         ($badge_counts['inventory_atk_po'] ?? 0) + 
                                         ($badge_counts['inventory_atk_penerimaan'] ?? 0);
                    ?>
                        <li class="submenu <?= $isInventoryActive ? 'active' : '' ?>">
                            <a href="#" class="<?= $isInventoryActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-boxes"></i> 
                                <span>Inventory ATK</span> 
                                <?php if ($totalATKBadge > 0): ?>
                                    <span class="nav-parent-badge">!</span>
<!--  -->                                <?php endif; ?>
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
                    <?php if ($hasBSTMenu): 
                        $isBSTActive = is_active(['z_pt_bst/inventory', 'z_pt_bst/po_masuk', 'z_pt_bst/po_pembelian', 'z_pt_bst/asset']) || (isset($_GET['ref']) && $_GET['ref'] === 'bst'); 
                    ?>
                        <li class="submenu <?= $isBSTActive ? 'active' : '' ?>">
                            <a href="#" class="<?= $isBSTActive ? 'subdrop' : '' ?>"><i class="fas fa-truck-loading"></i> <span>PT BST</span> <span class="menu-arrow"></span></a>
                            <ul style="display: <?= $isBSTActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('pt_bst_inventory', 'read')): ?>
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
                <?php endif; ?>


                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- 6. KELOMPOK: PENGATURAN SISTEM & ADMINISTRATOR             -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <?php 
                $hasMasterMenu = $auth->hasPermission('master_data', 'read') || $auth->hasPermission('master_vendor', 'read');
                $hasUsersMenu  = $auth->hasPermission('user_management', 'read');
                $hasSuperAdmin = $auth->isSuperAdmin();

                if ($hasMasterMenu || $hasUsersMenu || $hasSuperAdmin): ?>
                    <li class="menu-title"><span>Pengaturan Sistem</span></li>

                    <!-- DATA MASTER -->
                    <?php if ($hasMasterMenu): 
                        $isMasterActive = is_active(['z_master_data', 'z_master_vendor']);
                    ?>
                        <li class="submenu <?= $isMasterActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isMasterActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-database"></i>
                                <span>Data Master</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isMasterActive ? 'block' : 'none' ?>;">
                                <?php if ($auth->hasPermission('master_data', 'read')): ?>
                                    <li class="<?= is_active('z_master_data') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_data/dashboard.php">Dashboard</a></li>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('master_vendor', 'read')): ?>
                                    <li class="<?= is_active('z_master_vendor') ? 'active' : '' ?>"><a href="<?= BASE_URL ?>/z_master_vendor/dashboard.php">Master Vendor</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- ADMINISTRATOR & PENGGUNA -->
                    <?php if ($hasSuperAdmin || $hasUsersMenu): 
                        $isAdminGroupActive = is_active(['z_admin_permissions', 'z_users']);
                    ?>
                        <li class="submenu <?= $isAdminGroupActive ? 'active' : '' ?>">
                            <a href="javascript:void(0);" class="<?= $isAdminGroupActive ? 'subdrop' : '' ?>">
                                <i class="fas fa-user-shield"></i>
                                <span>Administrator</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <ul style="display: <?= $isAdminGroupActive ? 'block' : 'none' ?>;">
                                <?php if ($hasSuperAdmin): ?>
                                    <li class="<?= is_active('z_admin_permissions') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_admin_permissions/dashboard.php">Hak Akses & Permission</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($hasUsersMenu || $hasSuperAdmin): ?>
                                    <li class="<?= is_active('z_users') ? 'active' : '' ?>">
                                        <a href="<?= BASE_URL ?>/z_users/dashboard.php">Manajemen Pengguna</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
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
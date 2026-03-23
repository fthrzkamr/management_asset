<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\add.php
include('../dist/config/config.php');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// Get user data with divisi joined (termasuk jabatan)
$user_id = $_SESSION['id_user'];
$user_query = "SELECT u.*, d.nama_divisi, dp.nama_departemen 
              FROM user_dfm u 
              LEFT JOIN divisi d ON u.divisi = d.id_divisi
              LEFT JOIN departemen dp ON u.departemen = dp.id_departemen
              WHERE u.id_user = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$jabatan_user = $user_data['jabatan'] ?? '-';

// --- ADDED: ambil daftar akun dari tabel master_akun ---
$akun_options_html = '';
$akun_query = "SELECT id_ma, no_akun, nama_akun FROM master_akun ORDER BY no_akun";
$akun_result = mysqli_query($conn, $akun_query);
if ($akun_result) {
    while ($r = mysqli_fetch_assoc($akun_result)) {
        $no = htmlspecialchars($r['no_akun']);
        $nama = htmlspecialchars($r['nama_akun']);
        $akun_options_html .= "<option value=\"{$no}\">{$no} - {$nama}</option>";
    }
}

// TAMBAHAN: Query untuk ambil pengajuan asset yang sudah disetujui dan BELUM direlasikan dengan PPX
$asset_query = "SELECT 
    ar.no_pengajuan, 
    ar.deskripsi_asset,
    ar.estimasi_harga,
    ka.kategori_asset,
    ar.status_pendistribusian,
    ar.tanggal_pengajuan,
    COALESCE(ar.jumlah,0) AS jumlah
FROM asset_request ar
LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
LEFT JOIN ppx_request pr ON ar.no_pengajuan = pr.no_pengajuan_asset
WHERE ar.user_id = ? 
    AND ar.status = 'Disetujui' 
    AND pr.no_pengajuan_asset IS NULL  -- Filter asset yang belum direlasikan dengan PPX
    AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)  -- Filter asset yang belum dihapus
ORDER BY ar.tanggal_pengajuan DESC";

$asset_stmt = mysqli_prepare($conn, $asset_query);
mysqli_stmt_bind_param($asset_stmt, "i", $user_id);
mysqli_stmt_execute($asset_stmt);
$asset_result = mysqli_stmt_get_result($asset_stmt);
$asset_count = mysqli_num_rows($asset_result);

// Cek apakah kolom is_deleted ada di renovation_request
$check_renovasi_deleted = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'renovation_request' 
                            AND COLUMN_NAME = 'is_deleted'";
$renovasi_col_result = mysqli_query($conn, $check_renovasi_deleted);
$renovasi_has_deleted = (mysqli_num_rows($renovasi_col_result) > 0);

// Query untuk ambil pengajuan renovasi yang sudah disetujui dan BELUM direlasikan dengan PPX
$renovasi_where_deleted = $renovasi_has_deleted ? "AND (rr.is_deleted IS NULL OR rr.is_deleted = 0)" : "";

$renovasi_query = "SELECT 
    rr.no_pengajuan,
    rr.area_renovasi,
    rr.jenis_pekerjaan,
    rr.alasan_renovasi,
    rr.prioritas,
    rr.tanggal_pengajuan,
    rr.status
FROM renovation_request rr
LEFT JOIN ppx_request pr ON rr.no_pengajuan COLLATE utf8mb4_general_ci = pr.no_pengajuan_renovasi COLLATE utf8mb4_general_ci
WHERE rr.user_id = ?
    AND rr.status = 'Disetujui'
    AND pr.no_pengajuan_renovasi IS NULL
    $renovasi_where_deleted
ORDER BY rr.tanggal_pengajuan DESC";

$renovasi_stmt = mysqli_prepare($conn, $renovasi_query);
if (!$renovasi_stmt) {
    die("Error preparing renovasi query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($renovasi_stmt, "i", $user_id);
mysqli_stmt_execute($renovasi_stmt);
$renovasi_result = mysqli_stmt_get_result($renovasi_stmt);
$renovasi_count = mysqli_num_rows($renovasi_result);

// Get all divisions for dropdown
$divisi_query = "SELECT id_divisi, nama_divisi FROM divisi ORDER BY nama_divisi";
$divisi_result = mysqli_query($conn, $divisi_query);

// Get all departments for dropdown
$departemen_query = "SELECT id_departemen, nama_departemen FROM departemen ORDER BY nama_departemen";
$departemen_result = mysqli_query($conn, $departemen_query);

// Cek apakah kolom no_service ada di ppx_request
$check_column = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = 'asset_dfm' 
                 AND TABLE_NAME = 'ppx_request' 
                 AND COLUMN_NAME IN ('no_service', 'no_pengajuan_service')";
$col_result = mysqli_query($conn, $check_column);
$service_column_exists = (mysqli_num_rows($col_result) > 0);

// Cek apakah kolom is_deleted ada di service_asset
$check_service_deleted = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'service_asset' 
                          AND COLUMN_NAME = 'is_deleted'";
$service_col_result = mysqli_query($conn, $check_service_deleted);
$service_has_deleted = (mysqli_num_rows($service_col_result) > 0);
$service_where_deleted = $service_has_deleted ? "AND (s.is_deleted IS NULL OR s.is_deleted = 0)" : "";

// Query service yang sedang/sudah diservice dan belum direalisasi ke PPX (grouped by no_service)
// Menampilkan service dengan status 'Service' (sedang proses) dan 'Selesai' untuk user yang sedang login
if ($service_column_exists) {
    $service_query = "SELECT 
        s.no_service,
        MAX(s.status_service) as status_service,
        COUNT(DISTINCT s.id_service) as jumlah_asset,
        SUM(s.biaya_service) as total_biaya,
        MIN(s.tanggal_service) as tanggal_service,
        GROUP_CONCAT(DISTINCT a.nama_aset SEPARATOR ', ') as nama_assets
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset COLLATE utf8mb4_unicode_ci = a.kode_aset COLLATE utf8mb4_unicode_ci
    LEFT JOIN ppx_request pr ON s.no_service COLLATE utf8mb4_unicode_ci = COALESCE(pr.no_service, pr.no_pengajuan_service) COLLATE utf8mb4_unicode_ci
    WHERE s.status_service IN ('Service', 'Selesai')
        AND s.user_input = ?
        AND COALESCE(pr.no_service, pr.no_pengajuan_service) IS NULL
        $service_where_deleted
    GROUP BY s.no_service
    ORDER BY s.tanggal_service DESC";
    
    $service_stmt = mysqli_prepare($conn, $service_query);
    if (!$service_stmt) {
        die("Error preparing service query: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($service_stmt, "i", $user_id);
    mysqli_stmt_execute($service_stmt);
    $service_result = mysqli_stmt_get_result($service_stmt);
    $service_count = mysqli_num_rows($service_result);
} else {
    // Jika kolom belum ada, tampilkan semua service (sedang proses dan selesai) untuk user yang login
    $service_query = "SELECT 
        s.no_service,
        MAX(s.status_service) as status_service,
        COUNT(DISTINCT s.id_service) as jumlah_asset,
        SUM(s.biaya_service) as total_biaya,
        MIN(s.tanggal_service) as tanggal_service,
        GROUP_CONCAT(DISTINCT a.nama_aset SEPARATOR ', ') as nama_assets
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset COLLATE utf8mb4_unicode_ci = a.kode_aset COLLATE utf8mb4_unicode_ci
    WHERE s.status_service IN ('Service', 'Selesai')
        AND s.user_input = ?
        $service_where_deleted
    GROUP BY s.no_service
    ORDER BY s.tanggal_service DESC";
    
    $service_stmt = mysqli_prepare($conn, $service_query);
    if (!$service_stmt) {
        die("Error preparing service query (else): " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($service_stmt, "i", $user_id);
    mysqli_stmt_execute($service_stmt);
    $service_result = mysqli_stmt_get_result($service_stmt);
    $service_count = mysqli_num_rows($service_result);
}

// DEBUG: Cek apakah ada data service dengan status apapun
$debug_query = "SELECT no_service, status_service, COUNT(*) as total 
                FROM service_asset 
                GROUP BY no_service, status_service";
$debug_result = mysqli_query($conn, $debug_query);
$all_services = [];
while ($row = mysqli_fetch_assoc($debug_result)) {
    $all_services[] = $row;
}
// Uncomment line berikut jika ingin debug
// echo "<!-- DEBUG Service Data: " . print_r($all_services, true) . " -->";

// Cek apakah ada parameter asset dari URL
$preselected_asset = $_GET['asset'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<head>
    <style>
        .required {
            color: #dc3545;
        }

        .form-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .priority-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-rendah {
            background: #d4edda;
            color: #155724;
        }

        .priority-sedang {
            background: #fff3cd;
            color: #856404;
        }

        .priority-tinggi {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-urgent {
            background: #721c24;
            color: white;
        }

        .detail-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .btn-remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .info-box small {
            color: #0c5460;
        }

        .asset-info-card {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        #budget_warning .alert {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #budget_warning .alert-danger {
            border-left: 4px solid #dc3545;
        }
    </style>
    <!-- Select2 CSS and Bootstrap4 theme so dropdown matches form styling -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />
    <style>
        /* Ensure Select2 uses compact sizing similar to form-control-sm */
        .select2-container--bootstrap4 .select2-selection--single {
            height: calc(1.5em + .5rem + 2px);
            padding: .15rem .5rem;
            font-size: .875rem;
        }
        .select2-container { width:100% !important; }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title h4 h3-md">Tambah Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Pengajuan PPX</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Pengajuan</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body px-3 px-md-4">
                                <form action="add_insert.php" method="POST" enctype="multipart/form-data" id="formPengajuan">

                                    <!-- SECTION 1: Data Pengaju -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-user"></i> Data Pengaju
                                        </h5>
                                        <div class="row">
                                            <!-- Nama PIC -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama<span class="required">*</span></label>
                                                    <input type="text" name="nama_pic" class="form-control"
                                                        value="<?= htmlspecialchars($user_data['nama_user'] ?? $_SESSION['nama_user']) ?>"
                                                        placeholder="Nama Person In Charge" required readonly>
                                                </div>
                                            </div>

                                            <!-- Jabatan (otomatis dari user_dfm) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jabatan <span class="required">*</span></label>
                                                    <input type="text" name="jabatan" class="form-control"
                                                        value="<?= htmlspecialchars($jabatan_user) ?>" readonly>
                                                    <small class="text-muted">Jabatan diambil otomatis dari profil user</small>
                                                </div>
                                            </div>

                                            <!-- Divisi -->
                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Divisi <span class="required">*</span></label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['nama_divisi'] ?? 'Tidak ada divisi') ?>" readonly>
                                                    <input type="hidden" name="divisi" value="<?= $user_data['divisi'] ?>">
                                                </div>
                                            </div>

                                            <!-- Departemen -->
                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Departemen <span class="required">*</span></label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['nama_departemen'] ?? 'Tidak ada departemen') ?>" readonly>
                                                    <input type="hidden" name="departemen" value="<?= $user_data['departemen'] ?>">
                                                </div>
                                            </div>

                                            <!-- Cabang -->
                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Cabang <span class="required">*</span></label>
                                                    <select name="cabang" class="form-control" required>
                                                        <option value="">-- Pilih Cabang --</option>
                                                        <option value="Jakarta">Jakarta</option>
                                                        <option value="Bekasi">Bekasi</option>
                                                        <option value="Cibinong">Cibinong</option>
                                                        <option value="Medan">Medan</option>
                                                        <option value="Bali">Bali</option>
                                                        <option value="Surabaya">Surabaya</option>
                                                        <option value="Banjarmasin">Banjarmasin</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Bank / Rekening -->
                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama Bank</label>
                                                    <select name="nama_bank" id="nama_bank" class="form-control" onchange="setRekeningPlaceholder()">
                                                        <option value="">-- Pilih Bank --</option>
                                                        <option value="BCA">BCA</option>
                                                        <option value="Mandiri">Mandiri</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama Rekening</label>
                                                    <input type="text" name="nama_rek" class="form-control"
                                                        placeholder="Nama pemilik rekening">
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">No. Rekening</label>
                                                    <input type="text" name="no_rek" id="no_rek" class="form-control"
                                                        placeholder="Nomor rekening" oninput="validateNumber(this)">
                                                </div>
                                            </div>

                                            <!-- Nomor PPX (Auto) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nomor PPX</label>
                                                    <input type="text" name="no_ppx" class="form-control"
                                                        value="PPX-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                                                        readonly>
                                                    <small class="text-muted">Otomatis dibuat sistem</small>
                                                </div>
                                            </div>

                                            <!-- Tanggal Pengajuan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tanggal Pengajuan</label>
                                                    <input type="date" name="tanggal_pengajuan" class="form-control"
                                                        value="<?= date('Y-m-d') ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION: Tipe PPX & Budget Bulanan -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-wallet"></i> Tipe PPX & Anggaran Bulanan
                                        </h5>
                                        
                                        <div class="row">
                                            <!-- Tipe Request -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tipe Pengajuan <span class="required">*</span></label>
                                                    <select name="request_type" id="request_type" class="form-control" required onchange="toggleBudgetCategory()">
                                                        <option value="">-- Pilih Tipe --</option>
                                                        <option value="Regular" selected>Regular PPX (Tanpa Anggaran Bulanan)</option>
                                                        <option value="Monthly_Budget">Monthly Budget PPX (Gunakan Anggaran Bulanan)</option>
                                                    </select>
                                                    <small class="text-muted">
                                                        <strong>Regular:</strong> PPX normal tanpa memotong budget bulanan<br>
                                                        <strong>Monthly Budget:</strong> PPX yang akan memotong dari alokasi budget bulanan
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Pilih Kategori (hanya muncul jika Monthly_Budget) -->
                                            <div class="col-12 col-md-6 mb-3" id="category_section" style="display:none;">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Kategori Anggaran <span class="required">*</span></label>
                                                    <select name="id_category" id="id_category" class="form-control" onchange="showBudgetInfo()">
                                                        <option value="">-- Pilih Kategori --</option>
                                                        <?php
                                                        // Query kategori yang ada budgetnya untuk bulan ini dengan filter divisi
                                                        $current_year = date('Y');
                                                        $current_month = date('n');
                                                        
                                                        // Get current user's division, role for filtering
                                                        $user_id = $_SESSION['id_user'];
                                                        $user_query = "SELECT u.divisi, d.nama_divisi, r.nama_role 
                                                                       FROM user_dfm u 
                                                                       LEFT JOIN divisi d ON u.divisi = d.id_divisi 
                                                                       LEFT JOIN roles r ON u.id_role = r.id_role
                                                                       WHERE u.id_user = $user_id";
                                                        $user_result = mysqli_query($conn, $user_query);
                                                        $user_data = mysqli_fetch_assoc($user_result);
                                                        $user_division_id = $user_data['divisi'];
                                                        $user_division = $user_data['nama_divisi'];
                                                        $user_role = $user_data['nama_role'];
                                                        
                                                        // Build division filter - sama seperti get_budget_categories.php
                                                        if (in_array(strtolower($user_role), ['it', 'ga'])) {
                                                            $division_filter = "AND ub.divisi = 7";
                                                        } elseif ($user_division_id == 7) {
                                                            $division_filter = "AND ub.divisi = 7";
                                                        } else {
                                                            $division_filter = "AND mb.created_by = $user_id";
                                                        }
                                                        
                                                        // Ambil budget sesuai filter role
                                                        // Hanya tampilkan kategori yang punya budget item dengan remaining > 0
                                                        $budget_query = "SELECT bc.id_category, bc.category_name, bc.category_code,
                                                                                SUM(CASE WHEN mbi.remaining > 0 THEN mbi.remaining ELSE 0 END) as remaining, 
                                                                                SUM(mbi.allocation) as allocation, 
                                                                                SUM(mbi.allocation - mbi.remaining) as used_amount
                                                                         FROM ppx_budget_category bc
                                                                         JOIN ppx_monthly_budget_item mbi ON bc.id_category = mbi.id_category
                                                                         JOIN ppx_monthly_budget mb ON mbi.id_budget = mb.id_budget
                                                                         LEFT JOIN user_dfm ub ON mb.created_by = ub.id_user
                                                                         WHERE mb.period_year = $current_year
                                                                           AND mb.period_month = $current_month
                                                                           AND mb.status IN ('Approved', 'Active')
                                                                           AND bc.is_active = 1
                                                                           $division_filter
                                                                         GROUP BY bc.id_category, bc.category_name, bc.category_code
                                                                         HAVING SUM(CASE WHEN mbi.remaining > 0 THEN mbi.remaining ELSE 0 END) > 0
                                                                         ORDER BY bc.category_name";
                                                        $budget_result = mysqli_query($conn, $budget_query);
                                                        while ($cat = mysqli_fetch_assoc($budget_result)):
                                                        ?>
                                                            <option value="<?= $cat['id_category'] ?>" 
                                                                    data-remaining="<?= $cat['remaining'] ?>"
                                                                    data-allocation="<?= $cat['allocation'] ?>"
                                                                    data-used="<?= $cat['used_amount'] ?>"
                                                                    data-code="<?= htmlspecialchars($cat['category_code']) ?>">
                                                                [<?= htmlspecialchars($cat['category_code']) ?>] <?= htmlspecialchars($cat['category_name']) ?> 
                                                                (Sisa: Rp <?= number_format($cat['remaining'], 0, ',', '.') ?>)
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                    <div id="budget_info" class="alert alert-info mt-2" style="display:none;">
                                                        <!-- Info budget akan muncul di sini -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning" id="no_budget_alert" style="display:none;">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Tidak ada budget aktif untuk bulan ini.</strong> Silakan gunakan tipe "Regular PPX" atau hubungi Manager Finance untuk membuat budget bulanan.
                                        </div>
                                    </div>

                                    <!-- TAMBAHAN: Relasi dengan Pengajuan Asset -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-link"></i> Relasi dengan Pengajuan (Opsional)
                                        </h5>

                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">
                                                        Pilih Pengajuan Terkait (Asset / Renovasi / Service)
                                                        <?php if ($asset_count > 0 || $renovasi_count > 0 || $service_count > 0): ?>
                                                            <span class="badge badge-warning ml-2">
                                                                <i class="fas fa-box"></i> Asset: <?= $asset_count ?> |
                                                                <i class="fas fa-tools"></i> Renovasi: <?= $renovasi_count ?> |
                                                                <i class="fas fa-wrench"></i> Service: <?= $service_count ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <select class="form-control" name="no_pengajuan_relasi" id="no_pengajuan_relasi" onchange="showRelationInfo(this)">
                                                        <option value="">-- Tidak ada relasi dengan pengajuan --</option>

                                                        <?php if ($asset_count > 0): ?>
                                                            <optgroup label="📦 Pengajuan Asset (<?= $asset_count ?> pengajuan)">
                                                                <?php
                                                                // output option dengan data-* agar JS bisa mengisi field secara otomatis
                                                                mysqli_data_seek($asset_result, 0);
                                                                while ($asset = mysqli_fetch_assoc($asset_result)):
                                                                    $estimasi_raw = $asset['estimasi_harga'] ?? 0;
                                                                    $estimasi_fmt = number_format((float)$estimasi_raw, 0, ',', '.');
                                                                    $deskripsi = htmlspecialchars($asset['deskripsi_asset']);
                                                                    $kategori = htmlspecialchars($asset['kategori_asset']);
                                                                    $tanggal = htmlspecialchars($asset['tanggal_pengajuan']);
                                                                    $jumlah = htmlspecialchars($asset['jumlah']);
                                                                ?>
                                                                    <option value="<?= htmlspecialchars($asset['no_pengajuan']) ?>"
                                                                        data-kategori="<?= $kategori ?>"
                                                                        data-deskripsi="<?= $deskripsi ?>"
                                                                        data-estimasi="<?= $estimasi_raw ?>"
                                                                        data-estimasi-formatted="<?= $estimasi_fmt ?>"
                                                                        data-tanggal="<?= $tanggal ?>"
                                                                        data-jumlah="<?= $jumlah ?>"
                                                                        <?= ($preselected_asset == $asset['no_pengajuan']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($asset['no_pengajuan']) ?> -
                                                                        <?= $kategori ?> -
                                                                        <?= substr($asset['deskripsi_asset'], 0, 50) ?>
                                                                        <?= strlen($asset['deskripsi_asset']) > 50 ? '...' : '' ?>
                                                                        (Rp <?= $estimasi_fmt ?>)
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </optgroup>
                                                        <?php endif; ?>

                                                        <?php if ($renovasi_count > 0): ?>
                                                            <optgroup label="🔧 Pengajuan Renovasi (<?= $renovasi_count ?> pengajuan)">
                                                                <?php
                                                                mysqli_data_seek($renovasi_result, 0);
                                                                while ($renovasi = mysqli_fetch_assoc($renovasi_result)):
                                                                    $area = htmlspecialchars($renovasi['area_renovasi']);
                                                                    $jenis = htmlspecialchars($renovasi['jenis_pekerjaan']);
                                                                    $alasan = htmlspecialchars($renovasi['alasan_renovasi']);
                                                                    $prioritas = htmlspecialchars($renovasi['prioritas']);
                                                                    $tanggal = htmlspecialchars($renovasi['tanggal_pengajuan']);
                                                                ?>
                                                                    <option value="renovasi_<?= htmlspecialchars($renovasi['no_pengajuan']) ?>"
                                                                        data-type="renovasi"
                                                                        data-area="<?= $area ?>"
                                                                        data-jenis="<?= $jenis ?>"
                                                                        data-alasan="<?= $alasan ?>"
                                                                        data-prioritas="<?= $prioritas ?>"
                                                                        data-tanggal="<?= $tanggal ?>">
                                                                        <?= htmlspecialchars($renovasi['no_pengajuan']) ?> -
                                                                        <?= $area ?> -
                                                                        <?= $jenis ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </optgroup>
                                                        <?php endif; ?>

                                                        <?php if ($service_count > 0): ?>
                                                            <optgroup label="⚙️ Service Asset (<?= $service_count ?> service)">
                                                                <?php
                                                                mysqli_data_seek($service_result, 0);
                                                                while ($svc = mysqli_fetch_assoc($service_result)):
                                                                    $total_biaya_fmt = number_format($svc['total_biaya'] ?? 0, 0, ',', '.');
                                                                    $asset_names = htmlspecialchars(substr($svc['nama_assets'] ?? '', 0, 50));
                                                                    if (strlen($svc['nama_assets'] ?? '') > 50) $asset_names .= '...';
                                                                    $status_label = ($svc['status_service'] == 'Selesai') ? '✓ Selesai' : '⚙️ Proses';
                                                                    $status_class = ($svc['status_service'] == 'Selesai') ? 'text-success' : 'text-warning';
                                                                ?>
                                                                    <option value="service_<?= htmlspecialchars($svc['no_service']) ?>"
                                                                        data-type="service"
                                                                        data-no-service="<?= htmlspecialchars($svc['no_service']) ?>"
                                                                        data-jumlah-asset="<?= $svc['jumlah_asset'] ?>"
                                                                        data-total-biaya="<?= $svc['total_biaya'] ?>"
                                                                        data-status="<?= htmlspecialchars($svc['status_service']) ?>"
                                                                        data-tanggal="<?= htmlspecialchars($svc['tanggal_service']) ?>">
                                                                        <?= htmlspecialchars($svc['no_service']) ?> - 
                                                                        <?= $svc['jumlah_asset'] ?> Asset - 
                                                                        Rp <?= $total_biaya_fmt ?> - 
                                                                        [<?= $status_label ?>] - 
                                                                        <?= $asset_names ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </optgroup>
                                                        <?php endif; ?>
                                                    </select>
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle"></i>
                                                        Asset: sudah <strong>Disetujui</strong> dan <strong>Belum diorder</strong>.
                                                        Renovasi: sudah <strong>Disetujui</strong>.
                                                        Service: status <strong>Service (Proses)</strong> atau <strong>Selesai</strong>, belum direalisasi.
                                                    </small>
                                                </div>

                                                <!-- Info Box Asset -->
                                                <div class="asset-info-card" id="assetInfo" style="display: none;">
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <h6 class="font-weight-bold mb-2">
                                                                <i class="fas fa-box text-warning"></i> Detail Asset Terkait
                                                            </h6>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small>
                                                                <strong>No. Pengajuan:</strong><br>
                                                                <span id="asset-no"></span>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small>
                                                                <strong>Kategori:</strong><br>
                                                                <span id="asset-kategori"></span>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small>
                                                                <strong>Estimasi Harga:</strong><br>
                                                                <span id="asset-estimasi" class="text-success font-weight-bold"></span>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small>
                                                                <strong>Tanggal Pengajuan:</strong><br>
                                                                <span id="asset-tanggal"></span>
                                                            </small>
                                                        </div>

                                                        <!-- Jumlah Item: gunakan col-md-6 agar sejajar dengan Deskripsi -->
                                                        <div class="col-md-6 mt-2">
                                                            <small>
                                                                <strong>Deskripsi:</strong><br>
                                                                <span id="asset-deskripsi"></span>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small>
                                                                <strong>Jumlah Item:</strong><br>
                                                                <span id="asset-jumlah"></span>
                                                            </small>
                                                        </div>

                                                        <!-- Deskripsi: ubah ke col-md-6 agar sejajar dengan Jumlah Item -->
                                                        <div class="col-12 mt-2">
                                                            <div class="info-box">
                                                                <small>
                                                                    <i class="fas fa-lightbulb"></i>
                                                                    <strong>Catatan:</strong> PPX ini terkait dengan pengajuan asset yang belum diorder.
                                                                    Pastikan pengeluaran mendukung pengadaan asset tersebut.
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 2: Detail Item PPX -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-list"></i> Detail Item PPX
                                        </h5>

                                        <div id="detailContainer">
                                            <!-- Item pertama -->
                                            <div class="detail-item position-relative" data-index="0">
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-index="0" title="Hapus item">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                                <div class="row">
                                                    <!-- No Akun -->
                                                    <div class="col-md-3 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">No. Akun <span class="required">*</span></label>
                                                            <select name="detail[0][no_akun]" class="form-control form-control-sm" required>
                                                                <option value="">-- Pilih --</option>
                                                                <?= $akun_options_html ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Rincian -->
                                                    <div class="col-md-3 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Rincian Item <span class="required">*</span></label>
                                                            <input type="text" name="detail[0][rincian]" class="form-control form-control-sm"
                                                                placeholder="Nama item" required>
                                                        </div>
                                                    </div>

                                                    <!-- Jumlah -->
                                                    <div class="col-md-1 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Qty <span class="required">*</span></label>
                                                            <input type="number" name="detail[0][jumlah]" class="form-control form-control-sm"
                                                                min="0" placeholder="0" required onchange="calculateTotal(0)">
                                                        </div>
                                                    </div>

                                                    <!-- Satuan -->
                                                    <div class="col-md-1 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Satuan <span class="required">*</span></label>
                                                            <select name="detail[0][satuan]" class="form-control form-control-sm" required>
                                                                <option value="">--</option>
                                                                <option value="Pcs">Pcs</option>
                                                                <option value="Unit">Unit</option>
                                                                <option value="Set">Set</option>
                                                                <option value="Buah">Buah</option>
                                                                <option value="Paket">Paket</option>
                                                                <option value="Kg">Kg</option>
                                                                <option value="Meter">Meter</option>
                                                                <option value="Liter">Liter</option>
                                                                <option value="Box">Box</option>
                                                                <option value="Roll">Roll</option>
                                                                <option value="Lembar">Lembar</option>
                                                                <option value="Dus">Dus</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Harga Satuan -->
                                                    <div class="col-md-2 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Harga Satuan <span class="required">*</span></label>
                                                            <input type="text" name="detail[0][harga_satuan]" class="form-control form-control-sm harga-input"
                                                                placeholder="Rp 0" required onkeyup="formatRupiah(this); calculateTotal(0)">
                                                        </div>
                                                    </div>

                                                    <!-- Total Harga -->
                                                    <div class="col-md-2 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Total Harga <small class="text-muted">(bisa diubah)</small></label>
                                                            <input type="text" name="detail[0][total_harga]" class="form-control form-control-sm total-harga"
                                                                placeholder="Rp 0" onkeyup="formatRupiah(this); calculateGrandTotal();"
                                                                title="Otomatis dari Qty × Harga Satuan, tapi bisa diedit manual">
                                                        </div>
                                                    </div>

                                                    <!-- Deskripsi -->
                                                    <div class="col-md-12 mb-2">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small">Deskripsi Detail</label>
                                                            <textarea name="detail[0][deskripsi]" class="form-control form-control-sm" rows="1"
                                                                placeholder="Deskripsi tambahan..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tombol Tambah Item -->
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-start">
                                                    <button type="button" class="btn btn-sm btn-success" onclick="addDetailItem()">
                                                        <i class="fas fa-plus"></i> Tambah Item
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Total Keseluruhan -->
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Total Nominal PPX:</strong>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="grandTotal" class="mb-0">Rp 0</h5>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Warning jika melebihi budget -->
                                                <div id="budget_warning" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 3: Justifikasi -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-clipboard-list"></i> Justifikasi & Kepentingan
                                        </h5>
                                        <div class="row">
                                            <!-- Alasan Pengajuan -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Alasan Pengajuan <span class="required">*</span></label>
                                                    <textarea name="alasan_pengajuan" class="form-control" rows="4"
                                                        placeholder="Jelaskan alasan mengapa PPX ini dibutuhkan..." required></textarea>
                                                </div>
                                            </div>

                                            <!-- Tingkat Kepentingan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Prioritas Pengajuan <span class="required">*</span></label>
                                                    <select name="tingkat_kepentingan" class="form-control" required onchange="updatePriorityBadge()">
                                                        <option value="">-- Pilih Tingkat Prioritas --</option>
                                                        <option value="Rendah">Rendah - Bisa ditunda</option>
                                                        <option value="Sedang">Sedang - Diperlukan dalam 1-3 bulan</option>
                                                        <option value="Tinggi">Tinggi - Diperlukan segera</option>
                                                        <option value="Urgent">Urgent - Sangat mendesak</option>
                                                    </select>
                                                    <div id="priorityBadge" class="mt-2"></div>
                                                </div>
                                            </div>

                                            <!-- Tanggal Dibutuhkan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tanggal Dibutuhkan <span class="required">*</span></label>
                                                    <input type="date" name="tanggal_dibutuhkan" class="form-control"
                                                        min="<?= date('Y-m-d') ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 4: File Upload -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-paperclip"></i> Lampiran
                                        </h5>
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Upload File Pendukung</label>
                                                    <input type="file" name="upload_file" class="form-control"
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
                                                    <small class="text-muted">
                                                        Format: PDF, DOC, XLS, JPG, PNG. Maksimal 5MB.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden fields -->
                                    <input type="hidden" name="user_id" value="<?= $_SESSION['id_user'] ?>">
                                    <input type="hidden" name="status" value="Leader">
                                    <input type="hidden" name="id_divisi" value="<?= $user_data['divisi'] ?? '' ?>">
                                    <input type="hidden" name="id_departemen" value="<?= $user_data['departemen'] ?? '' ?>">
                                    <!-- Tambahan: simpan data asset terkait agar submit langsung membawa nilai -->
                                    <input type="hidden" name="asset_no_pengajuan" id="asset_no_input" value="">
                                    <input type="hidden" name="asset_kategori" id="asset_kategori_input" value="">
                                    <input type="hidden" name="asset_estimasi" id="asset_estimasi_input" value="">
                                    <input type="hidden" name="asset_tanggal" id="asset_tanggal_input" value="">

                                    <!-- Submit Buttons -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex flex-column flex-sm-row justify-content-end">
                                                <a href="dashboard.php" class="btn btn-secondary mb-2 mb-sm-0 mr-sm-2">
                                                    <i class="fas fa-times"></i> Batal
                                                </a>
                                                <button type="submit" name="submit_pengajuan" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Submit Pengajuan
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
        let detailIndex = 1;
        const akunOptions = <?= json_encode($akun_options_html) ?>;

        // TAMBAHAN: Show relation info (asset or renovasi) when select changes
        function showRelationInfo(selectElem) {
            const infoBox = document.getElementById('assetInfo');
            if (!selectElem) return;
            const val = selectElem.value;

            if (val) {
                const option = selectElem.options[selectElem.selectedIndex];
                const type = option.getAttribute('data-type');

                if (type === 'renovasi') {
                    // Display renovasi info
                    const no_pengajuan = val.replace('renovasi_', '');
                    document.getElementById('asset-no').textContent = no_pengajuan;
                    document.getElementById('asset-kategori').innerHTML = '<i class="fas fa-tools text-warning"></i> Renovasi';

                    const area = option.getAttribute('data-area') || '-';
                    const jenis = option.getAttribute('data-jenis') || '-';
                    const alasan = option.getAttribute('data-alasan') || '-';
                    const prioritas = option.getAttribute('data-prioritas') || '-';

                    document.getElementById('asset-deskripsi').textContent = area + ' - ' + jenis;
                    document.getElementById('asset-estimasi').textContent = 'Prioritas: ' + prioritas;
                    document.getElementById('asset-tanggal').textContent = option.getAttribute('data-tanggal') || '-';
                    document.getElementById('asset-jumlah').textContent = alasan;

                    infoBox.style.display = 'block';
                } else {
                    // Display asset info (original logic)
                    document.getElementById('asset-no').textContent = val;
                    document.getElementById('asset-kategori').innerHTML = '<i class="fas fa-box text-info"></i> ' + (option.getAttribute('data-kategori') || '-');
                    document.getElementById('asset-deskripsi').textContent = option.getAttribute('data-deskripsi') || '-';
                    const estimasiFormatted = option.getAttribute('data-estimasi-formatted') || '0';
                    document.getElementById('asset-estimasi').textContent = "Rp " + estimasiFormatted;
                    document.getElementById('asset-tanggal').textContent = option.getAttribute('data-tanggal') || '-';
                    document.getElementById('asset-jumlah').textContent = option.getAttribute('data-jumlah') || '-';

                    infoBox.style.display = 'block';
                }

                // OTOMATIS: Fetch item list dan langsung isi ke Detail PPX tanpa modal
                fetch('fetch_asset_items.php?no_pengajuan=' + encodeURIComponent(val))
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success || !data.items || data.items.length === 0) {return;
                        }
                        
                        // Mapping items untuk populateDetailFromItems
                        const items = data.items;
                        const mapped = items.map(it => {
                            const jumlah = parseInt(it.jumlah || 0, 10) || 0;
                            let hargaSatuan = 0;
                            if (it.harga_satuan !== undefined && it.harga_satuan !== null && String(it.harga_satuan).trim() !== '') {
                                hargaSatuan = parseInt(String(it.harga_satuan).replace(/[^0-9]/g, ''), 10) || 0;
                            } else if (it.total_harga !== undefined && jumlah > 0) {
                                hargaSatuan = Math.round((parseInt(String(it.total_harga).replace(/[^0-9]/g, ''), 10) || 0) / jumlah);
                            }
                            return {
                                no_akun: it.no_akun || '',
                                rincian: it.rincian || '',
                                jumlah: jumlah,
                                satuan: it.satuan || '',
                                harga_satuan: hargaSatuan,
                                total_harga: hargaSatuan * jumlah,
                                deskripsi: it.deskripsi || ''
                            };
                        });
                        
                        // Langsung populate ke Detail PPX
                        populateDetailFromItems(mapped);
                        
                        // Tampilkan notifikasi sukses
                        const itemCount = mapped.length;
                        alert(`✓ Berhasil mengisi ${itemCount} item dari pengajuan asset ke Detail PPX`);
                    })
                    .catch(err => {
                        console.error('Error fetch items:', err);
                    });
            } else {
                // kosongkan
                infoBox.style.display = 'none';
                document.getElementById('asset-no').textContent = '';
                document.getElementById('asset-kategori').textContent = '';
                document.getElementById('asset-deskripsi').textContent = '';
                document.getElementById('asset-estimasi').textContent = '';
                document.getElementById('asset-tanggal').textContent = '';
                document.getElementById('asset-jumlah').textContent = '';
                document.getElementById('asset_no_input').value = '';
                document.getElementById('asset_kategori_input').value = '';
                document.getElementById('asset_estimasi_input').value = '';
                document.getElementById('asset_tanggal_input').value = '';

            }
        }

        // populateDetailFromItems expects items with harga_satuan numeric (it sets harga input value with "Rp ...")
        function populateDetailFromItems(items) {
            const container = document.getElementById('detailContainer');
            container.innerHTML = ''; // clear existing
            detailIndex = 0;
            items.forEach(function(it) {
                const idx = detailIndex;
                const harga = (it.harga_satuan !== undefined) ? String(it.harga_satuan) : '0';
                const formattedHarga = formatNumberDisplay(harga);
                const totalComputed = (parseInt(it.jumlah || 0, 10) * parseInt(harga || 0, 10));
                const formattedTotal = formatNumberDisplay(String(totalComputed));

                const itemHtml = `
                    <div class="detail-item position-relative" data-index="${idx}">
                        <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-index="${idx}" title="Hapus item">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="row">
                            <div class="col-12 col-md-4 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">No. Akun <span class="required">*</span></label>
                                    <select name="detail[${idx}][no_akun]" class="form-control" required>
                                        <option value="">-- Pilih No. Akun --</option>
                                        ${akunOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-8 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Rincian Item <span class="required">*</span></label>
                                    <input type="text" name="detail[${idx}][rincian]" class="form-control" value="${escapeHtml(it.rincian || '')}" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Jumlah <span class="required">*</span></label>
                                    <input type="number" name="detail[${idx}][jumlah]" class="form-control" min="0" placeholder="0" required onchange="calculateTotal(${idx})" value="${escapeHtml(String(it.jumlah || 0))}">
                                </div>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Satuan <span class="required">*</span></label>
                                    <select name="detail[${idx}][satuan]" class="form-control" required>
                                        <option value="">-- Pilih Satuan --</option>
                                        <option value="Pcs" ${it.satuan==='Pcs'?'selected':''}>Pcs</option>
                                        <option value="Unit" ${it.satuan==='Unit'?'selected':''}>Unit</option>
                                        <option value="Set" ${it.satuan==='Set'?'selected':''}>Set</option>
                                        <option value="Buah" ${it.satuan==='Buah'?'selected':''}>Buah</option>
                                        <option value="Paket" ${it.satuan==='Paket'?'selected':''}>Paket</option>
                                        <option value="Kg" ${it.satuan==='Kg'?'selected':''}>Kg</option>
                                        <option value="Meter" ${it.satuan==='Meter'?'selected':''}>Meter</option>
                                        <option value="Liter" ${it.satuan==='Liter'?'selected':''}>Liter</option>
                                        <option value="Box" ${it.satuan==='Box'?'selected':''}>Box</option>
                                        <option value="Roll" ${it.satuan==='Roll'?'selected':''}>Roll</option>
                                        <option value="Lembar" ${it.satuan==='Lembar'?'selected':''}>Lembar</option>
                                        <option value="Dus" ${it.satuan==='Dus'?'selected':''}>Dus</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Harga Satuan <span class="required">*</span></label>
                                    <input type="text" name="detail[${idx}][harga_satuan]" class="form-control harga-input" placeholder="Rp 0" required onkeyup="formatRupiah(this); calculateTotal(${idx})" value="Rp ${formattedHarga}">
                                </div>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Total Harga <small class="text-muted">(bisa diubah)</small></label>
                                    <input type="text" name="detail[${idx}][total_harga]" class="form-control total-harga" placeholder="Rp 0"
                                        onkeyup="formatRupiah(this); this.dataset.manualEdit='1'; calculateGrandTotal();"
                                        value="Rp ${formattedTotal}"
                                        title="Otomatis dari Qty × Harga Satuan, tapi bisa diedit manual">
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label class="font-weight-bold">Deskripsi Detail</label>
                                    <textarea name="detail[${idx}][deskripsi]" class="form-control" rows="2">${escapeHtml(it.deskripsi || '')}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', itemHtml);

                // set selected akun if present
                if (it.no_akun) {
                    const sel = container.querySelector(`.detail-item[data-index="${idx}"] select[name="detail[${idx}][no_akun]"]`);
                    if (sel) {
                        const opt = sel.querySelector(`option[value="${it.no_akun}"]`);
                        if (opt) opt.selected = true;
                    }
                }

                detailIndex++;
            });

            updateRemoveButtons();
            calculateGrandTotal();
            // initialize searchable akun selects
            if (typeof initAkunSelects === 'function') initAkunSelects();
        }

        // helper to format number string without prefix, used in modal
        function formatNumberDisplay(val) {
            val = String(val || '').replace(/[^0-9]/g, '') || '0';
            return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // escapeHtml helper to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Format Rupiah
        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, "").toString();
            let split = value.split(",");
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? "." : "";
                rupiah += separator + ribuan.join(".");
            }

            rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
            input.value = rupiah ? "Rp " + rupiah : "";
        }

        // Calculate total for each item (only auto-fills if total is empty or user didn't manually edit)
        function calculateTotal(index) {
            const jumlahInput = document.querySelector(`input[name="detail[${index}][jumlah]"]`);
            const hargaInput = document.querySelector(`input[name="detail[${index}][harga_satuan]"]`);
            const totalInput = document.querySelector(`input[name="detail[${index}][total_harga]"]`);

            if (!jumlahInput || !hargaInput || !totalInput) return;
            
            // Only auto-calculate if total hasn't been manually edited
            if (totalInput.dataset.manualEdit === '1') {
                calculateGrandTotal();
                return;
            }

            let jumlah = parseInt(jumlahInput.value) || 0;
            let harga = parseInt(hargaInput.value.replace(/[^\d]/g, '')) || 0;
            let total = jumlah * harga;

            let formattedTotal = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            totalInput.value = total > 0 ? "Rp " + formattedTotal : "Rp 0";

            calculateGrandTotal();
        }

        // Calculate grand total
        function calculateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.total-harga').forEach(function(input) {
                let value = input.value.replace(/[^,\d]/g, "") || 0;
                grandTotal += parseInt(value);
            });

            let formattedGrandTotal = grandTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            document.getElementById('grandTotal').textContent = grandTotal > 0 ? "Rp " + formattedGrandTotal : "Rp 0";
            
            // Check budget limit if Monthly_Budget type is selected
            checkBudgetLimit(grandTotal);
        }
        
        // Check if total exceeds budget limit
        function checkBudgetLimit(grandTotal) {
            const requestType = document.getElementById('request_type').value;
            const categorySelect = document.getElementById('id_category');
            const warningDiv = document.getElementById('budget_warning');
            
            if (requestType === 'Monthly_Budget' && categorySelect.value) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const remaining = parseFloat(selectedOption.dataset.remaining) || 0;
                
                if (grandTotal > remaining) {
                    const excess = grandTotal - remaining;
                    const formattedExcess = excess.toLocaleString('id-ID');
                    const formattedRemaining = remaining.toLocaleString('id-ID');
                    const formattedTotal = grandTotal.toLocaleString('id-ID');
                    
                    warningDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> PERINGATAN: Nominal Melebihi Budget!</h5>
                            <hr>
                            <p class="mb-2"><strong>Total Nominal PPX:</strong> Rp ${formattedTotal}</p>
                            <p class="mb-2"><strong>Sisa Budget Tersedia:</strong> Rp ${formattedRemaining}</p>
                            <p class="mb-3"><strong>Kelebihan:</strong> <span class="text-danger">Rp ${formattedExcess}</span></p>
                            <hr>
                            <p class="mb-2"><i class="fas fa-info-circle"></i> <strong>Silakan lakukan salah satu dari berikut:</strong></p>
                            <ul class="mb-2">
                                <li>Kurangi nominal item di bawah sisa budget, atau</li>
                                <li>Pilih kategori anggaran lain yang memiliki budget cukup, atau</li>
                                <li>Hubungi <strong>IT</strong> untuk penambahan alokasi budget</li>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `;
                    warningDiv.style.display = 'block';
                } else {
                    warningDiv.style.display = 'none';
                }
            } else {
                warningDiv.style.display = 'none';
            }
        }

        // Remove detail item and reindex
        function removeDetailItem(btnOrIndex) {
            let item = null;
            if (!btnOrIndex) return;
            if (typeof btnOrIndex === 'number' || /^\d+$/.test(String(btnOrIndex))) {
                item = document.querySelector('.detail-item[data-index="' + btnOrIndex + '"]');
            } else if (btnOrIndex instanceof Element) {
                item = btnOrIndex.closest('.detail-item');
            }
            if (!item) return;

            item.parentNode.removeChild(item);

            const items = document.querySelectorAll('.detail-item');
            items.forEach(function(it, idx) {
                it.setAttribute('data-index', idx);
                const removeBtn = it.querySelector('.btn-remove-item');
                if (removeBtn) removeBtn.setAttribute('data-index', idx);

                it.querySelectorAll('[name]').forEach(function(el) {
                    el.name = el.name.replace(/detail\[\d+\]/, 'detail[' + idx + ']');
                });
            });

            detailIndex = document.querySelectorAll('.detail-item').length;
            calculateGrandTotal();
            updateRemoveButtons();
        }

        // Add new detail item
        function addDetailItem() {
            const container = document.getElementById('detailContainer');
            const newItem = document.createElement('div');
            newItem.className = 'detail-item position-relative';
            newItem.setAttribute('data-index', detailIndex);

            newItem.innerHTML = `
                <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-index="${detailIndex}" title="Hapus item">
                     <i class="fas fa-times"></i>
                 </button>
                
                <div class="row">
                    <div class="col-12 col-md-2 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">No. Akun <span class="required">*</span></label>
                            <select name="detail[${detailIndex}][no_akun]" class="form-control form-control-sm" required>
                                <option value="">-- Pilih --</option>
                                ${akunOptions}
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Rincian Item <span class="required">*</span></label>
                            <input type="text" name="detail[${detailIndex}][rincian]" class="form-control form-control-sm"
                                placeholder="Nama item" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-1 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Jumlah <span class="required">*</span></label>
                            <input type="number" name="detail[${detailIndex}][jumlah]" class="form-control form-control-sm"
                                min="0" placeholder="0" required onchange="calculateTotal(${detailIndex})">
                        </div>
                    </div>
                    <div class="col-12 col-md-1 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Satuan <span class="required">*</span></label>
                            <select name="detail[${detailIndex}][satuan]" class="form-control form-control-sm" required>
                                <option value="">--</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Set">Set</option>
                                <option value="Buah">Buah</option>
                                <option value="Paket">Paket</option>
                                <option value="Kg">Kg</option>
                                <option value="Meter">Meter</option>
                                <option value="Liter">Liter</option>
                                <option value="Box">Box</option>
                                <option value="Roll">Roll</option>
                                <option value="Lembar">Lembar</option>
                                <option value="Dus">Dus</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Harga Satuan <span class="required">*</span></label>
                            <input type="text" name="detail[${detailIndex}][harga_satuan]" class="form-control form-control-sm harga-input"
                                placeholder="Rp 0" required onkeyup="formatRupiah(this); calculateTotal(${detailIndex})">
                        </div>
                    </div>
                    <div class="col-12 col-md-2 mb-2">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Total Harga <small class="text-muted">(bisa diubah)</small></label>
                            <input type="text" name="detail[${detailIndex}][total_harga]" class="form-control form-control-sm total-harga"
                                placeholder="Rp 0" onkeyup="formatRupiah(this); this.dataset.manualEdit='1'; calculateGrandTotal();"
                                title="Otomatis dari Qty × Harga Satuan, tapi bisa diedit manual">
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Deskripsi Detail</label>
                            <textarea name="detail[${detailIndex}][deskripsi]" class="form-control form-control-sm" rows="2"
                                placeholder="Deskripsi tambahan untuk item ini..."></textarea>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(newItem);
            detailIndex++;
            updateRemoveButtons();
            // initialize searchable akun selects for the newly added row
            if (typeof initAkunSelects === 'function') initAkunSelects();
        }

        // Update remove buttons visibility
        function updateRemoveButtons() {
            const items = document.querySelectorAll('.detail-item');
            items.forEach(function(item, index) {
                const removeBtn = item.querySelector('.btn-remove-item');
                if (items.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
                if (removeBtn) removeBtn.setAttribute('data-index', item.getAttribute('data-index'));
            });
        }

        // Event delegation for remove button
        document.getElementById('detailContainer').addEventListener('click', function(e) {
            const btn = e.target.closest ? e.target.closest('.btn-remove-item') : null;
            if (!btn || !this.contains(btn)) return;
            e.preventDefault();
            removeDetailItem(btn);
        });

        updateRemoveButtons();

        // Update Priority Badge
        function updatePriorityBadge() {
            const select = document.querySelector('select[name="tingkat_kepentingan"]');
            const badge = document.getElementById('priorityBadge');
            const value = select.value;

            let badgeClass = '';
            let text = '';

            switch (value) {
                case 'Rendah':
                    badgeClass = 'priority-rendah';
                    text = '🟢 RENDAH - Bisa ditunda';
                    break;
                case 'Sedang':
                    badgeClass = 'priority-sedang';
                    text = '🟡 SEDANG - Diperlukan dalam 1-3 bulan';
                    break;
                case 'Tinggi':
                    badgeClass = 'priority-tinggi';
                    text = '🟠 TINGGI - Diperlukan segera';
                    break;
                case 'Urgent':
                    badgeClass = 'priority-urgent';
                    text = '🔴 URGENT - Sangat mendesak';
                    break;
                default:
                    badge.innerHTML = '';
                    return;
            }

            badge.innerHTML = `<span class="priority-badge ${badgeClass}">${text}</span>`;
        }

        // Form validation
        document.getElementById('formPengajuan').addEventListener('submit', function(e) {
            const tanggal = new Date(document.querySelector('input[name="tanggal_dibutuhkan"]').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            tanggal.setHours(0, 0, 0, 0);

            if (tanggal < today) {
                e.preventDefault();
                alert('Tanggal dibutuhkan tidak boleh kurang dari hari ini!');
                return false;
            }

            const detailItems = document.querySelectorAll('.detail-item');
            if (detailItems.length === 0) {
                e.preventDefault();
                alert('Minimal harus ada satu item detail!');
                return false;
            }
        });

        function setRekeningPlaceholder() {
            const bank = document.getElementById("nama_bank").value;
            const noRekInput = document.getElementById("no_rek");

            if (bank === "BCA") {
                noRekInput.placeholder = "Contoh: 1234567890 (BCA)";
            } else if (bank === "Mandiri") {
                noRekInput.placeholder = "Contoh: 9876543210 (Mandiri)";
            } else {
                noRekInput.placeholder = "Nomor rekening";
            }
        }

        function validateNumber(input) {
            input.value = input.value.replace(/[^0-9]/g, '');
        }

        // Toggle Budget Category Section
        function toggleBudgetCategory() {
            const type = document.getElementById('request_type').value;
            const section = document.getElementById('category_section');
            const categorySelect = document.getElementById('id_category');
            const budgetInfo = document.getElementById('budget_info');
            const noBudgetAlert = document.getElementById('no_budget_alert');
            const warningDiv = document.getElementById('budget_warning');
            
            if (type === 'Monthly_Budget') {
                section.style.display = 'block';
                categorySelect.required = true;
                
                // Refresh categories from server untuk bulan berjalan
                refreshBudgetCategories();
                
                // Re-check budget limit if category already selected
                calculateGrandTotal();
            } else {
                section.style.display = 'none';
                categorySelect.required = false;
                budgetInfo.style.display = 'none';
                noBudgetAlert.style.display = 'none';
                warningDiv.style.display = 'none';
            }
        }
        
        // Refresh budget categories via AJAX untuk bulan aktif saat ini
        function refreshBudgetCategories() {
            const categorySelect = document.getElementById('id_category');
            const noBudgetAlert = document.getElementById('no_budget_alert');
            
            fetch('get_budget_categories.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear existing options except the first one
                        categorySelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
                        
                        if (data.categories.length > 0) {
                            data.categories.forEach(cat => {
                                const option = document.createElement('option');
                                option.value = cat.id_category;
                                option.dataset.remaining = cat.remaining;
                                option.dataset.allocation = cat.allocation;
                                option.dataset.used = cat.used_amount;
                                option.dataset.code = cat.category_code;
                                option.textContent = `[${cat.category_code}] ${cat.category_name} (Sisa: Rp ${parseInt(cat.remaining).toLocaleString('id-ID')})`;
                                categorySelect.appendChild(option);
                            });
                            noBudgetAlert.style.display = 'none';
                        } else {
                            noBudgetAlert.style.display = 'block';
                            categorySelect.required = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading budget categories:', error);
                });
        }

        // Show Budget Info
        function showBudgetInfo() {
            const select = document.getElementById('id_category');
            const option = select.options[select.selectedIndex];
            const info = document.getElementById('budget_info');
            
            if (select.value && option) {
                const remaining = parseFloat(option.dataset.remaining) || 0;
                const allocation = parseFloat(option.dataset.allocation) || 0;
                const used = parseFloat(option.dataset.used) || 0;
                const percent = allocation > 0 ? ((used / allocation) * 100).toFixed(2) : 0;
                const code = option.dataset.code || '';
                
                const progressColor = percent > 80 ? 'danger' : (percent > 50 ? 'warning' : 'success');
                
                info.innerHTML = `
                    <strong>Info Budget Kategori: ${code}</strong><br>
                    <div class="row mt-2">
                        <div class="col-4">
                            <small><strong>Alokasi:</strong></small><br>
                            <span class="text-primary">Rp ${allocation.toLocaleString('id-ID')}</span>
                        </div>
                        <div class="col-4">
                            <small><strong>Terpakai:</strong></small><br>
                            <span class="text-danger">Rp ${used.toLocaleString('id-ID')} (${percent}%)</span>
                        </div>
                        <div class="col-4">
                            <small><strong>Sisa:</strong></small><br>
                            <span class="text-success">Rp ${remaining.toLocaleString('id-ID')}</span>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 20px;">
                        <div class="progress-bar bg-${progressColor}" style="width: ${Math.min(percent, 100)}%">
                            ${percent}%
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">
                        <i class="fas fa-info-circle"></i> 
                        Pastikan nominal PPX tidak melebihi sisa budget yang tersedia
                    </small>
                `;
                info.style.display = 'block';
                
                // Re-check budget limit when category changes
                calculateGrandTotal();
            } else {
                info.style.display = 'none';
                document.getElementById('budget_warning').style.display = 'none';
            }
        }

        // Validate PPX amount vs Budget when type is Monthly_Budget
        document.getElementById('formPengajuan').addEventListener('submit', function(e) {
            const requestType = document.getElementById('request_type').value;
            
            if (requestType === 'Monthly_Budget') {
                const categorySelect = document.getElementById('id_category');
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                
                if (!categorySelect.value) {
                    e.preventDefault();
                    alert('Silakan pilih kategori anggaran untuk tipe Monthly Budget PPX!');
                    categorySelect.focus();
                    return false;
                }
                
                // Get total PPX amount
                const grandTotalText = document.getElementById('grandTotal').innerText;
                const totalPPX = parseFloat(grandTotalText.replace(/[^0-9]/g, '')) || 0;
                
                // Get remaining budget
                const remaining = parseFloat(selectedOption.dataset.remaining) || 0;
                
                if (totalPPX > remaining) {
                    e.preventDefault();
                    const excess = totalPPX - remaining;
                    const confirmMsg = `⚠️ PERINGATAN: NOMINAL MELEBIHI BUDGET!\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `Total Nominal PPX: Rp ${totalPPX.toLocaleString('id-ID')}\n` +
                          `Sisa Budget Tersedia: Rp ${remaining.toLocaleString('id-ID')}\n` +
                          `Kelebihan: Rp ${excess.toLocaleString('id-ID')}\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +
                          `❌ Pengajuan PPX TIDAK DAPAT DIPROSES!\n\n` +
                          `Silakan lakukan salah satu:\n` +
                          `1. Kurangi nominal item sesuai sisa budget\n` +
                          `2. Pilih kategori anggaran lain\n` +
                          `3. Hubungi Manager Finance untuk penambahan budget\n\n` +
                          `📞 Kontak: Manager Finance\n` +
                          `   Ext: 101 / WA: 0812-xxxx-xxxx`;
                    
                    alert(confirmMsg);
                    
                    // Scroll to warning
                    const warningDiv = document.getElementById('budget_warning');
                    if (warningDiv) {
                        warningDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    return false;
                }
            }
            
            // Validasi lainnya tetap jalan
            const tanggal = new Date(document.querySelector('input[name="tanggal_dibutuhkan"]').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            tanggal.setHours(0, 0, 0, 0);

            if (tanggal < today) {
                e.preventDefault();
                alert('Tanggal dibutuhkan tidak boleh kurang dari hari ini!');
                return false;
            }

            const detailItems = document.querySelectorAll('.detail-item');
            if (detailItems.length === 0) {
                e.preventDefault();
                alert('Minimal harus ada satu item detail!');
                return false;
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleBudgetCategory();
            if (typeof initAkunSelects === 'function') initAkunSelects();
        });
    </script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 on No. Akun selects while preserving compact bootstrap look
        function initAkunSelects() {
            // use jQuery if available
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;

            // selector: any select whose name contains [no_akun]
            jQuery('select[name^="detail"]').filter(function() {
                return (this.name || '').indexOf('[no_akun]') !== -1;
            }).each(function() {
                const $s = jQuery(this);
                // avoid double-init
                if ($s.data('select2')) return;
                $s.select2({
                    theme: 'bootstrap4',
                    placeholder: '-- Pilih --',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: 8 // only show search when many options
                });
            });
        }
    </script>

</body>

</html>
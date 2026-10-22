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

// --- ADDED: ambil daftar kategori budget dari tabel ppx_budget_category ---
$categories_query = "SELECT * FROM ppx_budget_category WHERE is_active = 1 ORDER BY category_name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
if ($categories_result) {
    while ($r = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $r;
    }
} else {
    error_log("Error fetching budget categories: " . mysqli_error($conn));
}

$categoryOptionsHtml = '';
$kategoriNameOptionsHtml = '';
foreach ($categories as $cat) {
    $cat_code = isset($cat['category_code']) ? $cat['category_code'] : '';
    $cat_tipe = isset($cat['tipe_anggaran']) ? $cat['tipe_anggaran'] : '';
    
    $categoryOptionsHtml .= '<option value="' . htmlspecialchars($cat['id_category']) . '" data-code="' . htmlspecialchars($cat_code) . '" data-tipe="' . htmlspecialchars($cat_tipe) . '">' . htmlspecialchars($cat['category_name']) .'</option>';
    $kategoriNameOptionsHtml .= '<option value="' . htmlspecialchars($cat['category_name']) . '">' . htmlspecialchars($cat['category_name']) .'</option>';
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
                                            <!-- <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama<span class="required">*</span></label>
                                                    <input type="text" name="nama_pic" class="form-control"
                                                        value="<?= htmlspecialchars($user_data['nama_user'] ?? $_SESSION['nama_user']) ?>"
                                                        placeholder="Nama Person In Charge" required readonly>
                                                </div>
                                            </div> -->

                                            <!-- Jabatan (otomatis dari user_dfm) -->
                                            <!-- <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jabatan <span class="required">*</span></label>
                                                    <input type="text" name="jabatan" class="form-control"
                                                        value="<?= htmlspecialchars($jabatan_user) ?>" readonly>
                                                    <small class="text-muted">Jabatan diambil otomatis dari profil user</small>
                                                </div>
                                            </div> -->

                                            <!-- Divisi -->
                                            <!-- <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Divisi <span class="required">*</span></label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['nama_divisi'] ?? 'Tidak ada divisi') ?>" readonly>
                                                    <input type="hidden" name="divisi" value="<?= $user_data['divisi'] ?>">
                                                </div>
                                            </div> -->

                                            <!-- Departemen -->
                                            <!-- <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Departemen <span class="required">*</span></label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['nama_departemen'] ?? 'Tidak ada departemen') ?>" readonly>
                                                    <input type="hidden" name="departemen" value="<?= $user_data['departemen'] ?>">
                                                </div>
                                            </div> -->

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

                                            <!-- Tujuan Pencarian -->
                                            <div class="col-12 col-md-8 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tujuan Pencarian <span class="required">*</span></label>
                                                    <input type="text" name="tujuan_pencarian" class="form-control"
                                                        placeholder="Contoh: Pembelian ATK untuk operasional kantor" required>
                                                </div>
                                            </div>

                                            <!-- Metode Pembayaran -->
                                            <div class="col-12 col-md-4 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Metode Pembayaran <span class="required">*</span></label>
                                                    <select name="metode_pembayaran" id="metode_pembayaran" class="form-control" required onchange="toggleMetodePembayaran()">
                                                        <option value="">-- Pilih Metode --</option>
                                                        <option value="Transfer">Transfer</option>
                                                        <option value="Cash">Cash</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Fields Transfer (muncul kalau pilih Transfer) -->
                                            <div id="transfer_fields" class="col-12" style="display:none;">
                                                <div class="row">
                                                    <div class="col-12 col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Nama Bank / VA</label>
                                                            <select name="nama_bank" id="nama_bank" class="form-control" onchange="setRekeningPlaceholder()">
                                                                <option value="">-- Pilih Bank --</option>
                                                                <option value="BCA">BCA</option>
                                                                <option value="Mandiri">Mandiri</option>
                                                                <option value="BNI">BNI</option>
                                                                <option value="BRI">BRI</option>
                                                                <option value="CIMB Niaga">CIMB Niaga</option>
                                                                <option value="Permata">Permata</option>
                                                                <option value="Lainnya">Lainnya</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Nama Rekening / VA</label>
                                                            <input type="text" name="nama_rek" id="nama_rek" class="form-control"
                                                                placeholder="Nama pemilik rekening">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">No. Rekening / VA</label>
                                                            <input type="text" name="no_rek" id="no_rek" class="form-control"
                                                                placeholder="Nomor rekening" oninput="validateNumber(this)">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Field Cash (muncul kalau pilih Cash) -->
                                            <div id="cash_fields" class="col-12 col-md-8" style="display:none;">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama Penerima</label>
                                                    <input type="text" name="nama_rek" id="nama_rek_cash" class="form-control"
                                                        placeholder="Nama penerima uang cash">
                                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Isi nama lengkap penerima dana cash.</small>
                                                </div>
                                            </div>

                                            <!-- ===== Hidden inputs: data terkirim tapi tidak tampil di layar ===== -->
                                            <input type="hidden" name="no_ppx"
                                                value="PPX-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>">
                                            <input type="hidden" name="user_id"    value="<?= (int)$user_id ?>">
                                            <input type="hidden" name="status"     value="Menunggu Approval">
                                            <input type="hidden" name="nama_pic"   value="<?= htmlspecialchars($user_data['nama_user'] ?? '') ?>">
                                            <input type="hidden" name="jabatan"    value="<?= htmlspecialchars($user_data['jabatan'] ?? '') ?>">
                                            <input type="hidden" name="divisi"     value="<?= htmlspecialchars($user_data['divisi'] ?? '') ?>">
                                            <input type="hidden" name="departemen" value="<?= htmlspecialchars($user_data['departemen'] ?? '') ?>">
                                            <!-- ===== End hidden inputs ===== -->
                                            <!-- <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nomor PPX</label>
                                                    <input type="text" name="no_ppx" class="form-control"
                                                        value="PPX-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                                                        readonly>
                                                    <small class="text-muted">Otomatis dibuat sistem</small>
                                                </div>
                                            </div> -->

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

                                    <!-- Hidden inputs agar insert logic tidak error -->
                                    <input type="hidden" name="request_type" id="request_type" value="Regular">
                                    <input type="hidden" name="id_category" id="id_category" value="">
                                    <div id="category_section" style="display:none;"></div>
                                    <div id="budget_info" style="display:none;"></div>
                                    <div id="no_budget_alert" style="display:none;"></div>

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
                                        
                                        <!-- Alokasi per Kategori -->
                                        <div id="categoriesContainer">
                                            <!-- Category groups will be added here -->
                                        </div>
                                        
                                        <button type="button" id="addCategory" class="btn btn-primary btn-sm mt-3" style="width: auto; min-width: 150px;">
                                            <i class="fa fa-plus"></i> Tambah Akun Baru
                                        </button>
                                        
                                        <hr class="mt-4">
                                        <div class="row">
                                            <div class="col-md-6 offset-md-6">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h5 class="text-right mb-0"><strong>Total Alokasi:</strong></h5>
                                                        <h4 class="text-primary text-right mb-0" id="totalAllocation">Rp 0</h4>
                                                        <input type="hidden" name="total_allocation" id="total_allocation" value="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden fields for category and item data -->
                                    <input type="hidden" name="items_data" id="items_data" value="">
                                    

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
                                                        placeholder="Jelaskan alasan mengapa PPX ini dibutuhkan..." ></textarea>
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
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" >
                                                    <small class="text-muted">
                                                        Format: PDF, DOC, XLS, JPG, PNG. Maksimal 5MB.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden fields -->
                                    <input type="hidden" name="user_id" value="<?= $_SESSION['id_user'] ?>">
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
        // Format Rupiah function
        function formatRupiah(angka) {
            if (!angka) return '';
            let number_string = angka.toString().replace(/[^,\d]/g, '');
            let split = number_string.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return rupiah ? 'Rp ' + rupiah : '';
        }

        // Parse Rupiah to number
        function parseRupiah(rupiah) {
            if (!rupiah) return 0;
            return parseInt(rupiah.toString().replace(/[^0-9]/g, '')) || 0;
        }

        const akunOptions = <?= json_encode($akun_options_html) ?>;
        const categoryOptions = `<?= str_replace("`", "\\`", $categoryOptionsHtml) ?>`;
        const kategoriNameOptions = `<?= str_replace("`", "\\`", $kategoriNameOptionsHtml) ?>`;
        let categoryCounter = 0;

        function addCategory() {
            categoryCounter++;
            const catId = 'cat_' + Date.now() + '_' + categoryCounter;
            
            const categoryBlock = `
                <div class="border rounded p-3 mb-3 category-block" data-category-id="${catId}" style="background-color: #f8f9fa;">
                    <div class="row align-items-center mb-2">
                        <div class="col-md-5">
                            <label class="mb-1" style="font-size: 13px;"><strong>Kategori:</strong></label>
                            <select class="form-control form-control-sm category-select" data-category-id="${catId}" required>
                                <option value="">-- Pilih Kategori --</option>
                                ${kategoriNameOptions}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="mb-1" style="font-size: 13px;"><strong>Subtotal Akun:</strong></label><br>
                            <span class="category-subtotal text-primary font-weight-bold h5" data-category-id="${catId}">Rp 0</span>
                        </div>
                        <div class="col-md-3 text-right" style="padding-top: 24px;">
                            <button type="button" class="btn btn-sm btn-danger remove-category-btn" title="Hapus Akun">
                                <i class="fa fa-trash"></i> Hapus Akun
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 35%;">Rincian Item</th>
                                    <th style="width: 10%;">Qty</th>
                                    <th style="width: 15%;">Satuan</th>
                                    <th style="width: 17%;">Harga Satuan</th>
                                    <th style="width: 17%;">Total</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                            </thead>
                            <tbody class="items-body" data-category-id="${catId}">
                                <!-- Items will be added here -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="p-2">
                                        <button type="button" class="btn btn-xs btn-success add-item-btn" data-category-id="${catId}">
                                            <i class="fa fa-plus"></i> Item Baru
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;
            
            $('#categoriesContainer').append(categoryBlock);
            bindCategoryEvents();
            addItemToCategory(catId);
            updateCategoryForItems(catId);
            initAkunSelects();
        }

        function addItemToCategory(categoryId) {
            const itemId = 'item_' + Date.now() + '_' + Math.random();
            const itemIndex = Date.now() + '_' + Math.floor(Math.random() * 10000);
            
            const itemRow = `
                <tr data-item-id="${itemId}" data-category-id="${categoryId}" data-item-index="${itemIndex}">
                    <td>
                        <input type="hidden" name="detail[${itemIndex}][no_akun]" class="item-category-ref" data-category-id="${categoryId}" value="">
                        <input type="text" name="detail[${itemIndex}][rincian]" class="form-control form-control-sm item-rincian" placeholder="Deskripsi item" required>
                    </td>
                    <td><input type="number" name="detail[${itemIndex}][jumlah]" class="form-control form-control-sm item-qty" value="1" min="1"></td>
                    <td>
                        <select name="detail[${itemIndex}][satuan]" class="form-control form-control-sm item-satuan">
                            <option value="">-</option>
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
                    </td>
                    <td><input type="text" name="detail[${itemIndex}][harga_satuan]" class="form-control form-control-sm item-price" value="" placeholder="Rp 0"></td>
                    <td><input type="text" name="detail[${itemIndex}][total_harga]" class="form-control form-control-sm item-total" value="" placeholder="Rp 0"></td>
                    <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
                </tr>
            `;
            
            $(`.items-body[data-category-id="${categoryId}"]`).append(itemRow);
            bindItemEvents();
            updateCategoryForItems(categoryId);
        }

        function updateCategoryForItems(categoryId) {
            const selectedValue = $(`.category-select[data-category-id="${categoryId}"]`).val();
            $(`.items-body[data-category-id="${categoryId}"]`).find('.item-category-ref').val(selectedValue || '');
        }

        function bindCategoryEvents() {
            $('.add-item-btn').off('click').on('click', function() {
                const catId = $(this).data('category-id');
                addItemToCategory(catId);
            });
            
            $('.remove-category-btn').off('click').on('click', function() {
                if (confirm('Hapus kategori ini beserta semua itemnya?')) {
                    $(this).closest('.category-block').remove();
                    updateTotals();
                }
            });
            
            $('.category-select').off('change').on('change', function() {
                $(this).removeClass('is-invalid');
                const catId = $(this).data('category-id');
                updateCategoryForItems(catId);
            });
        }

        function bindItemEvents() {
            $('.item-price').off('keyup input').on('keyup input', function() {
                $(this).removeClass('is-invalid');
                const num = parseRupiah($(this).val());
                $(this).val(formatRupiah(num));
                updateItemTotal($(this).closest('tr'));
            });
            
            $('.item-qty').off('change input').on('change input', function() {
                updateItemTotal($(this).closest('tr'));
            });
            
            $('.remove-item-btn').off('click').on('click', function() {
                const $tbody = $(this).closest('tbody');
                $(this).closest('tr').remove();
                if ($tbody.find('tr').length === 0) {
                    const catId = $tbody.data('category-id');
                    addItemToCategory(catId);
                }
                updateTotals();
            });
            
            $('.item-total').off('keyup input').on('keyup input', function() {
                $(this).removeClass('is-invalid');
                const num = parseRupiah($(this).val());
                $(this).val(formatRupiah(num));
                const catId = $(this).closest('tr').data('category-id');
                updateCategorySubtotal(catId);
            });
        }

        function updateItemTotal($row) {
            const qty = parseInt($row.find('.item-qty').val()) || 0;
            const price = parseRupiah($row.find('.item-price').val()) || 0;
            const total = qty * price;
            $row.find('.item-total').val(formatRupiah(total));
            const catId = $row.data('category-id');
            updateCategorySubtotal(catId);
        }

        function updateCategorySubtotal(categoryId) {
            let subtotal = 0;
            $(`.items-body[data-category-id="${categoryId}"] tr`).each(function() {
                subtotal += parseRupiah($(this).find('.item-total').val());
            });
            $(`.category-subtotal[data-category-id="${categoryId}"]`).text('Rp ' + subtotal.toLocaleString('id-ID'));
            updateTotals();
        }

        function updateTotals() {
            let total = 0;
            $('.items-body tr').each(function() {
                total += parseRupiah($(this).find('.item-total').val());
            });
            $('#totalAllocation').text('Rp ' + total.toLocaleString('id-ID'));
            $('#total_allocation').val(total);
        }

        $(document).ready(function() {
            $('#addCategory').on('click', function() {
                addCategory();
            });
            // Add first category on load
            addCategory();
        });

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

        // populateDetailFromItems updated for category-based structure
        function populateDetailFromItems(items) {
            // Clear existing categories
            $('#categoriesContainer').empty();
            categoryCounter = 0;

            // Create one category for these items
            addCategory();
            const catId = $('.category-block').last().data('category-id');

            items.forEach(function(it) {
                const itemId = 'item_auto_' + Date.now() + '_' + Math.random();
                const itemIndex = Date.now() + '_' + Math.floor(Math.random() * 100000);
                
                const hargaFormatted = formatRupiah(it.harga_satuan);
                const totalFormatted = formatRupiah(it.total_harga);

                const itemRow = `
                    <tr data-item-id="${itemId}" data-category-id="${catId}" data-item-index="${itemIndex}">
                        <td>
                            <input type="hidden" name="detail[${itemIndex}][no_akun]" class="item-category-ref" data-category-id="${catId}" value="">
                            <input type="text" name="detail[${itemIndex}][rincian]" class="form-control form-control-sm item-rincian" value="${escapeHtml(it.rincian || '')}" placeholder="Deskripsi item" required>
                        </td>
                        <td><input type="number" name="detail[${itemIndex}][jumlah]" class="form-control form-control-sm item-qty" value="${it.jumlah || 1}" min="1"></td>
                        <td>
                            <select name="detail[${itemIndex}][satuan]" class="form-control form-control-sm item-satuan">
                                <option value="">-</option>
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
                        </td>
                        <td><input type="text" name="detail[${itemIndex}][harga_satuan]" class="form-control form-control-sm item-price" value="${hargaFormatted}" placeholder="0"></td>
                        <td><input type="text" name="detail[${itemIndex}][total_harga]" class="form-control form-control-sm item-total" value="${totalFormatted}" placeholder="0"></td>
                        <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
                    </tr>
                `;
                
                $(`.items-body[data-category-id="${catId}"]`).append(itemRow);
            });

            // Set the account for the main category dropdown
            items.forEach((it, idx) => {
                if (it.no_akun && idx === 0) {
                    $(`.category-select[data-category-id="${catId}"]`).val(it.no_akun);
                    updateCategoryForItems(catId);
                }
            });

            bindItemEvents();
            updateCategorySubtotal(catId);
            initAkunSelects();
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

            const detailItems = document.querySelectorAll('.items-body tr');
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
                                option.value = cat.id_budget_item;
                                option.dataset.categoryId = cat.id_category;
                                option.dataset.remaining = cat.remaining;
                                option.dataset.allocation = cat.allocation;
                                option.dataset.used = cat.used_amount;
                                option.dataset.code = cat.category_code;
                                option.dataset.tipe = cat.tipe_anggaran;
                                option.textContent = `[${cat.tipe_anggaran}] ${cat.category_name} (Sisa: Rp ${parseInt(cat.remaining).toLocaleString('id-ID')})`;
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
                const tipe = option.dataset.tipe || '';
                
                const progressColor = percent > 80 ? 'danger' : (percent > 50 ? 'warning' : 'success');
                
                info.innerHTML = `
                    <strong>Info Budget: ${tipe} - [${code}]</strong><br>
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

            const detailItems = document.querySelectorAll('.items-body tr');
            if (detailItems.length === 0) {
                e.preventDefault();
                alert('Minimal harus ada satu item detail!');
                return false;
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleBudgetCategory();
            toggleMetodePembayaran();
            if (typeof initAkunSelects === 'function') initAkunSelects();
        });

        // Sync nama penerima (cash) ke field nama_rek saat submit
        document.getElementById('formPengajuan').addEventListener('submit', function() {
            const metode = document.getElementById('metode_pembayaran').value;
            if (metode === 'Cash') {
                const cashVal = document.getElementById('nama_rek_cash');
                const namaRek = document.getElementById('nama_rek');
                if (cashVal && namaRek) namaRek.value = cashVal.value;
            }
        });
        // Toggle metode pembayaran: Transfer vs Cash
        function toggleMetodePembayaran() {
            const metode = document.getElementById('metode_pembayaran').value;
            const transferFields = document.getElementById('transfer_fields');
            const cashFields = document.getElementById('cash_fields');

            // Reset nama_rek values to avoid submitting stale data
            const namaRekTransfer  = document.getElementById('nama_rek');
            const namaRekCash      = document.getElementById('nama_rek_cash');

            if (metode === 'Transfer') {
                transferFields.style.display = 'block';
                cashFields.style.display = 'none';
                if (namaRekCash) namaRekCash.value = '';
            } else if (metode === 'Cash') {
                transferFields.style.display = 'none';
                cashFields.style.display = 'block';
                // sync nama_rek_cash value into the hidden transfer nama_rek so it gets submitted
                if (namaRekTransfer) namaRekTransfer.value = '';
            } else {
                transferFields.style.display = 'none';
                cashFields.style.display = 'none';
            }
        }
    </script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 on No. Akun selects while preserving compact bootstrap look
        function initAkunSelects() {
            // use jQuery if available
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;

            // Initialize select2 on the main category/account select
            jQuery('.category-select').each(function() {
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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($_SESSION['error'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Peringatan',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonText: 'Tutup'
            });
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?= addslashes($_SESSION['success']) ?>',
                confirmButtonText: 'Tutup'
            });
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

</body>

</html>
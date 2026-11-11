<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'create', 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$kode_vendor         = trim($_POST['kode_vendor'] ?? '');
$nama_vendor         = trim($_POST['nama_vendor'] ?? '');
$kategori_vendor     = trim($_POST['kategori_vendor'] ?? '');
$peruntukan_vendor   = trim($_POST['peruntukan_vendor'] ?? '');
$area                = trim($_POST['area'] ?? '');
$pic_ga              = trim($_POST['pic_ga'] ?? '');
$kontak_vendor       = trim($_POST['kontak_vendor'] ?? '');
$email               = trim($_POST['email'] ?? '');
$alamat              = trim($_POST['alamat'] ?? '');
$status_hukum        = trim($_POST['status_hukum'] ?? '');
$npwp                = trim($_POST['npwp'] ?? '');
$status_pkp          = trim($_POST['status_pkp'] ?? 'Non PKP');
$nama_bank           = trim($_POST['nama_bank'] ?? '');
$no_rekening         = trim($_POST['no_rekening'] ?? '');
$atas_nama_rekening  = trim($_POST['atas_nama_rekening'] ?? '');
$metode_pembayaran   = trim($_POST['metode_pembayaran'] ?? '');
$lead_time           = trim($_POST['lead_time'] ?? '');
$status_vendor       = trim($_POST['status_vendor'] ?? 'Aktif');
$status_mou          = trim($_POST['status_mou'] ?? 'Tidak Ada');
$keterangan          = trim($_POST['keterangan'] ?? '');
$review_performa     = trim($_POST['review_performa'] ?? '');
$created_by          = $_SESSION['nama_user'] ?? ($_SESSION['username'] ?? 'System');

if (empty($nama_vendor)) {
    $_SESSION['error'] = 'Nama Vendor wajib diisi!';
    header("Location: vendor_add.php");
    exit();
}

// Generate kode otomatis jika kosong
if (empty($kode_vendor)) {
    $prefixMap = [
        'Pest Control'      => 'PC',
        'Cleaning Service'  => 'CS',
        'Entertaiment'      => 'ENT',
        'Entertainment'     => 'ENT',
        'Teknisi'           => 'TK',
        'Kendaraan'         => 'KND',
        'Elektronik'        => 'ELK',
        'ATK'               => 'ATK',
        'Perawatan Gedung'  => 'PG',
        'Ekspedisi'         => 'EKS',
        'Percetakan'        => 'PRC',
        'Internet'          => 'INT',
        'Perlengkapan'      => 'PLG'
    ];
    $cleanKat = trim($kategori_vendor);
    $prefix = $prefixMap[$cleanKat] ?? '';
    if (empty($prefix)) {
        $prefix = !empty($cleanKat) ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $cleanKat), 0, 3)) : 'VND';
        if (strlen($prefix) < 2) $prefix = 'VND';
    }

    $q_next = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(kode_vendor, '-', -1) AS UNSIGNED)), 0) + 1 AS next_num FROM master_vendor");
    $next = 1;
    if ($q_next && $r_next = mysqli_fetch_assoc($q_next)) {
        $next = (int)$r_next['next_num'];
    }
    $kode_vendor = sprintf("%s-VND-%03d", $prefix, $next);
}

// Validasi seluruh field wajib (kecuali Keterangan & Catatan Performa)
$requiredFields = [
    'Kode Vendor'                => $kode_vendor,
    'Nama Vendor'                => $nama_vendor,
    'Status Hukum'               => $status_hukum,
    'Kategori Vendor'            => $kategori_vendor,
    'Peruntukan Vendor'          => $peruntukan_vendor,
    'Area Layanan'               => $area,
    'Status Vendor'              => $status_vendor,
    'Nama PIC Tim GA'            => $pic_ga,
    'Contact Vendor / WhatsApp'  => $kontak_vendor,
    'Email Vendor'               => $email,
    'Alamat Lengkap'             => $alamat,
    'NPWP'                       => $npwp,
    'Status PKP'                 => $status_pkp,
    'Status MoU'                 => $status_mou,
    'Nama Bank'                  => $nama_bank,
    'No. Rekening'               => $no_rekening,
    'Atas Nama Rekening'         => $atas_nama_rekening,
    'Metode Pembayaran'          => $metode_pembayaran,
    'Lead Time'                  => $lead_time,
];

foreach ($requiredFields as $fieldLabel => $val) {
    if ($val === '') {
        $_SESSION['error'] = "Field '$fieldLabel' wajib diisi!";
        header("Location: vendor_add.php");
        exit();
    }
}

// Handle upload Soft File MoU
$file_mou = null;
if (isset($_FILES['file_mou']) && $_FILES['file_mou']['error'] === UPLOAD_ERR_OK) {
    $fileTmp   = $_FILES['file_mou']['tmp_name'];
    $fileName  = $_FILES['file_mou']['name'];
    $fileSize  = $_FILES['file_mou']['size'];
    $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed   = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file MoU tidak diizinkan! Format yang didukung: PDF, DOC, DOCX, JPG, PNG.';
        header("Location: vendor_add.php");
        exit();
    }
    if ($fileSize > 10 * 1024 * 1024) {
        $_SESSION['error'] = 'Ukuran file MoU terlalu besar! Maksimal 10MB.';
        header("Location: vendor_add.php");
        exit();
    }

    $uploadDir = __DIR__ . '/../uploads/vendor_mou/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $cleanKode   = preg_replace('/[^a-zA-Z0-9_-]/', '', $kode_vendor);
    $newFileName = 'mou_' . ($cleanKode ?: 'vnd') . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    $destPath    = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $destPath)) {
        $file_mou = $newFileName;
    } else {
        $_SESSION['error'] = 'Gagal mengunggah file MoU ke server.';
        header("Location: vendor_add.php");
        exit();
    }
} elseif ($status_mou === 'Ada' && (!isset($_FILES['file_mou']) || $_FILES['file_mou']['error'] !== UPLOAD_ERR_OK)) {
    $_SESSION['error'] = 'Soft File MoU wajib diunggah karena Status MoU adalah Ada!';
    header("Location: vendor_add.php");
    exit();
}

$stmt = mysqli_prepare($conn, "INSERT INTO master_vendor (
    kode_vendor, nama_vendor, kategori_vendor, peruntukan_vendor, area,
    pic_ga, kontak_vendor, email, alamat, status_hukum,
    npwp, status_pkp, nama_bank, no_rekening, atas_nama_rekening,
    metode_pembayaran, lead_time, status_vendor, status_mou, file_mou,
    keterangan, review_performa, created_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

mysqli_stmt_bind_param($stmt, "sssssssssssssssssssssss",
    $kode_vendor, $nama_vendor, $kategori_vendor, $peruntukan_vendor, $area,
    $pic_ga, $kontak_vendor, $email, $alamat, $status_hukum,
    $npwp, $status_pkp, $nama_bank, $no_rekening, $atas_nama_rekening,
    $metode_pembayaran, $lead_time, $status_vendor, $status_mou, $file_mou,
    $keterangan, $review_performa, $created_by
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Vendor '$nama_vendor' ($kode_vendor) berhasil ditambahkan!";
} else {
    $_SESSION['error'] = "Gagal menyimpan vendor: " . mysqli_stmt_error($stmt);
}

header("Location: dashboard.php");
exit();

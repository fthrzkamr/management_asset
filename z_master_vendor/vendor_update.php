<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'update', 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID vendor tidak valid!';
    header("Location: dashboard.php");
    exit();
}

$kode_vendor         = trim($_POST['kode_vendor'] ?? '');
$nama_vendor         = trim($_POST['nama_vendor'] ?? '');
$status_hukum        = trim($_POST['status_hukum'] ?? '');
$kategori_vendor     = trim($_POST['kategori_vendor'] ?? '');
$peruntukan_vendor   = trim($_POST['peruntukan_vendor'] ?? '');
$area                = trim($_POST['area'] ?? '');
$status_vendor       = trim($_POST['status_vendor'] ?? 'Aktif');
$pic_ga              = trim($_POST['pic_ga'] ?? '');
$kontak_vendor       = trim($_POST['kontak_vendor'] ?? '');
$email               = trim($_POST['email'] ?? '');
$alamat              = trim($_POST['alamat'] ?? '');
$npwp                = trim($_POST['npwp'] ?? '');
$status_pkp          = trim($_POST['status_pkp'] ?? 'Non PKP');
$status_mou          = trim($_POST['status_mou'] ?? 'Tidak Ada');
$nama_bank           = trim($_POST['nama_bank'] ?? '');
$no_rekening         = trim($_POST['no_rekening'] ?? '');
$atas_nama_rekening  = trim($_POST['atas_nama_rekening'] ?? '');
$metode_pembayaran   = trim($_POST['metode_pembayaran'] ?? '');
$lead_time           = trim($_POST['lead_time'] ?? '');
$keterangan          = trim($_POST['keterangan'] ?? '');
$review_performa     = trim($_POST['review_performa'] ?? '');
$existing_file_mou   = trim($_POST['existing_file_mou'] ?? '');

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
        header("Location: vendor_edit.php?id=" . $id);
        exit();
    }
}

// Handle upload Soft File MoU
$file_mou = !empty($existing_file_mou) ? $existing_file_mou : null;
$uploadDir = __DIR__ . '/../uploads/vendor_mou/';

if (isset($_FILES['file_mou']) && $_FILES['file_mou']['error'] === UPLOAD_ERR_OK) {
    $fileTmp   = $_FILES['file_mou']['tmp_name'];
    $fileName  = $_FILES['file_mou']['name'];
    $fileSize  = $_FILES['file_mou']['size'];
    $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed   = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file MoU tidak diizinkan! Format yang didukung: PDF, DOC, DOCX, JPG, PNG.';
        header("Location: vendor_edit.php?id=" . $id);
        exit();
    }
    if ($fileSize > 10 * 1024 * 1024) {
        $_SESSION['error'] = 'Ukuran file MoU terlalu besar! Maksimal 10MB.';
        header("Location: vendor_edit.php?id=" . $id);
        exit();
    }

    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $cleanKode   = preg_replace('/[^a-zA-Z0-9_-]/', '', $kode_vendor);
    $newFileName = 'mou_' . ($cleanKode ?: 'vnd') . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    $destPath    = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $destPath)) {
        // Hapus file lama jika ada dan berbeda
        if (!empty($existing_file_mou) && file_exists($uploadDir . $existing_file_mou)) {
            @unlink($uploadDir . $existing_file_mou);
        }
        $file_mou = $newFileName;
    } else {
        $_SESSION['error'] = 'Gagal mengunggah file MoU ke server.';
        header("Location: vendor_edit.php?id=" . $id);
        exit();
    }
}

// Jika status MoU = 'Ada' tapi tidak ada file tersimpan maupun file baru
if ($status_mou === 'Ada' && empty($file_mou)) {
    $_SESSION['error'] = 'Soft File MoU wajib diunggah karena Status MoU adalah Ada!';
    header("Location: vendor_edit.php?id=" . $id);
    exit();
}

$stmt = mysqli_prepare($conn, "UPDATE master_vendor SET 
    kode_vendor = ?,
    nama_vendor = ?,
    kategori_vendor = ?,
    peruntukan_vendor = ?,
    area = ?,
    pic_ga = ?,
    kontak_vendor = ?,
    email = ?,
    alamat = ?,
    status_hukum = ?,
    npwp = ?,
    status_pkp = ?,
    nama_bank = ?,
    no_rekening = ?,
    atas_nama_rekening = ?,
    metode_pembayaran = ?,
    lead_time = ?,
    status_vendor = ?,
    status_mou = ?,
    file_mou = ?,
    keterangan = ?,
    review_performa = ?
WHERE id = ?");

mysqli_stmt_bind_param($stmt, "ssssssssssssssssssssssi",
    $kode_vendor, $nama_vendor, $kategori_vendor, $peruntukan_vendor, $area,
    $pic_ga, $kontak_vendor, $email, $alamat, $status_hukum,
    $npwp, $status_pkp, $nama_bank, $no_rekening, $atas_nama_rekening,
    $metode_pembayaran, $lead_time, $status_vendor, $status_mou, $file_mou,
    $keterangan, $review_performa, $id
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Data vendor '$nama_vendor' berhasil diperbarui!";
} else {
    $_SESSION['error'] = "Gagal memperbarui vendor: " . mysqli_stmt_error($stmt);
}

header("Location: dashboard.php");
exit();

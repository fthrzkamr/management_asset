<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'update');

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

if (empty($nama_vendor)) {
    $_SESSION['error'] = 'Nama Vendor wajib diisi!';
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
    keterangan = ?,
    review_performa = ?
WHERE id = ?");

mysqli_stmt_bind_param($stmt, "sssssssssssssssssssssi",
    $kode_vendor, $nama_vendor, $kategori_vendor, $peruntukan_vendor, $area,
    $pic_ga, $kontak_vendor, $email, $alamat, $status_hukum,
    $npwp, $status_pkp, $nama_bank, $no_rekening, $atas_nama_rekening,
    $metode_pembayaran, $lead_time, $status_vendor, $status_mou,
    $keterangan, $review_performa, $id
);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Data vendor '$nama_vendor' berhasil diperbarui!";
} else {
    $_SESSION['error'] = "Gagal memperbarui vendor: " . mysqli_stmt_error($stmt);
}

header("Location: dashboard.php");
exit();


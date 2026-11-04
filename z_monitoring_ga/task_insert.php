<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';
require_once '../dist/config/image_helper.php';
date_default_timezone_set('Asia/Jakarta');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='../login.php';</script>";
    exit;
}
$auth->requirePermission('monitoring_ga', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: task_add.php");
    exit;
}

$BAGIAN_LIST = [
    'Aset Management & Kebersihan',
    'Kendaraan',
    'Administrasi',
    'Building Management & Mechanical Electrical',
];
$STATUS_LIST = ['Belum Dikerjakan', 'Sedang Dikerjakan', 'Sudah Dikerjakan'];

// Kalau user sudah punya bagian_ga sendiri (PIC), paksa pakai itu - jangan percaya input form untuk cegah orang isi bagian lain
$myBagian = null;
$q_me = mysqli_query($conn, "SELECT bagian_ga FROM user_dfm WHERE id_user = '" . mysqli_real_escape_string($conn, $_SESSION['id_user']) . "'");
if ($q_me && $row_me = mysqli_fetch_assoc($q_me)) {
    $myBagian = $row_me['bagian_ga'] ?: null;
}

$bagian = ($myBagian && !$auth->isSuperAdmin()) ? $myBagian : ($_POST['bagian'] ?? '');
if (!in_array($bagian, $BAGIAN_LIST)) {
    echo "<script>alert('Bagian tidak valid.'); history.back();</script>";
    exit;
}

$status = in_array($_POST['status'] ?? '', $STATUS_LIST) ? $_POST['status'] : 'Belum Dikerjakan';
$deskripsi = trim($_POST['deskripsi_pekerjaan'] ?? '');
if ($deskripsi === '') {
    echo "<script>alert('Keterangan pekerjaan wajib diisi.'); history.back();</script>";
    exit;
}
$tanggal_mulai = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
if ($tanggal_selesai === '') {
    echo "<script>alert('Tanggal selesai wajib diisi.'); history.back();</script>";
    exit;
}
$dtMulai   = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
$dtSelesai = DateTime::createFromFormat('Y-m-d', $tanggal_selesai);
if (!$dtMulai || !$dtSelesai || $dtSelesai < $dtMulai) {
    echo "<script>alert('Tanggal selesai tidak boleh sebelum tanggal mulai.'); history.back();</script>";
    exit;
}
$keterangan = trim($_POST['keterangan'] ?? '');
$id_user = $_SESSION['id_user'];

$lampiran = null;
if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK && !empty($_FILES['lampiran']['name'])) {
    $uploadResult = optimizeAndSaveAssetPhoto($_FILES['lampiran'], '../uploads/ga_tasks/', 'ga_task_');
    if ($uploadResult['success']) {
        $lampiran = $uploadResult['filename'];
    } else {
        $errMsg = addslashes($uploadResult['error'] ?: 'Gagal upload lampiran.');
        echo "<script>alert('{$errMsg}'); history.back();</script>";
        exit;
    }
}

$stmt = mysqli_prepare($conn, "INSERT INTO ga_tasks (bagian, id_user, deskripsi_pekerjaan, tanggal_mulai, status, tanggal_selesai, keterangan, lampiran) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    $_SESSION['error'] = 'Tabel monitoring GA belum siap di database: ' . mysqli_error($conn);
    header("Location: dashboard.php");
    exit;
}
mysqli_stmt_bind_param($stmt, "ssssssss", $bagian, $id_user, $deskripsi, $tanggal_mulai, $status, $tanggal_selesai, $keterangan, $lampiran);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Pekerjaan berhasil ditambahkan.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan: ' . mysqli_error($conn);
}
mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit;

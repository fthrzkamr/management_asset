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
$auth->requirePermission('monitoring_ga', 'update');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$id_task = (int)($_POST['id_task'] ?? 0);
if (!$id_task) {
    header("Location: dashboard.php");
    exit;
}

// Ambil data lama + validasi scope bagian
$res = mysqli_query($conn, "SELECT * FROM ga_tasks WHERE id_task = " . (int)$id_task);
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = 'Data pekerjaan tidak ditemukan.';
    header("Location: dashboard.php");
    exit;
}
$task = mysqli_fetch_assoc($res);

$myBagian = null;
$q_me = mysqli_query($conn, "SELECT bagian_ga FROM user_dfm WHERE id_user = '" . mysqli_real_escape_string($conn, $_SESSION['id_user']) . "'");
if ($q_me && $row_me = mysqli_fetch_assoc($q_me)) {
    $myBagian = $row_me['bagian_ga'] ?: null;
}
$isScoped = !$auth->isSuperAdmin() && !empty($myBagian);
if ($isScoped && $task['bagian'] !== $myBagian) {
    $_SESSION['error'] = 'Anda tidak memiliki izin mengedit pekerjaan bagian lain.';
    header("Location: dashboard.php");
    exit;
}

$STATUS_LIST = ['Belum Dikerjakan', 'Sedang Dikerjakan', 'Sudah Dikerjakan'];
$status = in_array($_POST['status'] ?? '', $STATUS_LIST) ? $_POST['status'] : $task['status'];
$deskripsi = trim($_POST['deskripsi_pekerjaan'] ?? '');
if ($deskripsi === '') {
    echo "<script>alert('Keterangan pekerjaan wajib diisi.'); history.back();</script>";
    exit;
}
$tanggal_mulai = !empty($_POST['tanggal_mulai']) ? $_POST['tanggal_mulai'] : $task['tanggal_mulai'];
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

$lampiran = $task['lampiran'];
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

$stmt = mysqli_prepare($conn, "UPDATE ga_tasks SET deskripsi_pekerjaan=?, tanggal_mulai=?, status=?, tanggal_selesai=?, keterangan=?, lampiran=? WHERE id_task=?");
if (!$stmt) {
    $_SESSION['error'] = 'Tabel monitoring GA belum siap di database: ' . mysqli_error($conn);
    header("Location: dashboard.php");
    exit;
}
mysqli_stmt_bind_param($stmt, "ssssssi", $deskripsi, $tanggal_mulai, $status, $tanggal_selesai, $keterangan, $lampiran, $id_task);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Pekerjaan berhasil diperbarui.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan: ' . mysqli_error($conn);
}
mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit;

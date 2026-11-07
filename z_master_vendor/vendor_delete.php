<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'delete', 'dashboard.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID vendor tidak valid!';
    header("Location: dashboard.php");
    exit();
}

$stmt_get = mysqli_prepare($conn, "SELECT nama_vendor, file_mou FROM master_vendor WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_get, "i", $id);
mysqli_stmt_execute($stmt_get);
$res_get = mysqli_stmt_get_result($stmt_get);
$row = mysqli_fetch_assoc($res_get);
$nama_vendor = $row['nama_vendor'] ?? 'Vendor';
$file_mou    = $row['file_mou'] ?? null;

$stmt = mysqli_prepare($conn, "DELETE FROM master_vendor WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    if (!empty($file_mou)) {
        $filePath = __DIR__ . '/../uploads/vendor_mou/' . $file_mou;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    $_SESSION['success'] = "Vendor '$nama_vendor' berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus vendor: " . mysqli_stmt_error($stmt);
}

header("Location: dashboard.php");
exit();


<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('monitoring_ga', 'delete');

$id_task = (int)($_GET['id'] ?? 0);
if ($id_task) {
    $stmt = mysqli_prepare($conn, "DELETE FROM ga_tasks WHERE id_task = ?");
    if (!$stmt) {
        $_SESSION['error'] = 'Tabel monitoring GA belum siap di database: ' . mysqli_error($conn);
        header("Location: dashboard.php");
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $id_task);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Pekerjaan berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'Gagal menghapus: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

header("Location: dashboard.php");
exit;

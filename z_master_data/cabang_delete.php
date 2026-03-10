<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_cabang', 'delete');

$id_cabang = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id_cabang)) {
    header("Location: dashboard.php");
    exit();
}

// Delete data
$delete_sql = "DELETE FROM master_cabang WHERE id_cabang = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "s", $id_cabang);

if (mysqli_stmt_execute($delete_stmt)) {
    mysqli_stmt_close($delete_stmt);
    header("Location: dashboard.php?status=deleted&type=cabang");
    exit();
} else {
    mysqli_stmt_close($delete_stmt);
    header("Location: dashboard.php?error=deletefailed");
    exit();
}

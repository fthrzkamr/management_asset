<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_cabang', 'update');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cabang_old = trim($_POST['id_cabang_old']);
    $id_cabang = strtoupper(trim($_POST['id_cabang']));
    $nama_cabang = trim($_POST['nama_cabang']);

    // Validasi input
    if (empty($id_cabang) || empty($nama_cabang)) {
        header("Location: cabang_edit.php?id=" . urlencode($id_cabang_old) . "&error=empty");
        exit();
    }

    // Jika ID berubah, cek duplikasi
    if ($id_cabang !== $id_cabang_old) {
        $check_sql = "SELECT id_cabang FROM master_cabang WHERE id_cabang = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $id_cabang);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            mysqli_stmt_close($check_stmt);
            header("Location: cabang_edit.php?id=" . urlencode($id_cabang_old) . "&error=duplicate");
            exit();
        }
        mysqli_stmt_close($check_stmt);
    }

    // Update data
    $update_sql = "UPDATE master_cabang SET id_cabang = ?, nama_cabang = ? WHERE id_cabang = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "sss", $id_cabang, $nama_cabang, $id_cabang_old);

    if (mysqli_stmt_execute($update_stmt)) {
        mysqli_stmt_close($update_stmt);
        header("Location: dashboard.php?status=updated&type=cabang");
        exit();
    } else {
        mysqli_stmt_close($update_stmt);
        header("Location: cabang_edit.php?id=" . urlencode($id_cabang_old) . "&error=failed");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

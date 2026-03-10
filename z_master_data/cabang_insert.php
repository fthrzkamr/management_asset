<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_cabang', 'create');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cabang = strtoupper(trim($_POST['id_cabang']));
    $nama_cabang = trim($_POST['nama_cabang']);

    // Validasi input
    if (empty($id_cabang) || empty($nama_cabang)) {
        header("Location: cabang_add.php?error=empty");
        exit();
    }

    // Cek apakah ID sudah ada
    $check_sql = "SELECT id_cabang FROM master_cabang WHERE id_cabang = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $id_cabang);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_close($check_stmt);
        header("Location: cabang_add.php?error=duplicate");
        exit();
    }
    mysqli_stmt_close($check_stmt);

    // Insert data
    $insert_sql = "INSERT INTO master_cabang (id_cabang, nama_cabang) VALUES (?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "ss", $id_cabang, $nama_cabang);

    if (mysqli_stmt_execute($insert_stmt)) {
        mysqli_stmt_close($insert_stmt);
        header("Location: dashboard.php?status=added&type=cabang");
        exit();
    } else {
        mysqli_stmt_close($insert_stmt);
        header("Location: cabang_add.php?error=failed");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

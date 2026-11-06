<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

header('Content-Type: application/json');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (!$auth->hasPermission('master_vendor', 'read')) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID vendor tidak valid']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM master_vendor WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($res)) {
    echo json_encode(['status' => 'success', 'data' => $row]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Vendor tidak ditemukan']);
}


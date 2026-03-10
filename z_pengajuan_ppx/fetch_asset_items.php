<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\fetch_asset_items.php

// Start output buffering to catch any unwanted output
ob_start();



// Clear any buffered output before sending JSON
ob_end_clean();

header('Content-Type: application/json');

// Suppress any PHP warnings/errors from being output
error_reporting(0);
ini_set('display_errors', 0);

include('../dist/config/config.php');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$no_pengajuan = $_GET['no_pengajuan'] ?? '';

if (empty($no_pengajuan)) {
    echo json_encode(['success' => false, 'message' => 'No pengajuan required']);
    exit();
}

// Check if it's a renovasi request (starts with 'renovasi_')
if (strpos($no_pengajuan, 'renovasi_') === 0) {
    // For renovasi, we don't have items table yet, return empty
    echo json_encode(['success' => true, 'items' => []]);
    exit();
}

// Query untuk ambil detail items dari asset_request_detail
$query = "SELECT 
    ard.no_akun,
    ard.rincian,
    ard.jumlah,
    ard.satuan,
    ard.harga_satuan,
    ard.total_harga,
    ard.deskripsi
FROM asset_request_detail ard
WHERE ard.no_pengajuan = ?
ORDER BY ard.id_detail ASC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $no_pengajuan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'no_akun' => $row['no_akun'],
        'rincian' => $row['rincian'],
        'jumlah' => $row['jumlah'],
        'satuan' => $row['satuan'],
        'harga_satuan' => $row['harga_satuan'],
        'total_harga' => $row['total_harga'],
        'deskripsi' => $row['deskripsi']
    ];
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'items' => $items,
    'count' => count($items)
]);
?>

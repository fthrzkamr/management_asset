<?php

require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Silakan login kembali.'
    ]);
    exit;
}

// Check if Finance role
$username = $_SESSION['username'];
$user_query = "SELECT role FROM akun WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

if ($user_data['role'] !== 'Finance') {
    echo json_encode([
        'success' => false,
        'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini.'
    ]);
    exit;
}

// Validate input
if (!isset($_POST['no_ppx']) || empty($_POST['no_ppx'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak lengkap.'
    ]);
    exit;
}

$no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx']);

// Check if PPX exists and status is 'Selesai Realisasi'
$check_query = "SELECT status FROM ppx_request WHERE no_ppx = '$no_ppx'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'PPX tidak ditemukan.'
    ]);
    exit;
}

$ppx_data = mysqli_fetch_assoc($check_result);

if ($ppx_data['status'] !== 'Selesai Realisasi') {
    echo json_encode([
        'success' => false,
        'message' => 'Status PPX tidak valid untuk proses reimbursement.'
    ]);
    exit;
}

// Check if there are realisasi with reimbursement needs
$realisasi_query = "SELECT id_realisasi, status_selisih, confirm_pakai_uang_pribadi, total_realisasi, uang_kembali 
                    FROM ppx_realisasi 
                    WHERE no_ppx = '$no_ppx' 
                    AND status_selisih = 'kurang' 
                    AND confirm_pakai_uang_pribadi = 1
                    AND (is_reimbursed IS NULL OR is_reimbursed = 0)";

$realisasi_result = mysqli_query($conn, $realisasi_query);

if (mysqli_num_rows($realisasi_result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tidak ada reimbursement yang perlu diproses.'
    ]);
    exit;
}

// Mark all reimbursement as processed
$total_reimbursement = 0;
$realisasi_ids = [];

while ($realisasi = mysqli_fetch_assoc($realisasi_result)) {
    $realisasi_ids[] = $realisasi['id_realisasi'];
    $total_reimbursement += abs($realisasi['uang_kembali']); // uang_kembali is negative for 'kurang'
}

// Update ppx_realisasi - mark as reimbursed
$ids_string = implode(',', $realisasi_ids);
$update_query = "UPDATE ppx_realisasi 
                 SET is_reimbursed = 1,
                     tanggal_reimbursed = NOW(),
                     user_reimbursed = '$username'
                 WHERE id_realisasi IN ($ids_string)";

if (mysqli_query($conn, $update_query)) {
    // Log the reimbursement action
    $log_query = "INSERT INTO ppx_log (no_ppx, username, action, keterangan, created_at) 
                  VALUES ('$no_ppx', '$username', 'Reimbursement', 
                          'Reimbursement sebesar Rp " . number_format($total_reimbursement, 0, ',', '.') . " telah diproses oleh Finance', 
                          NOW())";
    mysqli_query($conn, $log_query);

    echo json_encode([
        'success' => true,
        'message' => 'Reimbursement berhasil ditandai selesai. Silakan lanjutkan dengan approval final.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan data reimbursement: ' . mysqli_error($conn)
    ]);
}

mysqli_close($conn);
?>

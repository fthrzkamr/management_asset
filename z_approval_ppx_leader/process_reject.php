<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_leader\process_reject.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
if (!$auth->canApprove()) {
    $auth->requirePermission('approval_ppx_leader', 'approve');
}

// Validasi input
if (!isset($_POST['no_pengajuan'])) {
    $_SESSION['error'] = 'Data tidak lengkap!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = mysqli_real_escape_string($conn, $_POST['no_pengajuan']);
$user_id = $_SESSION['id_user'];

// Get data pengajuan untuk validasi
$query = "SELECT pr.*, d.nama_divisi 
          FROM ppx_request pr
          LEFT JOIN divisi d ON pr.divisi = d.id_divisi
          WHERE pr.no_ppx = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $no_pengajuan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengajuan = mysqli_fetch_assoc($result);

if (!$pengajuan) {
    $_SESSION['error'] = 'Pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$current_status = $pengajuan['status'];
$divisi = strtolower($pengajuan['nama_divisi']);

// Validasi apakah user berhak menolak berdasarkan status dan divisi
// (hak ditentukan dari PERMISSION Administrator, bukan nama role)
$can_reject = false;

if ($auth->hasPermission('approval_ppx_leader', 'approve') || $auth->canApprove()) {
    if (in_array($current_status, ['Leader', 'Pending'])) {
        $can_reject = true;
    }
}
if ($auth->hasPermission('approval_ppx_kacab', 'approve')) {
    // Kacab bisa reject status Leader untuk divisi non-operasional, non-IT/GA/HR, non-finance
    if ($current_status == 'Leader' && 
        !preg_match('/(gudang|sales|bisnis|kurir|it|ga|hr|finance|tax)/i', $divisi)) {
        $can_reject = true;
    }
}
if ($auth->hasPermission('approval_ppx_operasional', 'approve')) {
    // Manager Operasional bisa reject untuk divisi operasional
    if (preg_match('/(gudang|sales|bisnis|kurir)/i', $divisi) && 
        in_array($current_status, ['Leader', 'Operasional'])) {
        $can_reject = true;
    }
}
if ($auth->hasPermission('approval_ppx_hr', 'approve')) {
    // HR bisa reject untuk divisi IT/GA/HR
    if (preg_match('/(it|ga|hr)/i', $divisi) && 
        in_array($current_status, ['Leader', 'HR'])) {
        $can_reject = true;
    }
}
if ($auth->hasPermission('approval_ppx_manager', 'approve')) {
    // Manager Finance bisa reject untuk divisi finance
    if (preg_match('/(finance|tax)/i', $divisi) && 
        in_array($current_status, ['Leader', 'Manager', 'Finance'])) {
        $can_reject = true;
    }
}

// Super Admin bisa reject semua
if ($auth->isSuperAdmin()) {
    $can_reject = true;
}

if (!$can_reject) {
    $_SESSION['error'] = 'Anda tidak memiliki hak untuk menolak pengajuan ini!';
    header("Location: dashboard.php");
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Update status ke 'Ditolak'
    $update_query = "UPDATE ppx_request 
                     SET status = 'Ditolak'
                     WHERE no_ppx = ?";
    
    $stmt_update = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt_update, "s", $no_pengajuan);
    
    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception("Gagal update status: " . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = 'Pengajuan PPX berhasil ditolak. Pengaju harus mengajukan ulang.';
    header("Location: dashboard.php");
    exit();
    
} catch (Exception $e) {
    // Rollback jika ada error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

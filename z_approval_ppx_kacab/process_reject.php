<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_kacab\process_reject.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - hanya Kacab
if (!$auth->isKacab()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Validasi input
if (!isset($_POST['no_pengajuan'])) {
    $_SESSION['error'] = 'Data tidak lengkap!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = mysqli_real_escape_string($conn, $_POST['no_pengajuan']);
$user_id = $_SESSION['id_user'];

// Validasi bahwa PPX status = 'Kacab' dan cabang sesuai
$user_query = "SELECT id_cabang FROM user_dfm WHERE id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_cabang = $user_data['id_cabang'] ?? null;

$check_query = "SELECT no_ppx, status, cabang_user 
                FROM ppx_request 
                WHERE no_ppx = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "s", $no_pengajuan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ppx_data = mysqli_fetch_assoc($result);

if (!$ppx_data) {
    $_SESSION['error'] = 'PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

if ($ppx_data['status'] != 'Kacab') {
    $_SESSION['error'] = 'Status PPX tidak valid untuk rejection Kacab!';
    header("Location: dashboard.php");
    exit();
}

if ($ppx_data['cabang_user'] != $user_cabang) {
    $_SESSION['error'] = 'PPX ini bukan untuk cabang Anda!';
    header("Location: dashboard.php");
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Update status ke 'Revisi' agar user bisa edit dan submit ulang
    $update_query = "UPDATE ppx_request 
                     SET status = 'Revisi',
                         updated_at = NOW(),
                         updated_by = ?
                     WHERE no_ppx = ?";
    
    $stmt_update = mysqli_prepare($conn, $update_query);
    $nama_user = $_SESSION['nama_user'] ?? '';
    mysqli_stmt_bind_param($stmt_update, "ss", $nama_user, $no_pengajuan);
    
    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception("Gagal update status: " . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = 'PPX berhasil ditolak dan dikembalikan untuk revisi.';
    header("Location: dashboard.php");
    exit();
    
} catch (Exception $e) {
    // Rollback jika ada error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}
?>

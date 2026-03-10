<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_kacab\process_approve.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || !$auth->isKacab()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Terima dari POST
$no_pengajuan = $_POST['no_pengajuan'] ?? null;
$user_id = (int)($_SESSION['id_user'] ?? 0);
$nama_user = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Validasi bahwa PPX status = 'Kacab' dan cabang sesuai
$user_query = "SELECT u.id_cabang, mc.nama_cabang 
               FROM user_dfm u 
               LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_cabang_nama = $user_data['nama_cabang'] ?? null;

if (!$user_cabang_nama) {
    $_SESSION['error'] = 'Data cabang user tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$check_query = "SELECT * 
                FROM ppx_request 
                WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_pengajuan) . "'";
$check_result = mysqli_query($conn, $check_query);
$ppx_data = mysqli_fetch_assoc($check_result);

if (!$ppx_data) {
    $_SESSION['error'] = 'PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

if ($ppx_data['status'] != 'Kacab') {
    $_SESSION['error'] = 'Status PPX tidak valid untuk approval Kacab!';
    header("Location: dashboard.php");
    exit();
}

if ($ppx_data['cabang'] != $user_cabang_nama) {
    $_SESSION['error'] = 'PPX ini bukan untuk cabang Anda! (PPX: ' . $ppx_data['cabang'] . ', User: ' . $user_cabang_nama . ')';
    header("Location: dashboard.php");
    exit();
}

// Dapatkan final_status untuk PPX ini
$rt_val = isset($ppx_data['request_type']) ? mysqli_real_escape_string($conn, $ppx_data['request_type']) : 'Regular';
$cab_val = mysqli_real_escape_string($conn, $ppx_data['cabang']);
$div_val = (int)$ppx_data['divisi'];

// Default next status 
$new_status = 'Selesai';
if (strtolower(trim($ppx_data['cabang'])) === 'cibinong') {
    $new_status = 'Finance';
}

$q_route = "SELECT final_status FROM ppx_approval_routing 
            WHERE divisi_id = $div_val 
            AND (cabang IS NULL OR cabang = '' OR cabang = '$cab_val')
            AND is_active = 1
            ORDER BY (cabang = '$cab_val') DESC, 
                     (request_type = '$rt_val') DESC LIMIT 1";
$r_route = mysqli_query($conn, $q_route);
if ($r_route && $row = mysqli_fetch_assoc($r_route)) {
    if (!empty($row['final_status'])) {
        if ($row['final_status'] === 'Kacab') {
            $new_status = 'Selesai';
        } else {
            $new_status = $row['final_status']; // langsung dilempar ke 'final_status' atau target selanjutnya
        }
    }
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    $update_query = "UPDATE ppx_request 
                     SET status = ?,
                         approved_by_kacab = ?,
                         approved_at_kacab = NOW(),
                         updated_at = NOW(),
                         updated_by = ?
                     WHERE no_ppx = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "siss", $new_status, $user_id, $nama_user, $no_pengajuan);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Gagal memproses approval: ' . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    if ($new_status === 'Finance') {
        $_SESSION['success'] = 'PPX berhasil disetujui. Menunggu approval Finance.';
    } else {
        $_SESSION['success'] = 'PPX berhasil disetujui dan diselesaikan.';
    }
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

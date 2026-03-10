<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_distribution.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Session expired. Silakan login kembali.';
    header('Location: ../login.php');
    exit();
}

// Hanya yang punya approve permission yang bisa kelola distribusi
if (!$auth->hasPermission('pengajuan_asset', 'approve')) {
    $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengelola pendistribusian asset!';
    header('Location: dashboard.php');
    exit();
}

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method!';
    header('Location: dashboard.php');
    exit();
}

try {
    // Ambil data dari POST
    $no_pengajuan = $_POST['no_pengajuan'] ?? '';
    $status_pendistribusian = $_POST['status_pendistribusian'] ?? '';
    
    // Validasi input
    if (empty($no_pengajuan)) {
        throw new Exception('Nomor pengajuan tidak boleh kosong!');
    }
    
    if (empty($status_pendistribusian)) {
        throw new Exception('Status pendistribusian tidak boleh kosong!');
    }
    
    // Validasi status pendistribusian
    $valid_statuses = ['Belum di order', 'Sudah diorder', 'Barang sampai', 'Sudah didistribusikan'];
    if (!in_array($status_pendistribusian, $valid_statuses)) {
        throw new Exception('Status pendistribusian tidak valid!');
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Cek apakah pengajuan exists dan sudah disetujui, dan termasuk sub_kategori yang sesuai
    $check_query = "SELECT ar.*, ka.sub_kategori
                   FROM asset_request ar
                   LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
                   WHERE ar.no_pengajuan = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception('Pengajuan asset tidak ditemukan!');
    }
    
    $asset_data = mysqli_fetch_assoc($check_result);
    
    // Cek apakah status sudah disetujui
    if ($asset_data['status'] !== 'Disetujui') {
        throw new Exception('Hanya pengajuan yang sudah disetujui yang dapat dikelola distribusinya!');
    }
    
    // Jika user adalah GA, cek sub_kategori
    if ($auth->isGa() && $asset_data['sub_kategori'] === 'IT') {
        throw new Exception('GA hanya dapat mengelola distribusi untuk asset dengan sub kategori GA!');
    }
    
    // Jika user adalah IT, cek sub_kategori
    if ($auth->isIT() && $asset_data['sub_kategori'] !== 'IT') {
        throw new Exception('IT hanya dapat mengelola distribusi untuk asset dengan sub kategori IT!');
    }
    
    // Update status pendistribusian
    $update_query = "UPDATE asset_request 
                    SET status_pendistribusian = ?, 
                        updated_at = NOW() 
                    WHERE no_pengajuan = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "ss", $status_pendistribusian, $no_pengajuan);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Gagal memperbarui status pendistribusian: ' . mysqli_stmt_error($update_stmt));
    }
    
    // Cek affected rows
    $affected = mysqli_stmt_affected_rows($update_stmt);
    if ($affected === 0) {
        throw new Exception('Tidak ada perubahan data!');
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Set success message dengan info role
    $role_info = '';
    if ($auth->isIT()) {
        $role_info = ' oleh IT Support';
    } elseif ($auth->isGa()) {
        $role_info = ' oleh GA';
    }
    
    $_SESSION['success'] = "Status pendistribusian untuk pengajuan {$no_pengajuan} berhasil diperbarui menjadi '{$status_pendistribusian}'{$role_info}!";
    header('Location: dashboard.php');
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    // Set error message
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
    
} finally {
    // Close statements
    if (isset($check_stmt)) mysqli_stmt_close($check_stmt);
    if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
    
    // Close connection
    if (isset($conn)) mysqli_close($conn);
}
?>
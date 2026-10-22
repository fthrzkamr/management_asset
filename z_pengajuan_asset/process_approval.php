<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_approval.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Session expired. Silakan login kembali.';
    header('Location: ../login.php');
    exit();
}

$isGlobalApprover = $auth->hasPermission('pengajuan_asset', 'approve');
$canApproveIT = $auth->isIT() || $auth->hasPermission('approval_asset_it', 'approve');
$canApproveGA = $auth->isGa() || $auth->hasPermission('approval_asset_ga', 'approve');

if (!$isGlobalApprover && !$canApproveIT && !$canApproveGA) {
    $_SESSION['error'] = 'Anda tidak memiliki izin untuk approve pengajuan asset!';
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
    $decision = $_POST['decision'] ?? '';  
    $catatan_approval = trim($_POST['catatan'] ?? '');
    $sub_kategori = $_POST['sub_kategori'] ?? '';
    
    // Validasi input
    if (empty($no_pengajuan)) {
        throw new Exception('Nomor pengajuan tidak boleh kosong!');
    }
    
    if (empty($decision)) {
        throw new Exception('Keputusan approval tidak boleh kosong!');
    }
    
    if (!in_array($decision, ['approve', 'reject'])) {
        throw new Exception('Keputusan tidak valid! Harus approve atau reject.');
    }
    
    // Jika reject, catatan wajib diisi
    if ($decision === 'reject' && empty($catatan_approval)) {
        throw new Exception('Alasan penolakan wajib diisi!');
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Cek apakah pengajuan exists
    $check_query = "SELECT ar.*, ka.kategori_asset as nama_kategori, ka.sub_kategori,
                           u.nama_user as nama_pengaju
                   FROM asset_request ar 
                   LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
                   LEFT JOIN user_dfm u ON ar.user_id = u.id_user 
                   WHERE ar.no_pengajuan = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception('Pengajuan asset tidak ditemukan!');
    }
    
    $asset_data = mysqli_fetch_assoc($check_result);
    $approval_level = $asset_data['approval_level'];
    $cabang_pengaju = $asset_data['cabang_pengaju'];
    $approved_by_ga = $asset_data['approved_by_ga'];
    
    // Cek status current
    if ($asset_data['status'] !== 'Pending') {
        throw new Exception('Pengajuan sudah diproses sebelumnya! Status saat ini: ' . $asset_data['status']);
    }
    
    // Validasi berdasarkan sub kategori (sesuai permission Administrator)
    if (!$isGlobalApprover) {
        if ($canApproveIT && !$canApproveGA && $asset_data['sub_kategori'] != 'IT') {
            throw new Exception('Anda hanya dapat menyetujui pengajuan asset dengan sub kategori IT!');
        } elseif ($canApproveGA && !$canApproveIT && $asset_data['sub_kategori'] != 'GA') {
            throw new Exception('Anda hanya dapat menyetujui pengajuan asset dengan sub kategori GA!');
        }
    }
    
    // Set status dan update field berdasarkan decision
    if ($decision === 'approve') {
        $approval_date = date('Y-m-d H:i:s');
        $new_status = 'Disetujui';
        $status_message = 'disetujui';
        
        $update_query = "UPDATE asset_request 
                        SET status = ?, 
                            approved_by = ?, 
                            approved_at = ?, 
                            catatan_approval = ?, 
                            updated_at = NOW() 
                        WHERE no_pengajuan = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "sisss", 
            $new_status, 
            $_SESSION['id_user'], 
            $approval_date, 
            $catatan_approval, 
            $no_pengajuan
        );
    } else { // reject
        $new_status = 'Ditolak';
        $status_message = 'ditolak';
        $rejection_date = date('Y-m-d H:i:s');
        
        // Update query untuk reject
        $update_query = "UPDATE asset_request 
                        SET status = ?, 
                            rejected_by = ?, 
                            rejected_at = ?, 
                            catatan_approval = ?, 
                            updated_at = NOW() 
                        WHERE no_pengajuan = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "sisss", 
            $new_status, 
            $_SESSION['id_user'], 
            $rejection_date, 
            $catatan_approval, 
            $no_pengajuan
        );
    }
    
    // Execute update
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Gagal memproses approval: ' . mysqli_stmt_error($update_stmt));
    }
    
    // Cek affected rows
    $affected = mysqli_stmt_affected_rows($update_stmt);
    if ($affected === 0) {
        throw new Exception('Tidak ada data yang diupdate! Pastikan nomor pengajuan benar.');
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Set success message dan redirect
    $role_info = '';
    if ($auth->isIT()) {
        $role_info = ' oleh IT Support';
    } elseif ($auth->isGa()) {
        $role_info = ' oleh GA';
    } elseif ($auth->isHr()) {
        $role_info = ' oleh HR';
    }
    
    $_SESSION['success'] = "Pengajuan asset {$no_pengajuan} berhasil {$status_message}{$role_info}!";
    header('Location: dashboard.php');
    exit();
    
} catch (Exception $e) {
    // Rollback transaction jika terjadi error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    // Set error message dan redirect
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
<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\process_approval.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Session expired. Silakan login kembali.';
    header('Location: ../login.php');
    exit();
}

// Cek permission approve
if (!$auth->canApproveRenovasi()) {
    $_SESSION['error'] = 'Anda tidak memiliki izin untuk approve pengajuan renovasi!';
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
    
    // Cek apakah ada field 'decision' atau 'action'
    $decision = $_POST['decision'] ?? $_POST['action'] ?? '';
    $catatan_approval = trim($_POST['catatan'] ?? $_POST['catatan_approval'] ?? '');
    
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
    $check_query = "SELECT * FROM renovation_request WHERE no_pengajuan = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception('Pengajuan renovasi tidak ditemukan!');
    }
    
    $renovation_data = mysqli_fetch_assoc($check_result);
    
    // Cek status current
    if ($renovation_data['status'] !== 'Pending') {
        throw new Exception('Pengajuan sudah diproses sebelumnya! Status saat ini: ' . $renovation_data['status']);
    }
    
    // Set status dan tanggal berdasarkan decision
    // PERBAIKAN: Gunakan nilai ENUM yang sesuai database
    if ($decision === 'approve') {
        $new_status = 'Disetujui';  // Sesuai ENUM database
        $status_message = 'disetujui';
        $approval_date = date('Y-m-d H:i:s');
        
        // Update query untuk approve
        $update_query = "UPDATE renovation_request 
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
        $new_status = 'Ditolak';  // Sesuai ENUM database
        $status_message = 'ditolak';
        $rejection_date = date('Y-m-d H:i:s');
        
        // Update query untuk reject
        $update_query = "UPDATE renovation_request 
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
    $_SESSION['success'] = "Pengajuan renovasi {$no_pengajuan} berhasil {$status_message}!";
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
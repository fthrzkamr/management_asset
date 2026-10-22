<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_operasional\process_approve.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn() || !$auth->canApprove()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header('Location: dashboard.php');
    exit();
}

$no_pengajuan = $_GET['no_ppx'] ?? $_POST['no_pengajuan'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? 'approve';
$nama_user = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header('Location: dashboard.php');
    exit();
}

// Ambil status dan divisi
$query_status = "SELECT status, divisi FROM ppx_request WHERE no_ppx = ?";
$stmt = mysqli_prepare($conn, $query_status);
mysqli_stmt_bind_param($stmt, 's', $no_pengajuan);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $current_status, $divisi_ppx);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Logic: Manager Operasional approves -> set Selesai for divisi 2,4,17 when status=Operasional
if ($action === 'approve') {
    if (trim($current_status) === 'Selesai Realisasi') {
        $status = 'Selesai';
    } elseif (in_array($divisi_ppx, [2,4,17]) && trim($current_status) === 'Operasional') {
        $status = 'Selesai';
    } else {
        $status = 'Uang Muka';
    }
} else {
    $status = 'Revisi';
    
    // Kembalikan budget jika reject
    $check_budget = "SELECT id_budget_item, nominal_ppx FROM ppx_request WHERE no_ppx = ?";
    $stmt_budget = mysqli_prepare($conn, $check_budget);
    mysqli_stmt_bind_param($stmt_budget, 's', $no_pengajuan);
    mysqli_stmt_execute($stmt_budget);
    $result_budget = mysqli_stmt_get_result($stmt_budget);
    $budget_data = mysqli_fetch_assoc($result_budget);
    mysqli_stmt_close($stmt_budget);
    
    if (!empty($budget_data['id_budget_item']) && !empty($budget_data['nominal_ppx'])) {
        $restore_budget = "UPDATE ppx_monthly_budget_item 
                         SET remaining = remaining + " . (float)$budget_data['nominal_ppx'] . ",
                             updated_at = NOW()
                         WHERE id_budget_item = " . (int)$budget_data['id_budget_item'];
        mysqli_query($conn, $restore_budget);
    }
}

$update = "UPDATE ppx_request SET status = ?, updated_at = NOW(), updated_by = ? WHERE no_ppx = ?";
$stmt2 = mysqli_prepare($conn, $update);
mysqli_stmt_bind_param($stmt2, 'sss', $status, $nama_user, $no_pengajuan);
if (mysqli_stmt_execute($stmt2)) {
    $_SESSION['success'] = ($status === 'Selesai') ? 'PPX berhasil diapprove dan status diubah menjadi Selesai!' : 'Berhasil diproses.';
} else {
    $_SESSION['error'] = 'Gagal memproses approval: ' . mysqli_stmt_error($stmt2);
}
mysqli_stmt_close($stmt2);

header('Location: dashboard.php');
exit();
?>
<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || !$auth->canApprove()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = $_POST['no_pengajuan'] ?? $_GET['no_ppx'] ?? null;
$user_id = (int)($_SESSION['id_user'] ?? 0);

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data PPX untuk cek budget
$check_query = "SELECT id_budget_item, nominal_ppx FROM ppx_request WHERE no_ppx = ?";
$stmt_check = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt_check, 's', $no_pengajuan);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$ppx_data = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

// KEMBALIKAN BUDGET jika menggunakan Monthly Budget
if (!empty($ppx_data['id_budget_item']) && !empty($ppx_data['nominal_ppx'])) {
    $restore_budget = "UPDATE ppx_monthly_budget_item 
                     SET remaining = remaining + " . (float)$ppx_data['nominal_ppx'] . ",
                         updated_at = NOW()
                     WHERE id_budget_item = " . (int)$ppx_data['id_budget_item'];
    
    if (mysqli_query($conn, $restore_budget)) {
        error_log("Budget restored on revisi: +" . $ppx_data['nominal_ppx'] . " to budget_item " . $ppx_data['id_budget_item']);
    }
}

// Update status menjadi Revisi dan set updated_at, updated_by
$sql = "UPDATE ppx_request SET status = ?, updated_at = NOW(), updated_by = ? WHERE no_ppx = ?";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal menyiapkan query: ' . mysqli_error($conn);
    header("Location: dashboard.php");
    exit();
}
$status = 'Revisi';
mysqli_stmt_bind_param($stmt, 'sis', $status, $user_id, $no_pengajuan);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Pengajuan berhasil dikembalikan ke status Revisi.';
} else {
    $_SESSION['error'] = 'Gagal mengubah status: ' . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit();
?>
<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - Approval Realisasi (Finance)
$auth->requirePermission('approval_budget_finance', 'approve');

$current_year = (int)date('Y');
$current_month = (int)date('n');

// Lakukan perubahan batch status menjadi 'Closed'
$query = "UPDATE ppx_monthly_budget 
          SET status = 'Closed' 
          WHERE status IN ('Active', 'Finance (Realisasi)') 
          AND (period_year < $current_year OR (period_year = $current_year AND period_month < $current_month))";

if (mysqli_query($conn, $query)) {
    $affected_rows = mysqli_affected_rows($conn);
    $_SESSION['success'] = "Berhasil Melakukan Tutup Buku. Sebanyak $affected_rows anggaran dari bulan lalu telah di-Closed dan dikunci.";
} else {
    $_SESSION['error'] = "Gagal melakukan proses Tutup Buku: " . mysqli_error($conn);
}

header("Location: dashboard.php");
exit();

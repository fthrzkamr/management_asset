<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }

$id_budget = intval($_GET['id'] ?? 0);
if ($id_budget <= 0) { header("Location: dashboard.php"); exit(); }

$user_id = $_SESSION['id_user'];

// Pastikan budget milik user ini dan statusnya 'Revisi'
$stmt = mysqli_prepare($conn,
    "SELECT id_budget, budget_name, notes FROM ppx_monthly_budget
     WHERE id_budget = ? AND created_by = ? AND status = 'Revisi' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $id_budget, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Anggaran tidak ditemukan atau tidak bisa diajukan ulang.';
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Ubah status langsung ke 'Manager Finance' (bypass Kacab)
$stmt_update = mysqli_prepare($conn,
    "UPDATE ppx_monthly_budget SET status = 'Manager Finance', approved_by = NULL, approved_at = NULL
     WHERE id_budget = ? AND status = 'Revisi'");
mysqli_stmt_bind_param($stmt_update, 'i', $id_budget);

if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
    $_SESSION['success'] = 'Anggaran "' . htmlspecialchars($budget['budget_name']) . '" berhasil diajukan ulang ke Manager Finance.';
} else {
    $_SESSION['error'] = 'Gagal mengajukan ulang: ' . mysqli_error($conn);
}

header("Location: dashboard.php");
exit();
?>

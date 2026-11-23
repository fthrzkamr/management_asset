<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_budget', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$rv_month = (int)($_POST['rv_month'] ?? 0);
$rv_year  = (int)($_POST['rv_year'] ?? 0);
$base_redirect = "dashboard.php?rv_month=$rv_month&rv_year=$rv_year";

$user_id = (int)$_SESSION['id_user'];
$saved = 0;
$errors = [];

foreach ($_POST as $key => $val) {
    if (!preg_match('/^decision_(\d+)$/', $key, $m)) continue;
    $id_budget = (int)$m[1];
    $decision  = ($val === 'Dikoreksi') ? 'Dikoreksi' : 'Sesuai';

    // Ambil nominal pengajuan asli untuk validasi & fallback kalau "Sesuai"
    $q = mysqli_query($conn, "SELECT total_allocation FROM ppx_monthly_budget WHERE id_budget = $id_budget LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        $errors[] = "Budget ID $id_budget tidak ditemukan.";
        continue;
    }
    $row = mysqli_fetch_assoc($q);
    $total_allocation = (float)$row['total_allocation'];

    if ($decision === 'Dikoreksi') {
        $raw = $_POST['approved_' . $id_budget] ?? '';
        $approved = (float)preg_replace('/[^\d]/', '', $raw);
        if ($approved <= 0) {
            $errors[] = "Nominal disetujui untuk budget ID $id_budget harus diisi dan lebih dari 0.";
            continue;
        }
    } else {
        $approved = $total_allocation;
    }

    $stmt = mysqli_prepare($conn, "UPDATE ppx_monthly_budget SET
        approved_allocation = ?, review_decision = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id_budget = ?");
    mysqli_stmt_bind_param($stmt, 'dsii', $approved, $decision, $user_id, $id_budget);
    if (mysqli_stmt_execute($stmt)) {
        $saved++;
    } else {
        $errors[] = "Gagal simpan review untuk budget ID $id_budget: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

if (!empty($errors)) {
    header("Location: $base_redirect&error=" . urlencode(implode(' ', $errors)));
    exit();
}

header("Location: $base_redirect&success=" . urlencode("$saved pengajuan berhasil direview."));
exit();

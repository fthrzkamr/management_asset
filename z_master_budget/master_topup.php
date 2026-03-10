<?php
/**
 * Master Budget Top-Up
 * Menambah dana ke master budget yang sudah ada
 */

// Start session jika belum

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/MasterBudgetService.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Cek permission
$auth->requirePermission('master_budget', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

// Get POST data
$master_id = mysqli_real_escape_string($conn, trim($_POST['master_id'] ?? ''));
$amount = floatval($_POST['amount'] ?? 0);
$note = mysqli_real_escape_string($conn, trim($_POST['note'] ?? ''));
$created_by = $_SESSION['nama_user'] ?? $_SESSION['username'];

// Validasi
if (empty($master_id)) {
    header('Location: dashboard.php?error=' . urlencode('Master ID tidak boleh kosong'));
    exit();
}

if ($amount <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Jumlah top-up harus lebih besar dari 0'));
    exit();
}

// Initialize service
$budgetService = new MasterBudgetService($conn);

// Top-up master budget
$description = !empty($note) ? $note : 'Top-up dana';
$result = $budgetService->topupMasterBudget(
    $master_id,
    $amount,
    $description,
    $created_by,
    'TOPUP-' . date('YmdHis')
);

if ($result['success']) {
    header('Location: dashboard.php?success=' . urlencode($result['message']));
} else {
    header('Location: dashboard.php?error=' . urlencode($result['message']));
}
exit();

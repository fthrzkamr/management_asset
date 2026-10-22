<?php
/**
 * Get Last Remaining Budget
 * Ajax endpoint untuk mendapatkan sisa budget terakhir
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Cek role
if (!$auth->isFinance() && !$auth->isManagerFinance() && !$auth->isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get last master budget remaining
$query = "SELECT 
    mb.master_id,
    mb.balance,
    mb.description,
    mb.created_at
FROM master_budget mb
ORDER BY mb.created_at DESC
LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $budget = mysqli_fetch_assoc($result);
    $remaining = floatval($budget['balance']);
    
    echo json_encode([
        'success' => true,
        'remaining' => $remaining,
        'remaining_formatted' => number_format($remaining, 0, ',', '.'),
        'description' => $budget['description'],
        'created_at' => $budget['created_at']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'remaining' => 0,
        'remaining_formatted' => '0',
        'description' => null,
        'created_at' => null
    ]);
}

<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Cek permission - hanya Finance dan Super Admin
if (!$auth->isFinance() && !$auth->isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit();
}

$budget_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($budget_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid budget ID']);
    exit();
}

// Get budget info - izinkan semua status yang relevan untuk realisasi
$budget_query = "SELECT 
    mb.id_budget,
    mb.budget_name,
    mb.period_year,
    mb.period_month,
    mb.total_allocation,
    mb.total_remaining,
    mb.status
FROM ppx_monthly_budget mb
WHERE mb.id_budget = ? AND mb.status IN ('Pending', 'Active', 'Approved', 'Finance (Realisasi)')";

$stmt = mysqli_prepare($conn, $budget_query);
mysqli_stmt_bind_param($stmt, 'i', $budget_id);
mysqli_stmt_execute($stmt);
$budget_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($budget_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Budget tidak ditemukan atau tidak dalam status yang dapat direalisasi!']);
    exit();
}

$budget = mysqli_fetch_assoc($budget_result);

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Get budget items
$items_query = "SELECT 
    mbi.id_budget_item,
    mbi.id_category,
    mbi.allocation,
    mbi.used_amount,
    mbi.remaining,
    pbc.category_name,
    pbc.category_code
FROM ppx_monthly_budget_item mbi
LEFT JOIN ppx_budget_category pbc ON mbi.id_category = pbc.id_category
WHERE mbi.id_budget = ?
ORDER BY pbc.category_name";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $budget_id);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = [
        'id_budget_item' => $item['id_budget_item'],
        'id_category' => $item['id_category'],
        'category_name' => $item['category_name'],
        'category_code' => $item['category_code'],
        'allocation' => floatval($item['allocation']),
        'used_amount' => floatval($item['used_amount']),
        'remaining' => floatval($item['remaining'])
    ];
}

echo json_encode([
    'success' => true,
    'budget_name' => $budget['budget_name'],
    'period' => $month_names[$budget['period_month']] . ' ' . $budget['period_year'],
    'total_allocation' => floatval($budget['total_allocation']),
    'total_remaining' => floatval($budget['total_remaining']),
    'items' => $items
]);

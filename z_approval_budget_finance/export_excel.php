<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Check auth - allow with fallback
$auth = new AuthHelper($conn);
$is_authorized = false;

if (method_exists($auth, 'isLoggedIn')) {
    if ($auth->isLoggedIn()) {
        if (method_exists($auth, 'isFinance')) {
            $is_authorized = $auth->isFinance() || $auth->isSuperAdmin();
        } else {
            $is_authorized = true;
        }
    }
} else {
    $is_authorized = !empty($_SESSION['id_user']);
}

if (!$is_authorized) {
    die("Akses ditolak - silakan login terlebih dahulu");
}

// Get parameters
$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget <= 0) {
    $debug_info = "
    <pre>
    Debug Info:
    - id_budget intval result: " . intval($_GET['id'] ?? 'NOT SET') . "
    - \$_GET['id'] raw value: " . var_export($_GET['id'] ?? 'NOT SET', true) . "
    - Full \$_GET array: " . var_export($_GET, true) . "
    - REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "
    </pre>";
    die("ID Budget tidak valid. Nilai yang diterima: " . (isset($_GET['id']) ? $_GET['id'] : 'kosong') . $debug_info);
}

// Get budget data
$query = "SELECT 
    mb.id_budget,
    mb.master_id,
    mb.period_year,
    mb.period_month,
    mb.budget_name,
    mb.total_allocation,
    mb.master_allocation,
    mb.total_remaining,
    mb.status,
    mb.created_at,
    mb.created_by,
    mb.approved_by,
    mb.approved_at,
    mb.notes,
    u.nama_user as creator_name,
    u.departemen as creator_departemen,
    u.divisi as creator_divisi,
    mc.nama_cabang as creator_cabang,
    approver.nama_user as approver_name
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN user_dfm approver ON mb.approved_by = approver.id_user
WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Query error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$budget = mysqli_fetch_assoc($result);

if (!$budget) {
    die("Data budget dengan ID " . $id_budget . " tidak ditemukan");
}

// Get budget items
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);
$items = [];
$total_used = 0;
while ($item = mysqli_fetch_assoc($items_result)) {
    $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
    $item['line_total'] = $line_total;
    $total_used += $line_total;
    $items[] = $item;
}

// Prepare filename
$timestamp = date('YmdHis');
$filename_base = 'Budget_' . str_replace(' ', '_', $budget['budget_name']) . '_' . $timestamp;

// Export to CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');

// BOM for UTF-8
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Title
fputcsv($output, ['LAPORAN DETAIL BUDGET FINANCE']);
fputcsv($output, []);

// Budget Info
fputcsv($output, ['Informasi Budget']);
fputcsv($output, ['Budget Name', $budget['budget_name']]);
fputcsv($output, ['Period', $budget['period_month'] . '/' . $budget['period_year']]);
fputcsv($output, ['Creator', $budget['creator_name'] . ' (' . $budget['creator_divisi'] . ')']);
fputcsv($output, ['Cabang', $budget['creator_cabang'] ?? '-']);
fputcsv($output, ['Status', $budget['status']]);
fputcsv($output, ['Total Allocation', 'Rp ' . number_format($budget['total_allocation'], 0, ',', '.')]);

if ($budget['approved_by']) {
    fputcsv($output, ['Approved By', $budget['approver_name']]);
    fputcsv($output, ['Approved At', date('d/m/Y H:i', strtotime($budget['approved_at']))]);
}

fputcsv($output, []);
fputcsv($output, ['DETAIL ITEMS']);

// Headers
fputcsv($output, ['Kategori', 'Rincian', 'Qty', 'Satuan', 'Harga Satuan', 'Total']);

// Items
foreach ($items as $item) {
    fputcsv($output, [
        $item['category_name'] ?? '-',
        $item['rincian'] ?? '-',
        intval($item['qty'] ?? 0),
        $item['satuan'] ?? '-',
        'Rp ' . number_format(floatval($item['harga_satuan'] ?? 0), 0, ',', '.'),
        'Rp ' . number_format($item['line_total'] ?? 0, 0, ',', '.')
    ]);
}

// Total
fputcsv($output, ['TOTAL', '', '', '', '', 'Rp ' . number_format($total_used, 0, ',', '.')]);

fclose($output);
exit;
?>

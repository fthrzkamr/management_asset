<?php
/**
 * get_prev_month_budget.php
 * AJAX endpoint: ambil item anggaran bulan lalu milik user yang sedang login
 */
include_once(__DIR__ . '/../dist/config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$user_id      = (int)$_SESSION['id_user'];
$tipe         = isset($_GET['tipe']) ? mysqli_real_escape_string($conn, trim($_GET['tipe'])) : '';
$target_month = (int)($_GET['month'] ?? 0);
$target_year  = (int)($_GET['year']  ?? date('Y'));

// Hitung bulan lalu
if ($target_month > 1) {
    $prev_month = $target_month - 1;
    $prev_year  = $target_year;
} else {
    $prev_month = 12;
    $prev_year  = $target_year - 1;
}

// Query anggaran terakhir berdasarkan User (tanpa melihat Tipe Kas)
$sql = "SELECT id_budget, budget_name, tipe_anggaran, period_month, period_year 
        FROM ppx_monthly_budget 
        WHERE created_by = $user_id
        ORDER BY period_year DESC, period_month DESC, created_at DESC LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada anggaran bulan lalu ditemukan.']);
    exit;
}

$budget = mysqli_fetch_assoc($result);
$budget_id = (int)$budget['id_budget'];

// Ambil detail item dari tabel ppx_monthly_budget_item_detail
$items_sql = "SELECT d.id_category, bc.category_name, d.rincian, d.qty, d.satuan, d.harga_satuan, d.total as total_amount
              FROM ppx_monthly_budget_item_detail d
              LEFT JOIN ppx_budget_category bc ON d.id_category = bc.id_category
              WHERE d.id_budget = $budget_id
              ORDER BY bc.category_name, d.id";

$items_result = mysqli_query($conn, $items_sql);
$categories = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $cat_id = $row['id_category'];
        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'category_id' => $cat_id, 
                'category_name' => $row['category_name'] ?? 'Tanpa Kategori', 
                'items' => []
            ];
        }
        $categories[$cat_id]['items'][] = $row;
    }
}

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

echo json_encode([
    'success' => true,
    'period' => $month_names[$prev_month] . ' ' . $prev_year,
    'tipe' => $budget['tipe_anggaran'],
    'categories' => array_values($categories),
]);

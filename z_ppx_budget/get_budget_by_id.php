<?php
/**
 * get_budget_by_id.php
 * AJAX endpoint: ambil item anggaran berdasarkan ID budget tertentu
 */
include_once(__DIR__ . '/../dist/config/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$user_id   = (int)$_SESSION['id_user'];
$budget_id = (int)($_GET['id'] ?? 0);

if (!$budget_id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

// Validasi: budget harus milik user ini
$chk_sql = "SELECT id_budget, budget_name, period_month, period_year, tipe_anggaran
            FROM ppx_monthly_budget 
            WHERE id_budget = $budget_id AND created_by = $user_id LIMIT 1";
$chk = mysqli_query($conn, $chk_sql);

if (!$chk) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($chk) === 0) {
    echo json_encode(['success' => false, 'message' => 'Budget ID ' . $budget_id . ' tidak ditemukan untuk User ID ' . $user_id]);
    exit;
}

$budget = mysqli_fetch_assoc($chk);

// Ambil detail item
$items_sql = "SELECT d.id_category, bc.category_name, d.rincian, d.qty, d.satuan, d.harga_satuan, d.total as total_amount
              FROM ppx_monthly_budget_item_detail d
              LEFT JOIN ppx_budget_category bc ON d.id_category = bc.id_category
              WHERE d.id_budget = $budget_id
              ORDER BY bc.category_name, d.id";

$items_result = mysqli_query($conn, $items_sql);
if (!$items_result) {
    echo json_encode(['success' => false, 'message' => 'Detail query error: ' . mysqli_error($conn)]);
    exit;
}
$categories = [];
while ($row = mysqli_fetch_assoc($items_result)) {
        $cat_id = $row['id_category'];
        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'category_id'   => $cat_id,
                'category_name' => $row['category_name'] ?? 'Tanpa Kategori',
                'items'         => []
            ];
        }
        $categories[$cat_id]['items'][] = $row;
    }


$month_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

echo json_encode([
    'success'    => true,
    'budget_name'=> $budget['budget_name'],
    'period'     => $month_names[$budget['period_month']] . ' ' . $budget['period_year'],
    'tipe'       => $budget['tipe_anggaran'],
    'categories' => array_values($categories),
]);
?>

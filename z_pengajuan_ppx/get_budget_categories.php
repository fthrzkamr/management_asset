<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\get_budget_categories.php
include('../dist/config/config.php');


if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['id_user'];
$current_year = date('Y');
$current_month = date('n');

// Ambil data user untuk filter
$user_query = "SELECT u.divisi, r.nama_role 
               FROM user_dfm u 
               LEFT JOIN roles r ON u.id_role = r.id_role 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['divisi'];
$user_role = $user_data['nama_role'];

// Filter budget berdasarkan role dan divisi:
// 1. IT, GA -> pakai budget yang dibuat oleh divisi HR (divisi 7)
// 2. User divisi HR (termasuk koordinator HR) -> pakai budget divisi HR
// 3. User lain -> pakai budget yang mereka buat sendiri
$division_filter = "";
if (in_array(strtolower($user_role), ['it', 'ga'])) {
    // IT dan GA pakai budget yang dibuat oleh user divisi HR (divisi 7)
    $division_filter = "AND u.divisi = 7";
    error_log("get_budget_categories: Role $user_role - menggunakan budget divisi HR");
} elseif ($user_division == 7) {
    // User divisi HR (termasuk koordinator dan HR sendiri) pakai budget divisi HR
    $division_filter = "AND u.divisi = 7";
    error_log("get_budget_categories: Divisi HR - menggunakan budget divisi HR");
} else {
    // User lain hanya bisa melihat budget yang mereka buat sendiri
    $division_filter = "AND mb.created_by = $user_id";
    error_log("get_budget_categories: Role $user_role - hanya budget created_by=$user_id");
}

// Log untuk debug
error_log("get_budget_categories: user_id=$user_id, division=$user_division, role=$user_role, year=$current_year, month=$current_month");

// Ambil budget yang dibuat oleh user yang login atau divisi yang sama (untuk IT/GA/HR)
// Hanya tampilkan kategori yang punya budget item dengan remaining > 0
$budget_query = "SELECT bc.id_category, bc.category_name, bc.category_code,
                        SUM(CASE WHEN mbi.remaining > 0 THEN mbi.remaining ELSE 0 END) as remaining, 
                        SUM(mbi.allocation) as allocation, 
                        SUM(mbi.allocation - mbi.remaining) as used_amount
                 FROM ppx_budget_category bc
                 JOIN ppx_monthly_budget_item mbi ON bc.id_category = mbi.id_category
                 JOIN ppx_monthly_budget mb ON mbi.id_budget = mb.id_budget
                 LEFT JOIN user_dfm u ON mb.created_by = u.id_user
                 WHERE mb.period_year = $current_year
                   AND mb.period_month = $current_month
                   AND mb.status IN ('Approved', 'Active')
                   AND bc.is_active = 1
                   $division_filter
                 GROUP BY bc.id_category, bc.category_name, bc.category_code
                 HAVING SUM(CASE WHEN mbi.remaining > 0 THEN mbi.remaining ELSE 0 END) > 0
                 ORDER BY bc.category_name";

$budget_result = mysqli_query($conn, $budget_query);

$categories = [];
while ($cat = mysqli_fetch_assoc($budget_result)) {
    $categories[] = [
        'id_category' => $cat['id_category'],
        'category_name' => $cat['category_name'],
        'category_code' => $cat['category_code'],
        'remaining' => $cat['remaining'],
        'allocation' => $cat['allocation'],
        'used_amount' => $cat['used_amount']
    ];
}

echo json_encode([
    'success' => true,
    'categories' => $categories,
    'current_month' => $current_month,
    'current_year' => $current_year
]);

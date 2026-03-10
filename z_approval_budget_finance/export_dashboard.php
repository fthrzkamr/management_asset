<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Check auth
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
$export_all = isset($_GET['all']) && $_GET['all'] == '1';
$ids_param = isset($_GET['ids']) ? $_GET['ids'] : '';

// Parse IDs
$budget_ids = [];
if ($export_all) {
    // Get all budget IDs
    $query = "SELECT id_budget FROM ppx_monthly_budget WHERE status IN ('Pending', 'Active') ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $budget_ids[] = intval($row['id_budget']);
    }
} else if (!empty($ids_param)) {
    // Parse comma-separated IDs
    $ids_array = explode(',', $ids_param);
    foreach ($ids_array as $id) {
        $id = intval(trim($id));
        if ($id > 0) {
            $budget_ids[] = $id;
        }
    }
}

if (empty($budget_ids)) {
    die("Tidak ada budget untuk di-export");
}

// Build query for multiple budgets
$ids_string = implode(',', $budget_ids);
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
WHERE mb.id_budget IN ($ids_string)
ORDER BY mb.period_year DESC, mb.period_month DESC, mb.created_at DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query error: " . mysqli_error($conn));
}

$budgets = [];
while ($budget = mysqli_fetch_assoc($result)) {
    $budgets[] = $budget;
}

if (empty($budgets)) {
    die("Data budget tidak ditemukan");
}

// Get all items for these budgets
$items_query = "SELECT 
    d.*,
    pbc.category_name,
    pbc.category_code,
    d.id_budget
FROM ppx_monthly_budget_item_detail d
LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
WHERE d.id_budget IN ($ids_string)
ORDER BY d.id_budget, pbc.category_name, d.id";

$items_result = mysqli_query($conn, $items_query);
$budget_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $id_budget = $item['id_budget'];
    if (!isset($budget_items[$id_budget])) {
        $budget_items[$id_budget] = [];
    }
    $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
    $item['line_total'] = $line_total;
    $budget_items[$id_budget][] = $item;
}

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Prepare filename
$timestamp = date('YmdHis');
$filename = 'Budget_Finance_' . ($export_all ? 'All' : 'Selected') . '_' . $timestamp . '.xls';

// Export to Excel (HTML-based)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for UTF-8
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Budget Finance</x:Name>
                    <x:WorksheetOptions>
                        <x:Print>
                            <x:ValidPrinterInfo/>
                        </x:Print>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        h2 { color: #1F4E78; border-bottom: 3px solid #4472C4; padding-bottom: 5px; }
        h3 { color: #2E5C8A; margin-top: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 8px; }
        th { 
            background-color: #4472C4; 
            color: white; 
            font-weight: bold; 
            text-align: center;
            font-size: 11pt;
        }
        .header-section { 
            background-color: #D9E1F2; 
            font-weight: bold; 
            font-size: 12pt;
        }
        .total-row { 
            background-color: #FFF2CC; 
            font-weight: bold; 
            border-top: 2px solid #000;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .budget-title { 
            background-color: #5B9BD5; 
            color: white; 
            font-weight: bold; 
            font-size: 13pt;
            padding: 10px;
            margin-top: 30px;
        }
        .info-label { 
            font-weight: bold; 
            width: 200px; 
            background-color: #F2F2F2;
        }
        .info-table { width: 60%; }
        .title-box {
            background-color: #1F4E78;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .title-box h2 {
            color: white;
            border: none;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Title Section -->
    <div class="title-box">
        <h2>LAPORAN BUDGET FINANCE</h2>
        <p style="margin: 5px 0;"><strong>Tanggal Export:</strong> <?= date('d/m/Y H:i') ?></p>
        <p style="margin: 5px 0;"><strong>Total Budget:</strong> <?= count($budgets) ?> Budget</p>
    </div>

    <!-- Summary Table -->
    <h3>📊 RINGKASAN BUDGET</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Periode</th>
                <th>Nama Budget</th>
                <th>PIC</th>
                <th>Cabang</th>
                <th>Status</th>
                <th>Total Alokasi</th>
                <th>Total Terpakai</th>
                <th>Sisa</th>
            </tr>
        </thead>
        <tbody>
<?php
$no = 1;
$total_allocation_sum = 0;
$total_used_sum = 0;
$total_remaining_sum = 0;

foreach ($budgets as $budget) {
    $items = $budget_items[$budget['id_budget']] ?? [];
    $total_used = 0;
    foreach ($items as $item) {
        $total_used += $item['line_total'];
    }
    
    $total_allocation_sum += $budget['total_allocation'];
    $total_used_sum += $total_used;
    $total_remaining_sum += ($budget['total_allocation'] - $total_used);
?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
                <td><?= htmlspecialchars($budget['budget_name']) ?></td>
                <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                <td><?= htmlspecialchars($budget['creator_cabang'] ?? '-') ?></td>
                <td class="text-center"><?= $budget['status'] ?></td>
                <td class="text-right"><?= number_format($budget['total_allocation'], 0, '', '') ?></td>
                <td class="text-right"><?= number_format($total_used, 0, '', '') ?></td>
                <td class="text-right"><?= number_format($budget['total_allocation'] - $total_used, 0, '', '') ?></td>
            </tr>
<?php } ?>
            <tr class="total-row">
                <td colspan="6" class="text-right"><strong>TOTAL</strong></td>
                <td class="text-right"><strong><?= number_format($total_allocation_sum, 0, '', '') ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_used_sum, 0, '', '') ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_remaining_sum, 0, '', '') ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <br><br>

    <!-- Detail for each budget -->
<?php foreach ($budgets as $budget): ?>
    <h3 class="budget-title">📋 DETAIL BUDGET: <?= htmlspecialchars($budget['budget_name']) ?></h3>
    
    <table class="info-table">
        <tr>
            <td class="info-label">Budget Name</td>
            <td><strong><?= htmlspecialchars($budget['budget_name']) ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">Period</td>
            <td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
        </tr>
        <tr>
            <td class="info-label">Creator</td>
            <td><?= htmlspecialchars($budget['creator_name']) ?> (<?= htmlspecialchars($budget['creator_divisi']) ?>)</td>
        </tr>
        <tr>
            <td class="info-label">Cabang</td>
            <td><?= htmlspecialchars($budget['creator_cabang'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="info-label">Status</td>
            <td><strong><?= $budget['status'] ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">Total Allocation</td>
            <td><strong>Rp <?= number_format($budget['total_allocation'], 0, '', '') ?></strong></td>
        </tr>
        <?php if ($budget['approved_by']): ?>
        <tr>
            <td class="info-label">Approved By</td>
            <td><?= htmlspecialchars($budget['approver_name']) ?></td>
        </tr>
        <tr>
            <td class="info-label">Approved At</td>
            <td><?= date('d/m/Y H:i', strtotime($budget['approved_at'])) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Items Table -->
    <h4 style="margin-top: 20px; color: #2E5C8A;">Detail Items:</h4>
    <table>
        <thead>
            <tr>
                <th>Cabang</th>
                <th>Kategori</th>
                <th>Rincian</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Harga Satuan</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
<?php
    $items = $budget_items[$budget['id_budget']] ?? [];
    $budget_total = 0;
    
    foreach ($items as $item):
        $budget_total += $item['line_total'];
?>
            <tr>
                <td><?= htmlspecialchars($budget['creator_cabang'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['rincian'] ?? '-') ?></td>
                <td class="text-center"><?= intval($item['qty'] ?? 0) ?></td>
                <td class="text-center"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                <td class="text-right"><?= number_format(floatval($item['harga_satuan'] ?? 0), 0, '', '') ?></td>
                <td class="text-right"><?= number_format($item['line_total'] ?? 0, 0, '', '') ?></td>
            </tr>
<?php endforeach; ?>
            <tr class="total-row">
                <td colspan="6" class="text-right"><strong>TOTAL</strong></td>
                <td class="text-right"><strong><?= number_format($budget_total, 0, '', '') ?></strong></td>
            </tr>
        </tbody>
    </table>

<?php endforeach; ?>

</body>
</html>
<?php
exit;
?>

<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_report_ppx\export_excel.php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

// Check auth
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    die("Akses ditolak - silakan login terlebih dahulu");
}
$auth->requirePermission('report_ppx', 'read');

// Get parameters dari Dashboard Report PPX
$filter_bulan = isset($_REQUEST['bulan']) ? $_REQUEST['bulan'] : date('m');
$filter_tahun = isset($_REQUEST['tahun']) ? $_REQUEST['tahun'] : date('Y');
$filter_cabang = isset($_REQUEST['cabang']) ? $_REQUEST['cabang'] : '';
$picked_ppx = isset($_POST['picked_ppx']) ? $_POST['picked_ppx'] : []; // Dari array Pick Checkbox

$where_clauses = [
    "mb.period_month = ?",
    "mb.period_year = ?",
    "mb.status NOT IN ('Leader', 'Kacab', 'Revisi', 'Ditolak')" // Hanya data approved minimum
];
$params = [$filter_bulan, $filter_tahun];
$types = "ss";

if (!empty($filter_cabang)) {
    $where_clauses[] = "u.id_cabang = (SELECT id_cabang FROM master_cabang WHERE nama_cabang = ? LIMIT 1)";
    $params[] = $filter_cabang;
    $types .= "s";
}

if (!empty($picked_ppx)) {
    // Generate placeholder ? sebanyak array picked
    $in_placeholders = implode(',', array_fill(0, count($picked_ppx), '?'));
    $where_clauses[] = "mb.id_budget IN ($in_placeholders)";
    
    // Merge tipe data dan nilai parameternya
    foreach ($picked_ppx as $p_no) {
        $params[] = $p_no;
        $types .= "s";
    }
}
$where_sql = implode(" AND ", $where_clauses);

// Build query for multiple budgets (Header Ringkasan)
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
    d.nama_divisi as creator_divisi,
    mc.nama_cabang as creator_cabang,
    approver.nama_user as approver_name
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi d ON u.divisi = d.id_divisi
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN user_dfm approver ON mb.approved_by = approver.id_user
WHERE $where_sql
ORDER BY mb.period_year DESC, mb.period_month DESC, mb.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) die("Query error: " . mysqli_error($conn));

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$budgets = [];
$budget_ids = [];
while ($budget = mysqli_fetch_assoc($result)) {
    $budgets[] = $budget;
    $budget_ids[] = $budget['id_budget'];
}

if (empty($budgets)) {
    die("Data budget tidak ditemukan pada periode/cabang ini");
}

$ids_string = implode(',', $budget_ids);
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
if($items_result) {
    while ($item = mysqli_fetch_assoc($items_result)) {
        $id_budget = $item['id_budget'];
        if (!isset($budget_items[$id_budget])) {
            $budget_items[$id_budget] = [];
        }
        $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
        $item['line_total'] = $line_total;
        $budget_items[$id_budget][] = $item;
    }
}

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Prepare filename
$timestamp = date('YmdHis');
$export_tipe = !empty($picked_ppx) ? 'Selected' : 'All';
$filename = 'Laporan_Anggaran_' . $export_tipe . '_' . $timestamp . '.xls';

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
                    <x:Name>Laporan Anggaran PPX</x:Name>
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
        <h2>LAPORAN PENGAJUAN ANGGARAN PPX BULANAN</h2>
        <p style="margin: 5px 0;"><strong>Periode Filter:</strong> <?= $month_names[intval($filter_bulan)] ?> <?= htmlspecialchars($filter_tahun) ?></p>
        <p style="margin: 5px 0;"><strong>Cabang:</strong> <?= !empty($filter_cabang) ? htmlspecialchars($filter_cabang) : 'Semua Cabang' ?></p>
        <p style="margin: 5px 0;"><strong>Tanggal Export:</strong> <?= date('d/m/Y H:i') ?></p>
        <p style="margin: 5px 0;"><strong>Total Data:</strong> <?= count($budgets) ?> Budget</p>
    </div>

    <!-- Summary Table -->
    <h3>📊 RINGKASAN BUDGET</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Periode</th>
                <th>Cabang</th>
                <th>PIC</th>
                <th>Divisi</th>
                <th>Status Terbaru</th>
                <th>Total Alokasi (Rp)</th>
                <th>Total Terpakai (Rp)</th>
                <th>Sisa (Rp)</th>
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
                <td class="text-center"><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
                <td><?= htmlspecialchars($budget['creator_cabang'] ?? '-') ?></td>
                <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                <td><?= htmlspecialchars($budget['creator_divisi'] ?? '-') ?></td>
                <td class="text-center"><?= $budget['status'] ?></td>
                <td class="text-right"><?= number_format($budget['total_allocation'], 0, '', '') ?></td>
                <td class="text-right"><?= number_format($total_used, 0, '', '') ?></td>
                <td class="text-right"><?= number_format($budget['total_allocation'] - $total_used, 0, '', '') ?></td>
            </tr>
<?php } ?>
            <tr class="total-row">
                <td colspan="6" class="text-right"><strong>GRAND TOTAL</strong></td>
                <td class="text-right"><strong><?= number_format($total_allocation_sum, 0, '', '') ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_used_sum, 0, '', '') ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_remaining_sum, 0, '', '') ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <br><br>
    
    <!-- Unified Detail Table for All Items -->
    <h3 class="budget-title">📋 RINCIAN ITEM SELURUH ANGGARAN</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 15%">Cabang</th>
                <th style="width: 15%">Anggaran</th>
                <th style="width: 15%">Kategori</th>
                <th style="width: 25%">Rincian Kebutuhan</th>
                <th style="width: 5%">Qty</th>
                <th style="width: 5%">Jml Hari</th>
                <th style="width: 5%">Jml Minggu</th>
                <th style="width: 10%">Harga Satuan (Rp)</th>
                <th style="width: 10%">Total Harga (Rp)</th>
            </tr>
        </thead>
        <tbody>
<?php
    $grand_item_no = 1;
    $grand_item_total = 0;
    
    foreach ($budgets as $budget) {
        $items = $budget_items[$budget['id_budget']] ?? [];
        $cabang_nama = htmlspecialchars($budget['creator_cabang'] ?? '-');
        $budget_nama = htmlspecialchars($budget['budget_name'] ?? '-');
        
        foreach ($items as $item) {
            $grand_item_total += $item['line_total'];
?>
            <tr>
                <td class="text-center"><?= $grand_item_no++ ?></td>
                <td><?= $cabang_nama ?></td>
                <td><?= $budget_nama ?></td>
                <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['rincian'] ?? '-') ?></td>
                <td class="text-center"><?= intval($item['qty'] ?? 0) ?></td>
                <td class="text-center"><?= intval($item['jumlah_hari'] ?? 0) ?></td>
                <td class="text-center"><?= intval($item['jumlah_minggu'] ?? 0) ?></td>
                <td class="text-right"><?= number_format(floatval($item['harga_satuan'] ?? 0), 0, '', '') ?></td>
                <td class="text-right"><?= number_format($item['line_total'] ?? 0, 0, '', '') ?></td>
            </tr>
<?php 
        }
    }

    if ($grand_item_no == 1) {
?>
            <tr>
                <td colspan="8" class="text-center"><em>Tidak ada rincian item</em></td>
            </tr>
<?php
    }
?>
            <tr class="total-row">
                <td colspan="9" class="text-right"><strong>GRAND TOTAL ITEM</strong></td>
                <td class="text-right"><strong><?= number_format($grand_item_total, 0, '', '') ?></strong></td>
            </tr>
        </tbody>
    </table>

</body>
</html>
<?php
exit;
?>

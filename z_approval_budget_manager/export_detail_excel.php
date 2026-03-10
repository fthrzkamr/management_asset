<?php
session_start();
include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission
if (!$auth->isManagerFinance() && !$auth->isSuperAdmin()) {
    header("Location: ../index.php");
    exit();
}

$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget <= 0) {
    die('ID Budget tidak valid');
}

// Get budget data
$query = "SELECT mb.*, u.nama_user as creator_name, a.nama_user as approver_name
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN user_dfm a ON mb.approved_by = a.id_user
          WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die('Query error: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    die('Data tidak ditemukan');
}

$budget = mysqli_fetch_assoc($result);

// Get item-level details grouped by category
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Set headers for Excel download
$filename = 'Detail_Anggaran_' . $month_names[$budget['period_month']] . '_' . $budget['period_year'] . '_' . date('YmdHis') . '.xls';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Get status text
$status_text = $budget['status'];
switch ($budget['status']) {
    case 'Draft': 
        $status_text = 'Menunggu Approval';
        break;
    case 'Active': 
        $status_text = 'Disetujui';
        break;
    case 'Closed': 
        $status_text = 'Ditutup';
        break;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 16pt;
            font-weight: bold;
        }
        .header h3 {
            margin: 3px 0;
            font-size: 14pt;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px;
            font-size: 10pt;
        }
        .info-table th {
            padding: 5px;
            text-align: left;
            font-weight: bold;
            width: 200px;
            font-size: 10pt;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #333;
            font-size: 10pt;
        }
        .data-table td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 10pt;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .category-header {
            background-color: #e3f2fd !important;
            font-weight: bold;
            text-align: left;
            padding: 8px !important;
        }
        .total-row {
            background-color: #fff9c4 !important;
            font-weight: bold;
        }
        .grand-total-row {
            background-color: #4CAF50 !important;
            color: white;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-bold {
            font-weight: bold;
        }
        .separator {
            margin: 20px 0;
            border-bottom: 2px solid #333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h2>DETAIL ANGGARAN BULANAN</h2>
        <h3><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></h3>
        <p style="margin: 5px 0;">Tanggal Cetak: <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <!-- Budget Information -->
    <table class="info-table">
        <tr>
            <th>Nama Budget</th>
            <td>: <?= htmlspecialchars($budget['budget_name']) ?></td>
            <th>Total Alokasi</th>
            <td>: <?= number_format($budget['total_allocation'], 0, '', '') ?></td>
        </tr>
        <tr>
            <th>Periode</th>
            <td>: <?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
            <th>Total Sisa</th>
            <td>: <?= number_format($budget['total_remaining'], 0, '', '') ?></td>
        </tr>
        <tr>
            <th>Dibuat Oleh</th>
            <td>: <?= htmlspecialchars($budget['creator_name']) ?></td>
            <th>Status</th>
            <td>: <?= $status_text ?></td>
        </tr>
        <tr>
            <th>Tanggal Dibuat</th>
            <td>: <?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
            <th></th>
            <td></td>
        </tr>
        </tr>
        <?php if ($budget['approved_by']): ?>
        <tr>
            <th>Disetujui Oleh</th>
            <td>: <?= htmlspecialchars($budget['approver_name']) ?></td>
            <th>Tanggal Approval</th>
            <td>: <?= date('d/m/Y H:i', strtotime($budget['approved_at'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($budget['notes'])): ?>
        <tr>
            <th>Catatan</th>
            <td colspan="3">: <?= nl2br(htmlspecialchars($budget['notes'])) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="separator"></div>

    <!-- Detail Items Table -->
    <h3 style="margin-bottom: 10px;">Detail Alokasi per Kategori</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 8%;">Kode</th>
                <th style="width: 20%;">Kategori</th>
                <th style="width: 28%;">Rincian</th>
                <th style="width: 6%;">Qty</th>
                <th style="width: 8%;">Satuan</th>
                <th style="width: 13%;">Harga Satuan</th>
                <th style="width: 13%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $total_allocation = 0;
            $current_category = null;
            $category_total = 0;
            $category_items = [];
            
            // Collect items by category
            while ($item = mysqli_fetch_assoc($items_result)) {
                $cat_id = $item['id_category'];
                if (!isset($category_items[$cat_id])) {
                    $category_items[$cat_id] = [
                        'category_code' => $item['category_code'],
                        'category_name' => $item['category_name'],
                        'items' => []
                    ];
                }
                $category_items[$cat_id]['items'][] = $item;
            }
            
            // Display by category
            foreach ($category_items as $cat_id => $category):
                $category_total = 0;
            ?>
                <tr class="category-header">
                    <td colspan="8"><?= htmlspecialchars($category['category_code']) ?> - <?= htmlspecialchars($category['category_name']) ?></td>
                </tr>
                <?php 
                foreach ($category['items'] as $item):
                    $line_total = isset($item['total']) ? floatval($item['total']) : (intval($item['qty']) * floatval($item['harga_satuan']));
                    $category_total += $line_total;
                    $total_allocation += $line_total;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= htmlspecialchars($item['category_code']) ?></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td><?= htmlspecialchars($item['rincian']) ?></td>
                    <td class="text-center"><?= intval($item['qty']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($item['satuan']) ?></td>
                    <td class="text-right"><?= number_format(floatval($item['harga_satuan']), 0, '', '') ?></td>
                    <td class="text-right"><?= number_format($line_total, 0, '', '') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="7" class="text-right"><strong>Subtotal <?= htmlspecialchars($category['category_name']) ?>:</strong></td>
                    <td class="text-right"><strong><?= number_format($category_total, 0, '', '') ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="grand-total-row">
                <td colspan="7" class="text-right"><strong>TOTAL KESELURUHAN:</strong></td>
                <td class="text-right"><strong><?= number_format($total_allocation, 0, '', '') ?></strong></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <div style="margin-top: 40px;">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; text-align: center;">
                    <p>Dibuat Oleh,</p>
                    <br><br><br>
                    <p><strong><u><?= htmlspecialchars($budget['creator_name']) ?></u></strong></p>
                    <p><?= date('d/m/Y', strtotime($budget['created_at'])) ?></p>
                </td>
                <td style="width: 50%; text-align: center;">
                    <?php if ($budget['approved_by']): ?>
                    <p>Disetujui Oleh,</p>
                    <br><br><br>
                    <p><strong><u><?= htmlspecialchars($budget['approver_name']) ?></u></strong></p>
                    <p><?= date('d/m/Y', strtotime($budget['approved_at'])) ?></p>
                    <?php else: ?>
                    <p>Menunggu Persetujuan</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

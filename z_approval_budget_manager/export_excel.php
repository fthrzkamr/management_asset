<?php
// Export daftar pengajuan bulanan (summary list)

include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Temukan nama kolom timestamp
$ts_column = '';
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM ppx_monthly_budget");
if ($colRes) {
    while ($col = mysqli_fetch_assoc($colRes)) {
        $field = $col['Field'];
        if (in_array($field, ['created_at','created_on','created','tanggal','created_date'])) {
            $ts_column = $field;
            break;
        }
    }
}

// Ambil semua budget dengan join user
$budgets_sql = "SELECT mb.id_budget, mb.period_year, mb.period_month, mb.budget_name, 
                mb.total_allocation, mb.total_remaining, mb.status, 
                u.nama_user as creator_name, a.nama_user as approver_name, mb.approved_at" .
               ($ts_column ? ", mb.`$ts_column` AS ts" : "") .
               " FROM ppx_monthly_budget mb
                 LEFT JOIN user_dfm u ON mb.created_by = u.id_user
                 LEFT JOIN user_dfm a ON mb.approved_by = a.id_user
                 ORDER BY mb.period_year DESC, mb.period_month DESC, mb.id_budget";
$bres = mysqli_query($conn, $budgets_sql);
if (!$bres) {
    echo '<h3>ERROR: ' . htmlspecialchars(mysqli_error($conn)) . '</h3>';
    exit();
}

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Set Excel headers
$filename = 'Daftar_Anggaran_Bulanan_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // BOM
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 16pt;
            font-weight: bold;
            color: #333;
        }
        .header p {
            margin: 3px 0;
            font-size: 10pt;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #4CAF50;
            color: white;
            font-weight: bold;
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #333;
            font-size: 10pt;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #f1f1f1;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .status-active {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-draft {
            background: #FFC107;
            color: #333;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-pending {
            background: #FF9800;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-rejected {
            background: #F44336;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>DAFTAR PENGAJUAN ANGGARAN BULANAN</h2>
        <p>Tanggal Export: <?= date('d/m/Y H:i:s') ?></p>
        <p>Total: <?= mysqli_num_rows($bres) ?> Pengajuan</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 10%;">Periode</th>
                <th style="width: 20%;">Nama Budget</th>
                <th style="width: 15%;">PIC / Dibuat Oleh</th>
                <th style="width: 12%;">Tanggal Pengajuan</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 13%;">Total Alokasi</th>
                <th style="width: 13%;">Total Sisa</th>
                <th style="width: 12%;">Disetujui Oleh</th>
            </tr>
        </thead>
        <tbody>
<?php
$no = 1;
$total_alokasi_keseluruhan = 0;
$total_sisa_keseluruhan = 0;

while ($b = mysqli_fetch_assoc($bres)):
    $periode_text = $month_names[$b['period_month']] . ' ' . $b['period_year'];
    $ts = $b['ts'] ?? '';
    $pic_name = $b['creator_name'] ?? '-';
    
    // Status
    $status_class = 'status-draft';
    $status_text = $b['status'];
    switch ($b['status']) {
        case 'Active': 
            $status_class = 'status-active';
            $status_text = 'Disetujui';
            break;
        case 'Draft': 
            $status_class = 'status-draft';
            $status_text = 'Menunggu Approval';
            break;
        case 'pending': 
            $status_class = 'status-pending';
            $status_text = 'Pending';
            break;
        case 'rejected': 
            $status_class = 'status-rejected';
            $status_text = 'Ditolak';
            break;
    }
    
    $total_alokasi_keseluruhan += $b['total_allocation'];
    $total_sisa_keseluruhan += $b['total_remaining'];
?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= $periode_text ?></td>
                <td><?= htmlspecialchars($b['budget_name'] ?? 'Budget ' . $periode_text) ?></td>
                <td><?= htmlspecialchars($pic_name) ?></td>
                <td class="text-center"><?= $ts ? date('d/m/Y H:i', strtotime($ts)) : '-' ?></td>
                <td class="text-center"><span class="<?= $status_class ?>"><?= $status_text ?></span></td>
                <td class="text-right">Rp <?= number_format($b['total_allocation'], 0, ',', '.') ?></td>
                <td class="text-right">Rp <?= number_format($b['total_remaining'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($b['approver_name'] ?? '-') ?></td>
            </tr>
<?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="background: #4CAF50; color: white; font-weight: bold;">
                <td colspan="6" class="text-right"><strong>TOTAL KESELURUHAN:</strong></td>
                <td class="text-right"><strong>Rp <?= number_format($total_alokasi_keseluruhan, 0, ',', '.') ?></strong></td>
                <td class="text-right"><strong>Rp <?= number_format($total_sisa_keseluruhan, 0, ',', '.') ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #333;">
        <p style="text-align: center; font-size: 9pt; color: #666;">
            --- End of Report ---<br>
            Generated by DFM Asset Management System
        </p>
    </div>
</body>
</html>

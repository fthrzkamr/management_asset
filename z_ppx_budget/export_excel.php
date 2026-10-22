<?php
include_once('../dist/config/config.php');
include_once('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if (!$auth->hasPermission('ppx_budget', 'read') && !$auth->hasPermission('approval_budget_kacab', 'read') && !$auth->hasPermission('approval_budget_manager', 'read')) {
    exit('Akses ditolak!');
}

$id_budget = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_budget <= 0) {
    exit('ID Budget tidak valid!');
}

// Get Budget Header
$query = "SELECT mb.*, u.nama_user, d.nama_departemen, mc.nama_cabang
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN departemen d ON u.departemen = d.id_departemen
          LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
          WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$budget = mysqli_fetch_assoc($result);

if (!$budget) {
    exit('Data anggaran tidak ditemukan!');
}

// Generate Filename
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulan = $month_names[$budget['period_month']];
$tahun = $budget['period_year'];

$filename = "Laporan_Anggaran_" . str_replace(' ', '_', $budget['budget_name']) . "_{$bulan}_{$tahun}.xls";

// Headers for Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; text-align: center; }
        .subtitle { font-size: 14px; text-align: center; margin-bottom: 20px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bg-light { background-color: #e9ecef; }
    </style>
</head>
<body>

    <div class="title">LAPORAN ANGGARAN BULANAN</div>
    <div class="subtitle">Bulan: <?= $bulan ?> <?= $tahun ?></div>

    <table style="margin-bottom: 20px; border: none;">
        <tr>
            <td style="border: none; width: 150px;"><strong>Nama Budget</strong></td>
            <td style="border: none;">: <?= htmlspecialchars($budget['budget_name']) ?></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>PIC</strong></td>
            <td style="border: none;">: <?= htmlspecialchars($budget['nama_user']) ?></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>Cabang</strong></td>
            <td style="border: none;">: <?= htmlspecialchars($budget['nama_cabang']) ?></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>Status</strong></td>
            <td style="border: none;">: <?= htmlspecialchars($budget['status']) ?></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kategori</th>
                <th width="20%">Rincian</th>
                <th width="5%">Qty</th>
                <th width="10%">Satuan</th>
                <th width="15%">Harga Satuan</th>
                <th width="15%">Total Rincian</th>
                <th width="15%">Total Kategori (Alokasi)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get Items
            $items_query = "SELECT mbi.*, bc.category_name, bc.category_code
                            FROM ppx_monthly_budget_item mbi
                            LEFT JOIN ppx_budget_category bc ON mbi.id_category = bc.id_category
                            WHERE mbi.id_budget = ?
                            ORDER BY bc.category_name";
            $stmt_items = mysqli_prepare($conn, $items_query);
            mysqli_stmt_bind_param($stmt_items, "i", $id_budget);
            mysqli_stmt_execute($stmt_items);
            $items_result = mysqli_stmt_get_result($stmt_items);
            
            $no_kat = 1;
            $grand_total = 0;
            
            while ($item = mysqli_fetch_assoc($items_result)): 
                $grand_total += $item['allocation'];
                
                // Get details for this item
                $details_query = "SELECT * FROM ppx_monthly_budget_item_detail 
                                  WHERE id_budget_item = ? ORDER BY id";
                $stmt_details = mysqli_prepare($conn, $details_query);
                mysqli_stmt_bind_param($stmt_details, "i", $item['id_budget_item']);
                mysqli_stmt_execute($stmt_details);
                $details_result = mysqli_stmt_get_result($stmt_details);
                $detail_count = mysqli_num_rows($details_result);
                
                if ($detail_count > 0):
                    $first = true;
                    while ($detail = mysqli_fetch_assoc($details_result)):
            ?>
                        <tr>
                            <?php if ($first): ?>
                                <td class="text-center" rowspan="<?= $detail_count ?>"><?= $no_kat++ ?></td>
                                <td rowspan="<?= $detail_count ?>">
                                    <strong><?= htmlspecialchars($item['category_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($item['category_code']) ?></small>
                                </td>
                            <?php endif; ?>
                            
                            <td><?= htmlspecialchars($detail['rincian']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($detail['qty']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($detail['satuan']) ?></td>
                            <td class="text-right"><?= number_format($detail['harga_satuan'], 0, ',', '.') ?></td>
                            <td class="text-right"><?= number_format($detail['total'], 0, ',', '.') ?></td>
                            
                            <?php if ($first): ?>
                                <td class="text-right bg-light" rowspan="<?= $detail_count ?>">
                                    <strong><?= number_format($item['allocation'], 0, ',', '.') ?></strong>
                                </td>
                            <?php endif; ?>
                        </tr>
            <?php 
                        $first = false;
                    endwhile;
                else: 
            ?>
                    <!-- If no details, just show category row -->
                    <tr>
                        <td class="text-center"><?= $no_kat++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['category_name']) ?></strong><br>
                            <small><?= htmlspecialchars($item['category_code']) ?></small>
                        </td>
                        <td colspan="5" class="text-center text-muted"><em>Tidak ada rincian</em></td>
                        <td class="text-right bg-light"><strong><?= number_format($item['allocation'], 0, ',', '.') ?></strong></td>
                    </tr>
            <?php 
                endif; 
            endwhile; 
            ?>
            
            <tr>
                <th colspan="7" class="text-right">GRAND TOTAL</th>
                <th class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></th>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <p>Diunduh oleh: <?= htmlspecialchars($_SESSION['nama_user']) ?> pada <?= date('d/m/Y H:i:s') ?></p>
    </div>

</body>
</html>

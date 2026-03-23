<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_report_ppx\export_excel_all.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    die("Akses ditolak - silakan login terlebih dahulu");
}
$auth->requirePermission('report_ppx_all', 'read');

$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_cabang = isset($_GET['cabang']) ? $_GET['cabang'] : '';
$filter_type = isset($_GET['request_type']) ? $_GET['request_type'] : '';

$where_clauses = [
    "MONTH(pr.tanggal_pengajuan) = ?",
    "YEAR(pr.tanggal_pengajuan) = ?",
    "pr.status NOT IN ('Draft', 'Leader', 'Kacab', 'Revisi', 'Ditolak')"
];
$params = [$filter_bulan, $filter_tahun];
$types = "ss";

if (!empty($filter_cabang)) {
    $where_clauses[] = "pr.cabang = ?";
    $params[] = $filter_cabang;
    $types .= "s";
}

if (!empty($filter_type)) {
    $where_clauses[] = "pr.request_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT 
    pr.*,
    d.nama_divisi,
    u.nama_user as nama_pengaju
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
WHERE $where_sql
ORDER BY pr.tanggal_pengajuan DESC, pr.no_ppx DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) die("Query error: " . mysqli_error($conn));

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$requests = [];
$no_ppx_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = $row;
    $no_ppx_list[] = "'" . mysqli_real_escape_string($conn, $row['no_ppx']) . "'";
}

$details = [];
if (!empty($no_ppx_list)) {
    $list_sql = implode(',', $no_ppx_list);
    $detail_query = "SELECT * FROM ppx_request_detail WHERE no_ppx IN ($list_sql) ORDER BY no_ppx, no_ppx_detail";
    $detail_result = mysqli_query($conn, $detail_query);
    while ($d = mysqli_fetch_assoc($detail_result)) {
        $details[$d['no_ppx']][] = $d;
    }
}

$filename = 'Laporan_PPX_All_' . date('Ymd_His') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #4472C4; color: white; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .header-title { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 5px; }
        .group-header { background-color: #D9E1F2; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header-title">LAPORAN PENGAJUAN PPX (REGULAR & MONTHLY)</div>
    <div style="text-align: center; font-weight: bold;">PT. DUA FARMA MAHAKARSA</div>
    <div style="text-align: center; margin-bottom: 10px;">
        Periode: <?= $filter_bulan ?> / <?= $filter_tahun ?> 
        | Cabang: <?= !empty($filter_cabang) ? $filter_cabang : 'Semua Cabang' ?>
        | Tipe: <?= !empty($filter_type) ? $filter_type : 'Semua Tipe' ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>No. PPX</th>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Cabang</th>
                <th>PIC</th>
                <th>Divisi</th>
                <th>Rincian Item</th>
                <th>Qty</th>
                <th>Harga Satuan</th>
                <th>Total Harga</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($requests as $row): 
                $items = $details[$row['no_ppx']] ?? [];
                $rowcount = count($items);
                if ($rowcount == 0) continue; // Skip if no items
                
                foreach ($items as $index => $item):
            ?>
                <tr>
                    <?php if ($index === 0): ?>
                        <td class="text-center" rowspan="<?= $rowcount ?>"><?= $no++ ?></td>
                        <td rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['no_ppx']) ?></td>
                        <td class="text-center" rowspan="<?= $rowcount ?>"><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                        <td class="text-center" rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['request_type']) ?></td>
                        <td rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['nama_pic']) ?></td>
                        <td rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['cabang']) ?></td>
                        <td rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['nama_divisi'] ?? '-') ?></td>
                    <?php endif; ?>
                    
                    <td><?= htmlspecialchars($item['rincian']) ?></td>
                    <td class="text-center"><?= $item['jumlah'] ?></td>
                    <td class="text-right"><?= number_format($item['harga_satuan'], 0, '', '') ?></td>
                    <td class="text-right"><?= number_format($item['total_harga'], 0, '', '') ?></td>
                    
                    <?php if ($index === 0): ?>
                        <td class="text-center" rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['status']) ?></td>
                        <td rowspan="<?= $rowcount ?>"><?= htmlspecialchars($row['nama_pengaju']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php 
                endforeach;
            endforeach; 
            ?>
        </tbody>
    </table>
</body>
</html>
<?php exit; ?>

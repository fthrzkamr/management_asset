<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\export_excel.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('ppx', 'read');

// Query berdasarkan role
$whereClause = "";
if ($auth->isKoordinator()) {
    $whereClause = "WHERE pr.user_id = " . (int)$_SESSION['id_user'];
}

// Query summary per PPX (gabungkan detail untuk total nominal)
// Tambahkan field status_mapped: jika status = 'Leader' => 'Kacap/Manager', jika status = 'Manager' => 'Manager Finance'
// Tambahkan pr.nominal_realisasi untuk ditampilkan per baris
$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi,
    pr.cabang,
    pr.alasan_pengajuan,
    pr.tingkat_kepentingan,
    pr.tanggal_dibutuhkan,
    pr.status,
    CASE 
        WHEN pr.status = 'Leader' THEN 'Kacap/Manager' 
        WHEN pr.status = 'Manager' THEN 'Manager Finance' 
        ELSE pr.status 
    END AS status_mapped,
    pr.nominal_ppx,
    pr.nominal_realisasi,
    u.nama_user as nama_pengaju,
    COUNT(prd.no_ppx_detail) as jumlah_item,
    COALESCE(SUM(prd.total_harga), 0) as total_nominal_detail
FROM ppx_request pr
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
$whereClause
GROUP BY 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi,
    pr.cabang,
    pr.alasan_pengajuan,
    pr.tingkat_kepentingan,
    pr.tanggal_dibutuhkan,
    pr.status,
    pr.nominal_ppx,
    pr.nominal_realisasi,
    u.nama_user
ORDER BY pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Set header untuk download Excel
$filename = "Laporan_Pengajuan_PPX_" . date('Y-m-d_H-i-s') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output Excel content
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-size:12px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .currency { text-align: right; }
    </style>
</head>
<body>';

echo '<h3 style="text-align: center;">LAPORAN PENGAJUAN PPX</h3>';
echo '<h5 style="text-align: center;">DF GROUP</h5>';
echo '<br>';

echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>No. PPX</th>';
echo '<th>Tanggal Pengajuan</th>';
echo '<th>Nama</th>';
echo '<th>Jabatan</th>';
echo '<th>Divisi</th>';
echo '<th>Cabang</th>';
echo '<th>Jumlah Item</th>';
echo '<th>Total Nominal (Detail)</th>';
echo '<th>Nominal PPX (field)</th>';
// tambah kolom Nominal Realisasi setelah nominal PPX
echo '<th>Nominal Realisasi</th>';
echo '<th>Tingkat Kepentingan</th>';
echo '<th>Tanggal Dibutuhkan</th>';
echo '<th>Alasan Pengajuan</th>';
echo '<th>Status</th>';
echo '<th>Nama Pengaju</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td class="center">' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($row['no_ppx']) . '</td>';
    echo '<td class="center">' . ($row['tanggal_pengajuan'] ? date('d/m/Y', strtotime($row['tanggal_pengajuan'])) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_pic']) . '</td>';
    echo '<td>' . htmlspecialchars($row['jabatan']) . '</td>';
    echo '<td>' . htmlspecialchars($row['divisi']) . '</td>';
    echo '<td>' . htmlspecialchars($row['cabang']) . '</td>';
    echo '<td class="center">' . (int)$row['jumlah_item'] . '</td>';
    echo '<td class="currency">Rp ' . number_format($row['total_nominal_detail'], 0, ',', '.') . '</td>';
    echo '<td class="currency">Rp ' . number_format($row['nominal_ppx'], 0, ',', '.') . '</td>';
    // tampilkan nominal_realisasi yang baru ditambahkan
    echo '<td class="currency">Rp ' . number_format($row['nominal_realisasi'] ?? 0, 0, ',', '.') . '</td>';
    echo '<td>' . htmlspecialchars($row['tingkat_kepentingan']) . '</td>';
    echo '<td class="center">' . ($row['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($row['tanggal_dibutuhkan'])) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['alasan_pengajuan']) . '</td>';
    // tampilkan status_mapped yang sudah diinisialisasi sesuai ketentuan
    echo '<td>' . htmlspecialchars($row['status_mapped']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_pengaju']) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Ringkasan sederhana
// Hitung statistik + total detail dari ppx_request_detail (SUM(prd.total_harga))
// Hitung total nominal_realisasi TERPISAH (subquery) agar tidak terduplikasi oleh JOIN
$stats_query = "SELECT 
    COUNT(DISTINCT pr.no_ppx) as total,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Kacap/Manager' THEN pr.no_ppx END) as pending_leader,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Manager Finance' THEN pr.no_ppx END) as pending_manager,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Finance' THEN pr.no_ppx END) as pending_finance,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Direktur' THEN pr.no_ppx END) as pending_direktur,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Uang Muka' THEN pr.no_ppx END) as uang_muka,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Selesai' THEN pr.no_ppx END) as selesai,
    COUNT(DISTINCT CASE WHEN (CASE WHEN pr.status = 'Leader' THEN 'Kacap/Manager' WHEN pr.status = 'Manager' THEN 'Manager Finance' ELSE pr.status END) = 'Revisi' THEN pr.no_ppx END) as revisi,
    COALESCE(SUM(prd.total_harga),0) as total_all_detail
FROM ppx_request pr
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
$whereClause";

$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];

// Ambil total nominal_realisasi TERPISAH (tanpa join ke detail) supaya tidak terduplikasi
$realisasi_sum_query = "SELECT COALESCE(SUM(nominal_realisasi),0) as total_nominal_realisasi FROM ppx_request pr2 $whereClause";
$realisasi_result = mysqli_query($conn, $realisasi_sum_query);
$realisasi_row = $realisasi_result ? mysqli_fetch_assoc($realisasi_result) : ['total_nominal_realisasi' => 0];
$stats['total_nominal_realisasi'] = $realisasi_row['total_nominal_realisasi'];

echo '<br><h4>RINGKASAN</h4>';
echo '<table style="width:50%">';
echo '<tr><td>Pending (Kacap/Manager)</td><td class="right">' . ((int)($stats['pending_leader'] ?? 0)) . '</td></tr>';
echo '<tr><td>Pending (Manager Finance)</td><td class="right">' . ((int)($stats['pending_manager'] ?? 0)) . '</td></tr>';
echo '<tr><td>Pending (Finance)</td><td class="right">' . ((int)($stats['pending_finance'] ?? 0)) . '</td></tr>';
echo '<tr><td>Pending (Direktur)</td><td class="right">' . ((int)($stats['pending_direktur'] ?? 0)) . '</td></tr>';
echo '<tr><td>Uang Muka</td><td class="right">' . ((int)($stats['uang_muka'] ?? 0)) . '</td></tr>';
echo '<tr><td>Done (Selesai)</td><td class="right">' . ((int)($stats['selesai'] ?? 0)) . '</td></tr>';
echo '<tr><td>Revisi</td><td class="right">' . ((int)($stats['revisi'] ?? 0)) . '</td></tr>';
echo '<tr><td>Total Semua Pengajuan</td><td class="right">' . ((int)($stats['total'] ?? 0)) . '</td></tr>';
echo '<tr><td>Total Semua Nominal (detail)</td><td class="right currency">Rp ' . number_format((int)($stats['total_all_detail'] ?? 0), 0, ',', '.') . '</td></tr>';
// tampilkan total nominal_realisasi di ringkasan
echo '<tr><td>Total Nominal Realisasi</td><td class="right currency">Rp ' . number_format((int)($stats['total_nominal_realisasi'] ?? 0), 0, ',', '.') . '</td></tr>';
echo '</table>';

echo '<br><p style="font-size:10px;color:#666">Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . htmlspecialchars($_SESSION['nama_user']) . '</p>';

echo '</body></html>';

mysqli_close($conn);
exit();
?>

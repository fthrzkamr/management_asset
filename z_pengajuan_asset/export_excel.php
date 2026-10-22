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

$auth->requirePermission('pengajuan_asset', 'read');

// Query berdasarkan permission
$canApprove = $auth->hasPermission('pengajuan_asset', 'approve');
$whereClause = $canApprove ? "" : "WHERE ar.user_id = " . (int)$_SESSION['id_user'];

$query = "SELECT 
    ar.no_pengajuan,
    ar.tanggal_pengajuan,
    ar.nama_pic,
    ar.jabatan,
    ar.divisi,
    d.nama_divisi,
    ka.kategori_asset,
    ar.jenis_pengadaan,
    ar.deskripsi_asset,
    ar.spesifikasi_merek,
    ar.alasan_pengajuan,
    ar.jumlah,
    ar.satuan,
    ar.tingkat_kepentingan,
    ar.tanggal_dibutuhkan,
    la.lokasi_asset,
    ar.detail_lokasi,
    ar.estimasi_harga,
    ar.status,
    ar.catatan_approval,
    ar.approved_by,
    ar.approved_at,
    ar.catatan_tambahan,
    u.nama_user as nama_pengaju,
    approver.nama_user as nama_approver
FROM asset_request ar
LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
LEFT JOIN lokasi_asset la ON ar.lokasi_penggunaan = la.id_lokasi
LEFT JOIN user_dfm u ON ar.user_id = u.id_user
LEFT JOIN user_dfm approver ON ar.approved_by = approver.id_user
LEFT JOIN divisi d ON ar.divisi = d.id_divisi
$whereClause
ORDER BY ar.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);

// Set header untuk download Excel
$filename = "Laporan_Pengajuan_Asset_" . date('Y-m-d_H-i-s') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output Excel content
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .currency { text-align: right; }
    </style>
</head>
<body>';

echo '<h2 style="text-align: center;">LAPORAN PENGAJUAN ASSET</h2>';
echo '<h3 style="text-align: center;">DF GROUP</h3>';
echo '<p style="text-align: center;">Periode: ' . date('d/m/Y H:i:s') . '</p>';
echo '<br>';

echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>No. Pengajuan</th>';
echo '<th>Tanggal Pengajuan</th>';
echo '<th>Nama Pengaju</th>';
echo '<th>PIC</th>';
echo '<th>Jabatan</th>';
echo '<th>Divisi</th>';
echo '<th>Kategori Asset</th>';
echo '<th>Jenis Pengadaan</th>';
echo '<th>Deskripsi Asset</th>';
echo '<th>Spesifikasi Merek</th>';
echo '<th>Alasan Pengajuan</th>';
echo '<th>Jumlah</th>';
echo '<th>Satuan</th>';
echo '<th>Tingkat Kepentingan</th>';
echo '<th>Tanggal Dibutuhkan</th>';
echo '<th>Lokasi Penggunaan</th>';
echo '<th>Detail Lokasi</th>';
echo '<th>Estimasi Harga</th>';
echo '<th>Status</th>';
echo '<th>Catatan Approval</th>';
echo '<th>Disetujui Oleh</th>';
echo '<th>Tanggal Approval</th>';
echo '<th>Catatan Tambahan</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td class="center">' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($row['no_pengajuan']) . '</td>';
    echo '<td class="center">' . date('d/m/Y H:i', strtotime($row['tanggal_pengajuan'])) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_pengaju']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_pic']) . '</td>';
    echo '<td>' . htmlspecialchars($row['jabatan']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_divisi'] ?? $row['divisi']) . '</td>';
    echo '<td>' . htmlspecialchars($row['kategori_asset']) . '</td>';
    echo '<td>' . htmlspecialchars($row['jenis_pengadaan']) . '</td>';
    echo '<td>' . htmlspecialchars($row['deskripsi_asset']) . '</td>';
    echo '<td>' . htmlspecialchars($row['spesifikasi_merek']) . '</td>';
    echo '<td>' . htmlspecialchars($row['alasan_pengajuan']) . '</td>';
    echo '<td class="center">' . number_format($row['jumlah'], 0, ',', '.') . '</td>';
    echo '<td class="center">' . htmlspecialchars($row['satuan']) . '</td>';
    echo '<td class="center">' . htmlspecialchars($row['tingkat_kepentingan']) . '</td>';
    echo '<td class="center">' . date('d/m/Y', strtotime($row['tanggal_dibutuhkan'])) . '</td>';
    echo '<td>' . htmlspecialchars($row['lokasi_asset']) . '</td>';
    echo '<td>' . htmlspecialchars($row['detail_lokasi']) . '</td>';
    echo '<td class="currency">Rp ' . number_format($row['estimasi_harga'], 0, ',', '.') . '</td>';
    echo '<td class="center">' . htmlspecialchars($row['status']) . '</td>';
    echo '<td>' . htmlspecialchars($row['catatan_approval']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_approver']) . '</td>';
    echo '<td class="center">' . ($row['approved_at'] ? date('d/m/Y H:i', strtotime($row['approved_at'])) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['catatan_tambahan']) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Summary statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'Disetujui' THEN estimasi_harga ELSE 0 END) as total_approved_budget
FROM asset_request ar $whereClause";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

echo '<br><br>';
echo '<h3>RINGKASAN DATA</h3>';
echo '<table style="width: 50%;">';
echo '<tr><td><strong>Total Pengajuan</strong></td><td class="right"><strong>' . $stats['total'] . '</strong></td></tr>';
echo '<tr><td>Status Pending</td><td class="right">' . $stats['pending'] . '</td></tr>';
echo '<tr><td>Status Disetujui</td><td class="right">' . $stats['approved'] . '</td></tr>';
echo '<tr><td>Status Ditolak</td><td class="right">' . $stats['rejected'] . '</td></tr>';
echo '<tr><td><strong>Total Budget Disetujui</strong></td><td class="right currency"><strong>Rp ' . number_format($stats['total_approved_budget'], 0, ',', '.') . '</strong></td></tr>';
echo '</table>';

echo '<br><br>';
echo '<p style="font-size: 10px; color: #666;">
    <em>Laporan ini digenerate otomatis oleh DF GROUP<br>
    Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . htmlspecialchars($_SESSION['nama_user']) . '</em>
</p>';

echo '</body></html>';

// Close database connection
mysqli_close($conn);
exit();
?>
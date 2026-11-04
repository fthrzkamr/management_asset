<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_management_asset_ga\aset_excel.php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}
// Download Excel cukup butuh izin READ
$auth->requirePermission('management_asset_ga', 'read', 'dashboard.php');

// Label Bulan
$daftar_bulan = [
   '01' => 'Januari',
   '02' => 'Februari',
   '03' => 'Maret',
   '04' => 'April',
   '05' => 'Mei',
   '06' => 'Juni',
   '07' => 'Juli',
   '08' => 'Agustus',
   '09' => 'September',
   '10' => 'Oktober',
   '11' => 'November',
   '12' => 'Desember'
];

// Parameter Filter
$filter_cabang = isset($_GET['cabang']) ? mysqli_real_escape_string($conn, trim($_GET['cabang'])) : '';
$filter_bulan  = isset($_GET['bulan']) ? mysqli_real_escape_string($conn, trim($_GET['bulan'])) : '';
$filter_tahun  = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, trim($_GET['tahun'])) : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_conditions = [];
if (!empty($search)) {
   $where_conditions[] = "(kode_aset LIKE '%$search%' 
                           OR nama_aset LIKE '%$search%' 
                           OR `user` LIKE '%$search%'
                           OR kategori_aset LIKE '%$search%' 
                           OR lokasi LIKE '%$search%' 
                           OR merk LIKE '%$search%')";
}
if (!empty($filter_cabang) && $filter_cabang !== 'Semua Cabang') {
   $where_conditions[] = "lokasi LIKE '%$filter_cabang%'";
}
if (!empty($filter_bulan) && !empty($filter_tahun)) {
   $where_conditions[] = "(
       (MONTH(created_at) = " . (int)$filter_bulan . " AND YEAR(created_at) = " . (int)$filter_tahun . ")
       OR 
       (MONTH(tanggal_pengadaan) = " . (int)$filter_bulan . " AND YEAR(tanggal_pengadaan) = " . (int)$filter_tahun . ")
   )";
} elseif (!empty($filter_bulan)) {
   $where_conditions[] = "(MONTH(created_at) = " . (int)$filter_bulan . " OR MONTH(tanggal_pengadaan) = " . (int)$filter_bulan . ")";
} elseif (!empty($filter_tahun)) {
   $where_conditions[] = "(YEAR(created_at) = " . (int)$filter_tahun . " OR YEAR(tanggal_pengadaan) = " . (int)$filter_tahun . ")";
}
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Suffix nama file sesuai filter
$filename_suffix = '';
if (!empty($filter_tahun)) {
    $filename_suffix .= '_' . $filter_tahun;
}
if (!empty($filter_bulan)) {
    $filename_suffix .= '_' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);
}
if (!empty($filter_cabang)) {
    $filename_suffix .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $filter_cabang);
}

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Data_Asset_GA' . $filename_suffix . '_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Query untuk mengambil data asset GA yang sudah difilter
$query = mysqli_query($conn, "
    SELECT 
        kode_aset,
        nama_aset,
        kategori_aset,
        lokasi,
        lokasi_detail,
        merk,
        spesifikasi,
        jumlah,
        satuan,
        nilai_pengadaan_satuan,
        total_nilai_pengadaan,
        tanggal_pengadaan,
        created_at,
        user,
        kondisi,
        status_kepemilikan,
        keterangan
    FROM asset_ga
    $where_clause
    ORDER BY created_at DESC, kode_aset DESC
");

if (!$query) {
    die('Error: ' . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Asset Departemen GA</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .header-info {
            margin-bottom: 20px;
        }
        .header-info h2 {
            margin: 0;
            color: #333;
        }
        .header-info p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header-info">
        <h2>LAPORAN DATA ASSET - DEPARTEMEN GA</h2>
        <p>Tanggal Export: <?= date('d-m-Y') ?></p>
        <?php if (!empty($filter_cabang)): ?>
            <p><strong>Cabang:</strong> <?= htmlspecialchars($filter_cabang) ?></p>
        <?php endif; ?>
        <?php if (!empty($filter_bulan) || !empty($filter_tahun)): ?>
            <p><strong>Periode Pengadaan:</strong> <?= !empty($filter_bulan) ? ($daftar_bulan[str_pad($filter_bulan, 2, '0', STR_PAD_LEFT)] ?? $filter_bulan) : 'Semua Bulan' ?> <?= !empty($filter_tahun) ? htmlspecialchars($filter_tahun) : 'Semua Tahun' ?></p>
        <?php endif; ?>
        <?php if (!empty($search)): ?>
            <p><strong>Pencarian:</strong> <?= htmlspecialchars($search) ?></p>
        <?php endif; ?>
        <p>Total Data: <?= mysqli_num_rows($query) ?> Asset</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="12%">Kode Asset</th>
                <th width="15%">Nama Asset</th>
                <th width="12%">Kategori</th>
                <th width="10%">Lokasi</th>
                <th width="12%">Lokasi Detail</th>
                <th width="8%">Merk</th>
                <th width="15%">Spesifikasi</th>
                <th width="5%">Jumlah</th>
                <th width="5%">Satuan</th>
                <th width="10%">Harga Satuan</th>
                <th width="10%">Total Harga</th>
                <th width="10%">Tgl Pengadaan</th>
                <th width="12%">Created At</th>
                <th width="10%">User/PJ</th>
                <th width="8%">Kondisi</th>
                <th width="10%">Kepemilikan</th>
                <th width="15%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_keseluruhan = 0;
            
            while ($row = mysqli_fetch_assoc($query)) {
                $tgl_pengadaan = ($row['tanggal_pengadaan'] && $row['tanggal_pengadaan'] != '0000-00-00') ? date('d-m-Y', strtotime($row['tanggal_pengadaan'])) : '-';
                
                $total_keseluruhan += (float)$row['total_nilai_pengadaan'];

                echo "<tr>";
                echo "<td class='center'>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['kode_aset']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_aset']) . "</td>";
                echo "<td>" . htmlspecialchars($row['kategori_aset']) . "</td>";
                echo "<td>" . htmlspecialchars($row['lokasi']) . "</td>";
                echo "<td>" . htmlspecialchars($row['lokasi_detail'] ?: '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['merk'] ?: '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['spesifikasi'] ?: '-') . "</td>";
                echo "<td class='center'>" . number_format($row['jumlah'], 0, ',', '.') . "</td>";
                echo "<td class='center'>" . htmlspecialchars($row['satuan']) . "</td>";
                echo "<td class='right'>" . number_format((float)$row['nilai_pengadaan_satuan'], 0, ',', '.') . "</td>";
                echo "<td class='right'>" . number_format((float)$row['total_nilai_pengadaan'], 0, ',', '.') . "</td>";
                $created_at_val = ($row['created_at'] && $row['created_at'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($row['created_at'])) : '-';
                echo "<td class='center'>" . $tgl_pengadaan . "</td>";
                echo "<td class='center'>" . $created_at_val . "</td>";
                echo "<td>" . htmlspecialchars($row['user'] ?: '-') . "</td>";
                echo "<td class='center'>" . htmlspecialchars($row['kondisi']) . "</td>";
                echo "<td class='center'>" . htmlspecialchars($row['status_kepemilikan'] ?: '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['keterangan'] ?: '-') . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="11" class="right" style="font-weight:bold;">Total Keseluruhan</td>
                <td class="right" style="font-weight:bold;"><?= number_format($total_keseluruhan, 0, ',', '.') ?></td>
                <td colspan="6"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #666;">
        <p><em>Laporan ini dibuat secara otomatis oleh Sistem Manajemen Asset - Departemen GA</em></p>
    </div>
</body>
</html>

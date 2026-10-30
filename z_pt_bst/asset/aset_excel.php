<?php
// filepath: c:\xampp\htdocs\asset_bst\aset_excel.php
include('../../dist/config/config.php');
include_once(__DIR__ . '/../../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}
// Download Excel cukup butuh izin READ
$auth->requirePermission('pt_bst_asset', 'read', 'dashboard.php');

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Data_Asset_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Query untuk mengambil semua data asset
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
        tanggal_pengadaan,
        user,
        kondisi,
        keterangan
    FROM asset_bst 
    ORDER BY nama_aset DESC
");

if (!$query) {
    die('Error: ' . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Asset</title>
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
        <h2>LAPORAN DATA ASSET</h2>
        <p>Tanggal Export: <?= date('d-m-Y') ?></p>
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
                <th width="10%">Tgl Pengadaan</th>
                <th width="10%">User/PJ</th>
                <th width="8%">Kondisi</th>
                <th width="15%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_keseluruhan = 0;
            
            while ($row = mysqli_fetch_assoc($query)) {
                // Format tanggal
                $tgl_pengadaan = $row['tanggal_pengadaan'] ? date('d-m-Y', strtotime($row['tanggal_pengadaan'])) : '-';

                
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
                echo "<td class='center'>" . $tgl_pengadaan . "</td>";
                echo "<td>" . htmlspecialchars($row['user'] ?: '-') . "</td>";
                echo "<td class='center'>" . htmlspecialchars($row['kondisi']) . "</td>";
                echo "<td>" . htmlspecialchars($row['keterangan'] ?: '-') . "</td>";
                echo "</tr>";
            }
            ?>
            

        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #666;">
        <p><em>Laporan ini dibuat secara otomatis oleh Sistem Manajemen Asset</em></p>
    </div>
</body>
</html>
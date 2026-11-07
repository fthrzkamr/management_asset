<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';
date_default_timezone_set('Asia/Jakarta');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'read');

$f_kategori = trim($_GET['kategori'] ?? '');
$f_area     = trim($_GET['area'] ?? '');
$f_status   = trim($_GET['status'] ?? '');

$where = [];
if ($f_kategori !== '') {
    $where[] = "kategori_vendor = '" . mysqli_real_escape_string($conn, $f_kategori) . "'";
}
if ($f_area !== '') {
    $where[] = "area = '" . mysqli_real_escape_string($conn, $f_area) . "'";
}
if ($f_status !== '') {
    $where[] = "status_vendor = '" . mysqli_real_escape_string($conn, $f_status) . "'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "SELECT * FROM master_vendor $whereSql ORDER BY id DESC";
$result = mysqli_query($conn, $query);

$filename = "Master_Database_Vendor_" . date('Y-m-d_His') . ".xls";

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Master Database Vendor</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background-color: #1e40af;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .header-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header-title">MASTER DATABASE VENDOR</div>
    <div style="font-size: 10pt; color: #555; margin-bottom: 12px;">Tanggal Export: <?= date('d/m/Y H:i') ?> | Oleh: <?= htmlspecialchars($_SESSION['nama_user'] ?? 'Admin') ?></div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Vendor</th>
                <th>Nama Vendor / Perusahaan</th>
                <th>Kategori Vendor</th>
                <th>Peruntukan Vendor</th>
                <th>Area</th>
                <th>Nama PIC Tim General Affair</th>
                <th>Contact Vendor</th>
                <th>Email</th>
                <th>Alamat</th>
                <th>Status Hukum</th>
                <th>NPWP</th>
                <th>Status PKP</th>
                <th>Nama Bank</th>
                <th>No. Rekening</th>
                <th>Atas Nama Rekening</th>
                <th>Metode Pembayaran</th>
                <th>Lead Time Pengiriman (Hari)</th>
                <th>Status Vendor</th>
                <th>Status MoU</th>
                <th>File MoU</th>
                <th>Keterangan</th>
                <th>Catatan Performa / Review</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while ($row = mysqli_fetch_assoc($result)): 
            ?>
            <tr>
                <td class="center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['kode_vendor'] ?? '') ?></td>
                <td><strong><?= htmlspecialchars($row['nama_vendor'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($row['kategori_vendor'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['peruntukan_vendor'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['area'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['pic_ga'] ?? '') ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['kontak_vendor'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['alamat'] ?? '') ?></td>
                <td class="center"><?= htmlspecialchars($row['status_hukum'] ?? '') ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['npwp'] ?? '') ?></td>
                <td class="center"><?= htmlspecialchars($row['status_pkp'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['nama_bank'] ?? '') ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['no_rekening'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['atas_nama_rekening'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['metode_pembayaran'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['lead_time'] ?? '') ?></td>
                <td class="center"><?= htmlspecialchars($row['status_vendor'] ?? '') ?></td>
                <td class="center"><?= htmlspecialchars($row['status_mou'] ?? '') ?></td>
                <td><?= !empty($row['file_mou']) ? htmlspecialchars($row['file_mou']) : '-' ?></td>
                <td><?= htmlspecialchars($row['keterangan'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['review_performa'] ?? '') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>


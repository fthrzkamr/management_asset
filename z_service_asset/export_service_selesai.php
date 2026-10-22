<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\export_service_selesai.php
include('../dist/config/config.php');

session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// Filter tanggal dari parameter GET
$tanggal_dari = $_GET['dari'] ?? date('Y-m-01');
$tanggal_sampai = $_GET['sampai'] ?? date('Y-m-d');

$where_tanggal = "";
if ($tanggal_dari && $tanggal_sampai) {
    $where_tanggal = "AND DATE(s.tanggal_selesai) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
}

// Query untuk service selesai
$query = mysqli_query($conn, "
    SELECT 
        s.id_service,
        s.no_service,
        s.kode_aset,
        s.tanggal_service,
        s.tanggal_selesai,
        s.jenis_service,
        s.biaya_service,
        s.tindakan,
        s.keluhan,
        a.nama_aset,
        a.merk,
        a.kategori_aset,
        a.lokasi,
        CASE 
            WHEN s.status_service = 'Selesai' AND s.tanggal_selesai IS NOT NULL 
            THEN DATEDIFF(s.tanggal_selesai, s.tanggal_service)
            ELSE DATEDIFF(CURDATE(), s.tanggal_service)
        END as lama_service
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset = a.kode_aset
    WHERE s.status_service = 'Selesai' $where_tanggal
    ORDER BY s.tanggal_selesai DESC
");

// Hitung total biaya
$total_biaya_query = mysqli_query($conn, "
    SELECT SUM(biaya_service) as total 
    FROM service_asset s
    WHERE s.status_service = 'Selesai' $where_tanggal
");
$total_biaya = mysqli_fetch_assoc($total_biaya_query)['total'] ?? 0;
$total_service = mysqli_num_rows($query);

// Generate filename
$filename = 'Report_Service_Selesai_' . date('Ymd_His') . '.xls';

// Set headers untuk Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Start Excel output
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="ProgId" content="Excel.Sheet">
    <meta name="Generator" content="Microsoft Excel 11">
    <title>Report Service Selesai</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }
        
        .header {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            background-color: #f8f9fa;
            padding: 10px;
            border: 1px solid #000;
        }
        
        .sub-header {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            background-color: #f8f9fa;
            padding: 5px;
            border: 1px solid #000;
        }
        
        .summary {
            font-weight: bold;
            background-color: #fff3cd;
            padding: 5px;
            border: 1px solid #000;
            margin: 10px 0;
        }
        
        th {
            background-color: #28a745;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            border: 1px solid #000;
            white-space: nowrap;
        }
        
        td {
            padding: 5px;
            border: 1px solid #000;
            vertical-align: top;
            white-space: nowrap;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .number { 
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .total-row {
            background-color: #17a2b8;
            color: white;
            font-weight: bold;
        }
        
        .footer {
            font-size: 9pt;
            color: #666;
            margin-top: 20px;
            padding: 10px;
            border-top: 1px solid #ccc;
        }
        
        .wrap-text {
            white-space: normal;
            word-wrap: break-word;
            max-width: 200px;
        }
    </style>
</head>
<body>
    <!-- Header Document -->
    <table style="width: 100%; margin-bottom: 20px;">
        <tr>
            <td colspan="14" class="header">
                LAPORAN SERVICE ASSET SELESAI<br>
                PT. DUA FARMA GROUP
            </td>
        </tr>
        <tr>
            <td colspan="14" class="sub-header">
                Periode: <?= date('d/m/Y', strtotime($tanggal_dari)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_sampai)) ?><br>
                Dicetak pada: <?= date('d/m/Y') ?>
            </td>
        </tr>
    </table>

    <!-- Data Table -->
    <?php if ($total_service > 0): ?>
    <table>
        <thead>
            <tr>
                <th width="30">No</th>
                <th width="120">No. Service</th>
                <th width="100">Kode Asset</th>
                <th width="200">Nama Asset</th>
                <th width="100">Merk</th>
                <th width="100">Kategori</th>
                <th width="150">Lokasi</th>
                <th width="100">Jenis Service</th>
                <th width="100">Tgl Service</th>
                <th width="100">Tgl Selesai</th>
                <th width="80">Lama Service</th>
                <th width="120">Biaya Service</th>
                <th width="250">Keluhan</th>
                <th width="250">Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            // Reset query pointer
            mysqli_data_seek($query, 0);
            while ($row = mysqli_fetch_assoc($query)): 
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= htmlspecialchars($row['no_service']) ?></td>
                <td class="text-center"><?= htmlspecialchars($row['kode_aset']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['nama_aset']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['merk'] ?: '-') ?></td>
                <td class="text-left"><?= htmlspecialchars($row['kategori_aset']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['lokasi']) ?></td>
                <td class="text-left"><?= htmlspecialchars($row['jenis_service']) ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal_service'])) ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal_selesai'])) ?></td>
                <td class="text-center"><?= $row['lama_service'] ?> hari</td>
                <td class="number">Rp <?= number_format($row['biaya_service'], 0, ',', '.') ?></td>
                <td class="wrap-text"><?= htmlspecialchars($row['keluhan'] ?: '-') ?></td>
                <td class="wrap-text"><?= htmlspecialchars($row['tindakan'] ?: '-') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="11" class="text-center"><strong>TOTAL</strong></td>
                <td class="number"><strong>Rp <?= number_format($total_biaya, 0, ',', '.') ?></strong></td>
                <td colspan="2" class="text-center"><strong>-</strong></td>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
    <table>
        <tr>
            <td colspan="15" style="text-align: center; padding: 20px; font-weight: bold; color: #dc3545;">
                Tidak ada data service selesai pada periode yang dipilih
            </td>
        </tr>
    </table>
    <?php endif; ?>

</body>
</html>
<?php
// Cleanup
mysqli_free_result($query);
mysqli_free_result($total_biaya_query);
exit();
?>
<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

/**
 * Format number safely, handling empty or non-numeric values
 * @param mixed $value Value to format
 * @param int $decimals Number of decimal points
 * @return string Formatted number
 */
function safeNumberFormat($value, $decimals = 0)
{
    if ($value === null || $value === '') {
        return number_format(0, $decimals, ',', '.');
    }
    if (!is_numeric($value)) {
        $cleanValue = preg_replace('/[^0-9.]/', '', $value);
        if (!is_numeric($cleanValue)) {
            return number_format(0, $decimals, ',', '.');
        }
        $value = $cleanValue;
    }
    return number_format((float)$value, $decimals, ',', '.');
}


$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('pengajuan_asset', 'read');

$no_ppx = $_GET['no_ppx'] ?? '';

if (!empty($no_ppx)) {
    $no_ppx = trim($no_ppx);
    $no_ppx_safe = mysqli_real_escape_string($conn, $no_ppx);

    $query_ppx = "SELECT pr.*, u.nama_user as nama_pengaju 
                  FROM ppx_request pr
                  LEFT JOIN user_dfm u ON pr.user_id = u.id_user
                  WHERE pr.no_ppx = '$no_ppx_safe'
                  LIMIT 1";

    $result_ppx = mysqli_query($conn, $query_ppx);

    if (!$result_ppx || mysqli_num_rows($result_ppx) === 0) {
        echo "<script>alert('Data PPX tidak ditemukan!'); window.close();</script>";
        exit();
    }

    $ppx_data = mysqli_fetch_assoc($result_ppx);

    $query_detail = "SELECT * FROM ppx_request_detail 
                     WHERE no_ppx = '$no_ppx_safe'
                     ORDER BY no_ppx_detail";

    $result_detail = mysqli_query($conn, $query_detail);

    $service_info = null;
    $service_items = [];
    if (!empty($ppx_data['no_service']) || !empty($ppx_data['no_pengajuan_service'])) {
        $no_service = !empty($ppx_data['no_service']) ? $ppx_data['no_service'] : $ppx_data['no_pengajuan_service'];
        $no_service_safe = mysqli_real_escape_string($conn, $no_service);
        $query_service = "SELECT s.*, a.nama_aset, a.kategori_aset, a.merk, a.lokasi
                          FROM service_asset s
                          LEFT JOIN asset_dfm a ON s.kode_aset COLLATE utf8mb4_unicode_ci = a.kode_aset COLLATE utf8mb4_unicode_ci
                          WHERE s.no_service = '$no_service_safe'
                          ORDER BY s.tanggal_service";
        $result_service = mysqli_query($conn, $query_service);
        if ($result_service && mysqli_num_rows($result_service) > 0) {
            while ($row = mysqli_fetch_assoc($result_service)) {
                $service_items[] = $row;
            }
            $service_info = [
                'no_service' => $no_service,
                'jumlah_asset' => count($service_items),
                'total_biaya' => array_sum(array_column($service_items, 'biaya_service'))
            ];
        }
    }
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
        <style>
            @media print {
                .no-print { display: none !important; }
                @page { margin: 1cm; size: A4; }
            }
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 20px; color: #000; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px; }
            .company-name { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
            .document-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
            .print-info { font-size: 11px; color: #666; }
            .info-section { margin-bottom: 25px; }
            .section-title { font-size: 14px; font-weight: bold; margin-bottom: 15px; padding: 8px 0; border-bottom: 1px solid #ccc; background-color: #f5f5f5; padding-left: 10px; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .info-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
            .info-table td table td { border: none; padding: 6px 8px; }
            .info-table>tr>td:first-child, .info-table tr>td:first-child { border-left-width: 0.6px; }
            .info-table>tr>td:last-child, .info-table tr>td:last-child { border-right-width: 0.6px; }
            .info-table td table td:first-child { width: 140px; font-weight: bold; padding-right: 8px; vertical-align: top; }
            .info-table td table td:last-child { padding-left: 6px; }
            .detail-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .detail-table th, .detail-table td { border: 1px solid #000; padding: 6px; text-align: left; font-size: 11px; }
            .detail-table th { font-weight: bold; text-align: center; background-color: #f5f5f5; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-bold { font-weight: bold; }
            .total-row { background-color: #f0f8ff; font-weight: bold; }
            .footer { margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
            .btn-print { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 10px; }
            .btn-print:hover { background-color: #0056b3; }
            .btn-close { background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 10px; }
            .btn-close:hover { background-color: #545b62; }
            .print-buttons { text-align: center; margin-bottom: 20px; }
        </style>
    </head>

    <body>
        <div class="print-buttons no-print">
            <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print Laporan</button>
            <button type="button" class="btn-close" onclick="tutupHalaman()"><i class="fas fa-times"></i> Tutup</button>
        </div>

        <div class="header">
            <div class="company-name">DF GROUP</div>
            <div class="document-title">DETAIL PENGAJUAN PPX</div>
        </div>

        <div class="info-section">
            <div class="section-title">INFORMASI PPX : <?= htmlspecialchars($ppx_data['no_ppx']) ?></div>
            <table class="info-table">
                <tr>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr><td class="info-label">Nama :</td><td><?= htmlspecialchars($ppx_data['nama_pic']) ?></td></tr>
                            <tr><td class="info-label">Jabatan :</td><td><?= htmlspecialchars($ppx_data['jabatan']) ?></td></tr>
                            <tr><td class="info-label">Divisi :</td><td><?= htmlspecialchars($ppx_data['divisi']) ?></td></tr>
                            <tr><td class="info-label">Cabang :</td><td><?= htmlspecialchars($ppx_data['cabang']) ?></td></tr>
                            <tr><td class="info-label">Tanggal Pengajuan :</td><td><?= $ppx_data['tanggal_pengajuan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) : '-' ?></td></tr>
                            <tr><td class="info-label">Tanggal Dibutuhkan :</td><td><?= $ppx_data['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) : '-' ?></td></tr>
                        </table>
                    </td>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr><td class="info-label">Tingkat Kepentingan :</td><td><?= htmlspecialchars($ppx_data['tingkat_kepentingan']) ?></td></tr>
                            <tr><td class="info-label">Status :</td><td><?= htmlspecialchars($ppx_data['status'] === 'Leader' ? 'Kacap/Manager' : $ppx_data['status']) ?></td></tr>
                            <tr><td class="info-label">Uang Muka :</td><td>Rp <?= safeNumberFormat($ppx_data['uang_muka']) ?></td></tr>
                            <tr><td class="info-label">Nominal Realisasi :</td><td>Rp <?= safeNumberFormat($ppx_data['nominal_realisasi']) ?></td></tr>
                            <tr><td class="info-label">Total Nominal :</td><td>Rp <?= safeNumberFormat($ppx_data['nominal_ppx']) ?></td></tr>
                        </table>
                    </td>
                </tr>

                <?php if ($service_info): ?>
                <tr>
                    <td colspan="2" style="vertical-align: top; background-color: #fff3cd; border: 2px solid #ffc107;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr><td colspan="2" style="font-weight: bold; color: #856404; padding: 8px;"> <i class="fas fa-wrench"></i> RELASI DENGAN SERVICE ASSET</td></tr>
                            <tr><td class="info-label" style="width: 140px;">No. Service :</td><td style="font-weight: bold;"><?= htmlspecialchars($service_info['no_service']) ?></td></tr>
                            <tr><td class="info-label">Jumlah Asset :</td><td><?= $service_info['jumlah_asset'] ?> asset</td></tr>
                            <tr><td class="info-label">Total Biaya Service :</td><td>Rp <?= safeNumberFormat($service_info['total_biaya']) ?></td></tr>
                        </table>
                    </td>
                </tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2" style="vertical-align: top;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr><td class="info-label">Alasan Pengajuan :</td><td><?= nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) ?></td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Detail items -->
            <?php
            echo '<table class="detail-table">';
            echo '<thead><tr><th>No</th><th>No. Akun</th><th>Rincian</th><th>Qty</th><th>Harga Satuan</th><th>Total</th></tr></thead>';
            echo '<tbody>';
            $i = 1;
            $sumDetail = 0;
            while ($row = mysqli_fetch_assoc($result_detail)) {
                echo '<tr>';
                echo '<td class="text-center">' . $i++ . '</td>';
                echo '<td>' . htmlspecialchars($row['no_akun']) . '</td>';
                echo '<td>' . htmlspecialchars($row['rincian']) . '</td>';
                echo '<td class="text-center">' . (int)$row['jumlah'] . ' ' . htmlspecialchars($row['satuan']) . '</td>';
                echo '<td class="text-right">Rp ' . number_format($row['harga_satuan'], 0, ',', '.') . '</td>';
                echo '<td class="text-right">Rp ' . number_format($row['total_harga'], 0, ',', '.') . '</td>';
                echo '</tr>';
                $sumDetail += (int)$row['total_harga'];
            }
            echo '</tbody>';
            echo '<tfoot><tr class="total-row"><td colspan="5" class="text-right">TOTAL</td><td class="text-right">Rp ' . number_format($sumDetail, 0, ',', '.') . '</td></tr></tfoot>';
            echo '</table>';
            ?>

        </div>

        <div class="footer">
            <p style="font-size:10px;color:#666">Dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh: <?= htmlspecialchars($_SESSION['nama_user']) ?></p>
        </div>

    </body>

    </html>

<?php
    mysqli_close($conn);
    exit();
}

// Jika tidak ada no_ppx, bisa men-generate laporan ringkasan semua pengajuan (tidak digunakan sekarang)
?>

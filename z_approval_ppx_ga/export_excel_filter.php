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
$auth->requirePermission('pengajuan', 'read');

// Cek apakah ada parameter no_ppx untuk export detail spesifik
$no_ppx = $_GET['no_ppx'] ?? '';

if (!empty($no_ppx)) {
    // EXPORT DETAIL PPX SPESIFIK (seperti detail.php)
    $no_ppx = trim($no_ppx);
    $no_ppx_safe = mysqli_real_escape_string($conn, $no_ppx);

    // Query untuk mendapatkan data PPX Request
    $query_ppx = "SELECT pr.*, pr.uang_muka, pr.nominal_realisasi, u.nama_user as nama_pengaju 
                  FROM ppx_request pr
                  LEFT JOIN user_dfm u ON pr.user_id = u.id_user
                  WHERE pr.no_ppx = '$no_ppx_safe'
                  LIMIT 1";

    $result_ppx = mysqli_query($conn, $query_ppx);

    if (!$result_ppx || mysqli_num_rows($result_ppx) === 0) {
        $_SESSION['error'] = 'Data PPX tidak ditemukan!';
        header("Location: dashboard.php");
        exit();
    }

    $ppx_data = mysqli_fetch_assoc($result_ppx);

    // Query untuk mendapatkan detail items
    $query_detail = "SELECT * FROM ppx_request_detail 
                     WHERE no_ppx = '$no_ppx_safe'
                     ORDER BY no_ppx_detail";

    $result_detail = mysqli_query($conn, $query_detail);

    // Set header untuk download Excel
    $filename = 'PPX_Detail_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $no_ppx) . '_' . date('Y-m-d_H-i-s') . '.xls';
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
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
            .info-table { margin-bottom: 20px; }
            .info-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
            .info-label { font-weight: bold; width: 25%; }
            .detail-table th, .detail-table td { border: 1px solid #000; padding: 8px; text-align: left; }
            .detail-table th { font-weight: bold; text-align: center; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-bold { font-weight: bold; }
            .header-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; text-align: center; }
            .company-name { font-size: 16px; font-weight: bold; margin-bottom: 5px; text-align: center; }
            .print-info { font-size: 12px; margin-bottom: 20px; text-align: center; }
        </style>
    </head>
    <body>';

    // Header laporan
    echo '<div class="header-title">DETAIL PENGAJUAN PPX</div>';
    echo '<div class="company-name">DF GROUP</div>';
    echo '<div class="print-info">Dicetak: ' . date('d/m/Y H:i:s') . '</div>';

    // Informasi PPX (layout: kiri = PIC/jabatan/divisi/cabang/tgl; kanan = tingkat/status/uang_muka/nominal_realisasi/total_nominal)
    echo '<table class="info-table">';
    echo '<tr><td colspan="4" style="font-weight: bold; text-align: center;">INFORMASI PPX: ' . htmlspecialchars($ppx_data['no_ppx']) . '</td></tr>';

    // map status untuk display: Leader -> Kacap/Manager, Manager -> Manager Finance
    $status_ppx = $ppx_data['status'];
    if ($status_ppx === 'Leader') {
        $status_ppx_display = 'Kacap/Manager';
    } elseif ($status_ppx === 'Manager') {
        $status_ppx_display = 'Manager Finance';
    } else {
        $status_ppx_display = $status_ppx;
    }

    echo '<tr>';
    echo '<td class="info-label">Nama PIC</td>';
    echo '<td>' . htmlspecialchars($ppx_data['nama_pic']) . '</td>';
    echo '<td class="info-label">Tingkat Kepentingan</td>';
    echo '<td>' . htmlspecialchars($ppx_data['tingkat_kepentingan']) . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Jabatan</td>';
    echo '<td>' . htmlspecialchars($ppx_data['jabatan']) . '</td>';
    echo '<td class="info-label">Status</td>';
    echo '<td>' . htmlspecialchars($status_ppx_display) . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Divisi</td>';
    echo '<td>' . htmlspecialchars($ppx_data['divisi']) . '</td>';
    echo '<td class="info-label">Uang Muka</td>';
    echo '<td class="text-bold">Rp ' . number_format((int)($ppx_data['uang_muka'] ?? 0), 0, ',', '.') . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Cabang</td>';
    echo '<td>' . htmlspecialchars($ppx_data['cabang']) . '</td>';
    echo '<td class="info-label">Nominal Realisasi</td>';
    echo '<td class="text-bold">Rp ' . number_format((int)($ppx_data['nominal_realisasi'] ?? 0), 0, ',', '.') . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Tanggal Pengajuan</td>';
    echo '<td>' . date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) . '</td>';
    echo '<td class="info-label">Total Nominal</td>';
    echo '<td class="text-bold">Rp ' . number_format((int)($ppx_data['nominal_ppx'] ?? 0), 0, ',', '.') . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Tanggal Dibutuhkan</td>';
    echo '<td>' . date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) . '</td>';
    echo '<td class="info-label"></td>';
    echo '<td></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="info-label">Alasan Pengajuan</td>';
    echo '<td colspan="3">' . nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) . '</td>';
    echo '</tr>';

    echo '</table>';

    // Detail Items
    echo '<br>';
    echo '<div style="font-size: 16px; font-weight: bold; margin-bottom: 10px;">DETAIL ITEM PPX (' . mysqli_num_rows($result_detail) . ' item)</div>';

    echo '<table class="detail-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th width="5%">No</th>';
    echo '<th width="12%">No. Akun</th>';
    echo '<th width="30%">Rincian Item</th>';
    // Deskripsi dipindahkan setelah Rincian
    echo '<th width="20%">Deskripsi</th>';
    echo '<th width="8%">Jumlah</th>';
    echo '<th width="10%">Satuan</th>';
    echo '<th width="15%">Harga Satuan</th>';
    echo '<th width="15%">Total Harga</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if (mysqli_num_rows($result_detail) > 0) {
        $total_all = 0;
        $no = 1;
        
        while ($detail = mysqli_fetch_assoc($result_detail)) {
            $total_all += $detail['total_harga'];
            
            echo '<tr>';
            echo '<td class="text-center">' . $no++ . '</td>';
            echo '<td>' . htmlspecialchars($detail['no_akun']) . '</td>';
            echo '<td>' . htmlspecialchars($detail['rincian']) . '</td>';
            // Tampilkan deskripsi setelah rincian
            echo '<td>' . (!empty($detail['deskripsi']) ? htmlspecialchars($detail['deskripsi']) : '-') . '</td>';
            echo '<td class="text-right">' . number_format($detail['jumlah'], 0, ',', '.') . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($detail['satuan']) . '</td>';
            echo '<td class="text-right">Rp ' . number_format($detail['harga_satuan'], 0, ',', '.') . '</td>';
            echo '<td class="text-right text-bold">Rp ' . number_format($detail['total_harga'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        
        // Total Summary: sesuaikan colspan (7 kolom sebelumnya), total di kolom ke-8
        echo '<tr>';
        echo '<td colspan="7" class="text-right text-bold">TOTAL KESELURUHAN PPX:</td>';
        echo '<td class="text-right text-bold" style="font-size: 14px;">Rp ' . number_format($total_all, 0, ',', '.') . '</td>';
        echo '</tr>';
        
    } else {
        echo '<tr>';
        echo '<td colspan="8" class="text-center">Tidak ada detail item untuk PPX ini</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Footer informasi
    echo '<br>';
    echo '<div style="font-size: 10px; margin-top: 20px;">';
    echo 'Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . htmlspecialchars($_SESSION['nama_user']);
    echo '</div>';

    echo '</body></html>';

} else {
    // EXPORT SUMMARY SEMUA PPX (tanpa detail item)
    
    // Query berdasarkan role
    $whereClause = "";
    if ($auth->isKaryawan()) {
        $whereClause = "WHERE pr.user_id = " . (int)$_SESSION['id_user'];
    }

    // Query summary per PPX
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
        pr.nominal_ppx,
        pr.uang_muka,
        pr.nominal_realisasi,
        u.nama_user as nama_pengaju,
        COUNT(prd.no_ppx_detail) as jumlah_item,
        COALESCE(SUM(prd.total_harga), 0) as total_nominal_detail
    FROM ppx_request pr
    LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
    LEFT JOIN user_dfm u ON pr.user_id = u.id_user
    $whereClause
    GROUP BY pr.no_ppx
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
            th { font-weight: bold; }
            .center { text-align: center; }
            .right { text-align: right; }
            .currency { text-align: right; }
        </style>
    </head>
    <body>';

    echo '<h2 style="text-align: center;">LAPORAN PENGAJUAN PPX</h2>';
    echo '<h4 style="text-align: center;">DF GROUP</h4>';
    echo '<p style="text-align: center;">Dicetak: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<br>';

    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>No. PPX</th>';
    echo '<th>Tanggal Pengajuan</th>';
    echo '<th>Nama PIC</th>';
    echo '<th>Jabatan</th>';
    echo '<th>Divisi</th>';
    echo '<th>Cabang</th>';
    echo '<th>Jumlah Item</th>';
    echo '<th>Uang Muka</th>';
    echo '<th>Nominal Realisasi</th>';
    echo '<th>Total Nominal (Detail)</th>';
    echo '<th>Nominal PPX (field)</th>';
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
        echo '<td class="currency">Rp ' . number_format((int)($row['uang_muka'] ?? 0), 0, ',', '.') . '</td>';
        echo '<td class="currency">Rp ' . number_format((int)($row['nominal_realisasi'] ?? 0), 0, ',', '.') . '</td>';
        echo '<td class="currency">Rp ' . number_format($row['total_nominal_detail'], 0, ',', '.') . '</td>';
        echo '<td class="currency">Rp ' . number_format($row['nominal_ppx'], 0, ',', '.') . '</td>';
        echo '<td>' . htmlspecialchars($row['tingkat_kepentingan']) . '</td>';
        echo '<td class="center">' . ($row['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($row['tanggal_dibutuhkan'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['alasan_pengajuan']) . '</td>';
        
        // map status untuk display: Leader -> Kacap/Manager, Manager -> Manager Finance
        $status_display = $row['status'];
        if ($status_display === 'Leader') {
            $status_display = 'Kacap/Manager';
        } elseif ($status_display === 'Manager') {
            $status_display = 'Manager Finance';
        }
        echo '<td>' . htmlspecialchars($status_display) . '</td>';
        
        echo '<td>' . htmlspecialchars($row['nama_pengaju']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Ringkasan
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN pr.status IN ('Kacap/Manager','Leader') THEN 1 ELSE 0 END) as pending_leader,
        SUM(CASE WHEN pr.status = 'Manager' THEN 1 ELSE 0 END) as pending_manager,
        SUM(CASE WHEN pr.status = 'Finance' THEN 1 ELSE 0 END) as pending_finance,
        SUM(CASE WHEN pr.status = 'Direktur' THEN 1 ELSE 0 END) as pending_direktur,
        SUM(CASE WHEN pr.status = 'Done' THEN 1 ELSE 0 END) as done,
        SUM(CASE WHEN pr.status = 'Revisi' THEN 1 ELSE 0 END) as revisi,
        COALESCE(SUM(prd.total_harga),0) as total_all_detail
    FROM ppx_request pr
    LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
    $whereClause";

    $stats_result = mysqli_query($conn, $stats_query);
    $stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];

    echo '<br><h4>RINGKASAN</h4>';
    echo '<table style="width:50%">';
    echo '<tr><td>Total Pengajuan</td><td class="right">' . ((int)($stats['total'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Pending (Leader)</td><td class="right">' . ((int)($stats['pending_leader'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Pending (Manager)</td><td class="right">' . ((int)($stats['pending_manager'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Pending (Finance)</td><td class="right">' . ((int)($stats['pending_finance'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Disetujui (Done)</td><td class="right">' . ((int)($stats['done'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Revisi</td><td class="right">' . ((int)($stats['revisi'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Total Semua Nominal (detail)</td><td class="right currency">Rp ' . number_format((int)($stats['total_all_detail'] ?? 0), 0, ',', '.') . '</td></tr>';
    echo '</table>';

    echo '<br><p style="font-size:10px;">Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . htmlspecialchars($_SESSION['nama_user']) . '</p>';

    echo '</body></html>';
}

mysqli_close($conn);
exit();
?>

<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\export_excel_filtered.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$auth->requirePermission('pengajuan_asset', 'read');

// Ambil parameter filter dari GET
$start_date = mysqli_real_escape_string($conn, $_GET['start_date'] ?? '');
$end_date = mysqli_real_escape_string($conn, $_GET['end_date'] ?? '');
$status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');
$kategori = mysqli_real_escape_string($conn, $_GET['kategori'] ?? '');

// Build WHERE clause berdasarkan role
$whereClause = "";
$filterConditions = [];

// Filter berdasarkan role
if ($auth->isKoordinator()) {
    $whereClause = "WHERE ar.user_id = " . $_SESSION['id_user'];
}

// Filter tanggal
if (!empty($start_date) && !empty($end_date)) {
    $filterConditions[] = "DATE(ar.tanggal_pengajuan) BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $filterConditions[] = "DATE(ar.tanggal_pengajuan) >= '$start_date'";
} elseif (!empty($end_date)) {
    $filterConditions[] = "DATE(ar.tanggal_pengajuan) <= '$end_date'";
}

// Filter status
if (!empty($status)) {
    $filterConditions[] = "ar.status = '$status'";
}

// Filter kategori
if (!empty($kategori)) {
    $filterConditions[] = "ka.kategori_asset = '$kategori'";
}

// Gabungkan filter conditions
if (!empty($filterConditions)) {
    if (empty($whereClause)) {
        $whereClause = "WHERE " . implode(" AND ", $filterConditions);
    } else {
        $whereClause .= " AND " . implode(" AND ", $filterConditions);
    }
}

// Query utama
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

// Validasi apakah ada data
if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Tidak ada data yang sesuai dengan filter yang dipilih!';
    header("Location: export_filter.php");
    exit();
}

// Buat nama file dengan info filter
$filename_parts = ["Laporan_Pengajuan_Asset"];

if (!empty($start_date) || !empty($end_date)) {
    $period = "";
    if (!empty($start_date) && !empty($end_date)) {
        $period = date('d-m-Y', strtotime($start_date)) . "_sampai_" . date('d-m-Y', strtotime($end_date));
    } elseif (!empty($start_date)) {
        $period = "dari_" . date('d-m-Y', strtotime($start_date));
    } elseif (!empty($end_date)) {
        $period = "sampai_" . date('d-m-Y', strtotime($end_date));
    }
    $filename_parts[] = $period;
}

if (!empty($status)) {
    $filename_parts[] = "Status_" . $status;
}

if (!empty($kategori)) {
    $filename_parts[] = "Kategori_" . str_replace(' ', '_', $kategori);
}

$filename_parts[] = date('Y-m-d_H-i-s');
$filename = implode("_", $filename_parts) . ".xls";

// Set header untuk download Excel
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
        .header { background-color: #d9edf7; font-weight: bold; }
    </style>
</head>
<body>';

echo '<h2 style="text-align: center;">LAPORAN PENGAJUAN ASSET</h2>';
echo '<h3 style="text-align: center;">DF GROUP</h3>';

// Tampilkan info filter
echo '<div style="margin: 20px 0; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">';
echo '<h4>Filter yang Diterapkan:</h4>';
echo '<table style="width: 100%; border: none;">';
echo '<tr><td style="border: none; width: 150px;"><strong>Periode:</strong></td><td style="border: none;">';
if (!empty($start_date) && !empty($end_date)) {
    echo date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
} elseif (!empty($start_date)) {
    echo 'Dari: ' . date('d/m/Y', strtotime($start_date));
} elseif (!empty($end_date)) {
    echo 'Sampai: ' . date('d/m/Y', strtotime($end_date));
} else {
    echo 'Semua Periode';
}
echo '</td></tr>';

echo '<tr><td style="border: none;"><strong>Status:</strong></td><td style="border: none;">' . (!empty($status) ? $status : 'Semua Status') . '</td></tr>';
echo '<tr><td style="border: none;"><strong>Kategori:</strong></td><td style="border: none;">' . (!empty($kategori) ? $kategori : 'Semua Kategori') . '</td></tr>';
echo '<tr><td style="border: none;"><strong>Total Data:</strong></td><td style="border: none;">' . mysqli_num_rows($result) . ' pengajuan</td></tr>';
echo '<tr><td style="border: none;"><strong>Diexport pada:</strong></td><td style="border: none;">' . date('d/m/Y H:i:s') . '</td></tr>';
echo '<tr><td style="border: none;"><strong>Diexport oleh:</strong></td><td style="border: none;">' . htmlspecialchars($_SESSION['nama_user']) . '</td></tr>';
echo '</table>';
echo '</div>';

echo '<br>';

// Tabel data
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
$total_estimasi = 0;
$count_by_status = ['Pending' => 0, 'Disetujui' => 0, 'Ditolak' => 0];
$count_by_priority = ['Rendah' => 0, 'Sedang' => 0, 'Tinggi' => 0, 'Urgent' => 0];

while ($row = mysqli_fetch_assoc($result)) {
    // Hitung statistik
    $total_estimasi += $row['estimasi_harga'];
    if (isset($count_by_status[$row['status']])) {
        $count_by_status[$row['status']]++;
    }
    if (isset($count_by_priority[$row['tingkat_kepentingan']])) {
        $count_by_priority[$row['tingkat_kepentingan']]++;
    }

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

// Summary statistics berdasarkan data yang difilter
echo '<br><br>';
echo '<h3>RINGKASAN DATA HASIL FILTER</h3>';

echo '<table style="width: 70%;">';
echo '<tr class="header"><td colspan="2"><strong>RINGKASAN BERDASARKAN STATUS</strong></td></tr>';
foreach ($count_by_status as $status_name => $count) {
    echo '<tr><td>' . $status_name . '</td><td class="right">' . $count . ' pengajuan</td></tr>';
}

echo '<tr class="header"><td colspan="2"><strong>RINGKASAN BERDASARKAN PRIORITAS</strong></td></tr>';
foreach ($count_by_priority as $priority_name => $count) {
    if ($count > 0) {
        echo '<tr><td>' . $priority_name . '</td><td class="right">' . $count . ' pengajuan</td></tr>';
    }
}

echo '<tr class="header"><td colspan="2"><strong>RINGKASAN FINANSIAL</strong></td></tr>';
echo '<tr><td>Total Estimasi Budget</td><td class="currency"><strong>Rp ' . number_format($total_estimasi, 0, ',', '.') . '</strong></td></tr>';

// Hitung total budget yang disetujui
$approved_budget_query = "SELECT SUM(estimasi_harga) as total_approved 
                         FROM asset_request ar 
                         LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.kategori_asset
                         $whereClause AND ar.status = 'Disetujui'";
$approved_result = mysqli_query($conn, $approved_budget_query);
$approved_data = mysqli_fetch_assoc($approved_result);
$total_approved = $approved_data['total_approved'] ?? 0;

echo '<tr><td>Budget yang Disetujui</td><td class="currency"><strong>Rp ' . number_format($total_approved, 0, ',', '.') . '</strong></td></tr>';

if ($total_estimasi > 0) {
    $approval_rate = ($total_approved / $total_estimasi) * 100;
    echo '<tr><td>Tingkat Persetujuan Budget</td><td class="right"><strong>' . number_format($approval_rate, 1) . '%</strong></td></tr>';
}

echo '</table>';

echo '<br><br>';
echo '<p style="font-size: 10px; color: #666;">
    <em>Laporan ini digenerate otomatis oleh DF GROUP<br>
    Data yang ditampilkan sesuai dengan filter yang dipilih dan hak akses user<br>
    Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . htmlspecialchars($_SESSION['nama_user']) . '</em>
</p>';

echo '</body></html>';

// Close database connection
mysqli_close($conn);
exit();
?>
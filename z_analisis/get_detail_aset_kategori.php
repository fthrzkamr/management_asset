<?php
ob_start();
session_start();
include('../dist/config/config.php');

// Validasi request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['kategori']) || !isset($_POST['departemen'])) {
    echo '<tr><td colspan="6" class="text-center text-danger">Akses tidak valid.</td></tr>';
    exit;
}

$kategori   = mysqli_real_escape_string($conn, $_POST['kategori']);
$departemen = mysqli_real_escape_string($conn, $_POST['departemen']);

// Tentukan tabel berdasarkan departemen
$tabel = ($departemen === 'IT') ? 'asset_dfm' : 'asset_ga';

// Query data per kategori
$sql = "SELECT kode_aset, nama_aset, lokasi, kondisi, total_nilai_pengadaan 
        FROM $tabel 
        WHERE kategori_aset = '$kategori' 
        ORDER BY nama_aset ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo '<tr><td colspan="6" class="text-center text-danger">Kesalahan sistem: ' . mysqli_error($conn) . '</td></tr>';
    exit;
}

$no = 1;

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Tentukan warna badge kondisi
        $badgeClass = 'badge-secondary';
        $kondisi    = strtoupper(trim($row['kondisi']));
        if ($kondisi === 'BAIK') $badgeClass = 'badge-success';
        else if ($kondisi === 'RUSAK RINGAN' || $kondisi === 'REPAIR') $badgeClass = 'badge-warning';
        else if ($kondisi === 'RUSAK BERAT' || $kondisi === 'RUSAK') $badgeClass = 'badge-danger';
        
        echo '<tr>';
        echo '<td class="text-center">' . $no++ . '</td>';
        echo '<td><strong>' . htmlspecialchars($row['kode_aset']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($row['nama_aset']) . '</td>';
        echo '<td><i class="fas fa-map-marker-alt text-muted mr-1"></i> ' . htmlspecialchars($row['lokasi']) . '</td>';
        echo '<td class="text-center"><span class="badge ' . $badgeClass . '">' . htmlspecialchars($row['kondisi']) . '</span></td>';
        echo '<td class="text-right font-weight-bold">Rp ' . number_format($row['total_nilai_pengadaan'], 0, ',', '.') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" class="text-center text-muted"><i class="fas fa-box-open mr-2"></i> Tidak ada aset ditemukan di kategori ini.</td></tr>';
}
?>

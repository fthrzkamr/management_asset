<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('pengajuan_asset', 'read');

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=ppx_export_" . date('Ymd_His') . ".xls");

// Build query similar to dashboard but without pagination
$whereClauses = [];
if (!empty($_SESSION['cabang'])) {
    $cabang_safe = mysqli_real_escape_string($conn, $_SESSION['cabang']);
    $whereClauses[] = "pr.cabang = '$cabang_safe'";
}
// Only show Gudang/Bisnis/Operasional
$whereClauses[] = "pr.divisi IN (2,4,17)";

$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

$query = "SELECT pr.*, u.nama_user as pengaju, d.nama_divisi FROM ppx_request pr 
          LEFT JOIN user_dfm u ON pr.user_id = u.id_user
          LEFT JOIN divisi d ON pr.divisi = d.id_divisi
          $where
          ORDER BY pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);

echo "<table border='1'>";
echo "<tr><th>No PPX</th><th>Pengaju</th><th>Divisi</th><th>Cabang</th><th>Tanggal Pengajuan</th><th>Status</th><th>Nominal</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['no_ppx']) . "</td>";
    echo "<td>" . htmlspecialchars($row['pengaju']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_divisi']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang']) . "</td>";
    echo "<td>" . ($row['tanggal_pengajuan'] ? date('d/m/Y', strtotime($row['tanggal_pengajuan'])) : '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . number_format($row['nominal_ppx'], 0, ',', '.') . "</td>";
    echo "</tr>";
}

echo "</table>";

mysqli_close($conn);
exit();
?>
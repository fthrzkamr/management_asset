<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_operasional\dashboard.php
// Copy of finance dashboard adjusted for Manager Operasional
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';


$auth = new AuthHelper($conn);

// Only allow Manager Operasional or users with process permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if (!$auth->isOperationalManager() && !$auth->canProcessPPX()) {
    die("Access Denied: Anda tidak memiliki akses ke halaman ini.");
}

// status labels
$status_labels = [
    'Leader'   => 'Kacap/Manager',
    'Manager'  => 'Manager Finance',
    'Finance'  => 'Manager Operasional', // Status untuk approval Manager Operasional (Gudang, Bisnis, Operasional)
    'Direktur' => 'Direktur',
    'Selesai'  => 'Selesai',
    'Uang Muka'=> 'Uang Muka',
    'Revisi'   => 'Revisi'
];

// Filter: Manager Operasional handles Gudang(2), Bisnis(4), Operasional(17)
// Manager Operasional bisa melihat SEMUA cabang untuk divisi ini
$whereClause = "WHERE pr.status IN ('Operasional','Selesai') AND pr.divisi IN (2, 4, 17)";

// Jika user adalah Koordinator, hanya tampilkan PPX miliknya sendiri
if ($auth->isKoordinator()) {
    $whereClause .= " AND pr.user_id = " . $_SESSION['id_user'];
}

$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi as divisi_id,
    d.nama_divisi as divisi,
    pr.departemen as departemen_id,
    dep.nama_departemen as departemen,
    pr.cabang,
    pr.status,
    pr.nominal_ppx,
    pr.nama_bank,
    pr.nama_rek,
    pr.no_rek,
    u.nama_user as nama_pengaju,
    COUNT(prd.no_ppx_detail) AS total_items,
    COALESCE(SUM(prd.total_harga), 0) AS total_nominal_detail,
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
$whereClause
GROUP BY pr.no_ppx
ORDER BY pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) die('Query Error: ' . mysqli_error($conn));

// Stats
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Leader' THEN 1 END) as pending_leader,
    COUNT(CASE WHEN status = 'Manager' THEN 1 END) as pending_manager,
    COUNT(CASE WHEN status = 'Finance' THEN 1 END) as pending_finance,
    COUNT(CASE WHEN status = 'Direktur' THEN 1 END) as pending_direktur,
    COUNT(CASE WHEN status = 'Selesai Realisasi' THEN 1 END) as selesai_realisasi,
    COUNT(CASE WHEN status = 'Selesai' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'Uang Muka' THEN 1 END) as uang_muka,
    COUNT(CASE WHEN status = 'Revisi' THEN 1 END) as rejected
FROM ppx_request pr
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
$whereClause";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Approval PPX - Manager Operasional</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <h3 class="page-title">Dashboard Approval PPX (Manager Operasional)</h3>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="ppxTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No. PPX</th>
                                        <th>Tanggal</th>
                                        <th>Pengaju</th>
                                        <th>Divisi</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): $n=1; while($row=mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?= $n++ ?></td>
                                            <td><?= htmlspecialchars($row['no_ppx']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                                            <td><?= htmlspecialchars($row['nama_pic']) ?></td>
                                            <td><?= htmlspecialchars($row['divisi']) ?></td>
                                            <td><?= $row['total_items'] ?> item</td>
                                            <td>Rp <?= number_format($row['total_nominal_detail'],0,',','.') ?></td>
                                            <td>
                                                <a href="detail.php?no_ppx=<?= urlencode($row['no_ppx']) ?>" class="btn btn-sm btn-info">Detail</a>
                                                <?php if ($row['status'] === 'Operasional' && $auth->isOperationalManager()): ?>
                                                    <button class="btn btn-sm btn-success" onclick="location.href='process_approve.php?no_ppx=<?= urlencode($row['no_ppx']) ?>&action=approve'">Approve</button>
                                                <?php elseif ($row['status'] === 'Selesai'): ?>
                                                    <span class="badge badge-success">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="8" class="text-center">Tidak ada data pengajuan PPX</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php include __DIR__ . '/../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(function(){ $('#ppxTable').DataTable({"order":[], "pageLength":10}); });
    </script>
</body>
</html>
<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_kacab\dashboard.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

// Mapping label status
$status_labels = [
    'Leader'      => 'Kacap/Manager',
    'Kacab'       => 'Kepala Cabang',
    'Manager'     => 'Manager Finance',
    'Operasional' => 'Manager Operasional',
    'HR'          => 'Manager HR',
    'Finance'     => 'GA (General Affairs)',
    'Direktur'    => 'Direktur',
    'Done'        => 'Selesai',
    'Selesai'     => 'Selesai',
    'Uang Muka'   => 'Uang Muka',
    'Revisi'      => 'Revisi'
];

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - Approval PPX Kacab
$auth->requirePermission('approval_ppx_kacab', 'approve');

// Get user's cabang
$user_id = $_SESSION['id_user'];
$user_query = "SELECT u.id_cabang, mc.nama_cabang 
               FROM user_dfm u 
               LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_cabang_id = $user_data['id_cabang'] ?? null;
$user_cabang_nama = $user_data['nama_cabang'] ?? null;

if (empty($user_cabang_id) || empty($user_cabang_nama)) {
    $_SESSION['error'] = 'Data cabang tidak ditemukan!';
    header("Location: ../index.php");
    exit();
}

// Query untuk mendapatkan PPX dengan status='Kacab' atau 'Selesai'
// Filter: divisi Finance AP (9), cabang sesuai user (menggunakan nama_cabang)
$whereClause = "WHERE pr.status IN ('Kacab', 'Selesai') 
                AND pr.divisi = 9 
                AND pr.cabang = '" . mysqli_real_escape_string($conn, $user_cabang_nama) . "'";

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
    pr.cabang_user,
    pr.status,
    pr.nominal_ppx,
    pr.flow_type,
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
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Kacab' THEN 1 END) as pending_kacab,
    COUNT(CASE WHEN status = 'Selesai' THEN 1 END) as approved
FROM ppx_request pr 
WHERE pr.divisi = 9 AND pr.cabang = '" . mysqli_real_escape_string($conn, $user_cabang_nama) . "'";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Approval PPX - Kepala Cabang</title>
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
                    <h3 class="page-title">Dashboard Approval PPX (Kepala Cabang)</h3>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h5>
                            <i class="fas fa-list fa-sm"></i> Daftar PPX Menunggu Approval
                        </h5>
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
                                            <td>Rp <?= number_format($row['total_nominal'],0,',','.') ?></td>
                                            <td>
                                                <a href="detail.php?no_ppx=<?= urlencode($row['no_ppx']) ?>" class="btn btn-sm btn-info">Detail</a>
                                                <?php if ($row['status'] === 'Kacab' && $auth->isKacab()): ?>
                                                    <form method="POST" action="process_approve.php" style="display:inline;">
                                                        <input type="hidden" name="no_pengajuan" value="<?= htmlspecialchars($row['no_ppx']) ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Apakah Anda yakin ingin menyetujui PPX ini?')">Approve</button>
                                                    </form>
                                                <?php elseif ($row['status'] === 'Selesai'): ?>
                                                    <span class="badge badge-success">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
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
        $(function(){ $('#ppxTable').DataTable({"order":[], "pageLength":10, "language": {"emptyTable": "Tidak ada data pengajuan PPX"}}); });
    </script>
</body>
</html>

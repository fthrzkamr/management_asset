<?php
// filepath: z_approval_ppx_asmen/dashboard.php
// Approval PPX oleh Asmen Finance — khusus PPX Regular dari divisi Finance AP & Finance di Jakarta
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$auth->requirePermission('approval_ppx_asmen', 'approve');

$status_labels = [
    'Asmen Finance' => 'Menunggu Asmen Finance',
    'Uang Muka'     => 'Uang Muka',
    'Realisasi'     => 'Menunggu Verifikasi Realisasi',
    'Selesai'       => 'Selesai',
    'Revisi'        => 'Revisi',
];

// Tampilkan semua PPX yang masuk ke tahap Asmen Finance + histori milik divisi Finance
$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi as divisi_id,
    d.nama_divisi as divisi,
    pr.cabang,
    pr.status,
    pr.request_type,
    pr.nominal_ppx,
    u.nama_user as nama_pengaju,
    COUNT(prd.no_ppx_detail) AS total_items,
    COALESCE(SUM(prd.total_harga), 0) AS total_nominal_detail,
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
WHERE pr.status IN ('Asmen Finance', 'Uang Muka', 'Realisasi', 'Selesai', 'Revisi')
  AND pr.request_type = 'Regular'
GROUP BY pr.no_ppx
ORDER BY 
    FIELD(pr.status, 'Asmen Finance', 'Realisasi', 'Uang Muka', 'Selesai', 'Revisi'),
    pr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);
if (!$result) die("Query Error: " . mysqli_error($conn));

$pending_asmen = 0;
$rows_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['status'] === 'Asmen Finance') $pending_asmen++;
    $rows_data[] = $row;
}

$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval PPX Asmen Finance</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <style>
        .btn-action { margin: 1px; padding: 4px 8px; font-size: 11px; }
        .table td, .table th { vertical-align: middle; }
        .badge-sm { font-size: 0.7rem; }
        .badge-asmen { background-color: #8b5cf6; color: #fff; }
        .badge-realisasi { background-color: #f97316; color: #fff; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php
    if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php';
    ?>
    <div class="page-wrapper">
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <h3 class="page-title mb-2">
                            <i class="fas fa-user-tie text-purple"></i>
                            Approval PPX &mdash; Asmen Finance
                        </h3>
                        <ul class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Approval PPX Asmen Finance</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded p-2 mr-3" style="background:#ede9fe">
                                <i class="fas fa-clock text-purple" style="font-size:1.4rem; color:#8b5cf6"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold" style="font-size:1.5rem"><?= $pending_asmen ?></div>
                                <div class="text-muted small">Menunggu Persetujuan Anda</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded p-2 mr-3" style="background:#fff7ed">
                                <i class="fas fa-receipt" style="font-size:1.4rem; color:#f97316"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold" style="font-size:1.5rem">
                                    <?= count(array_filter($rows_data, fn($r) => $r['status'] === 'Realisasi')) ?>
                                </div>
                                <div class="text-muted small">Menunggu Verifikasi Realisasi</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded p-2 mr-3" style="background:#f0fdf4">
                                <i class="fas fa-check-double" style="font-size:1.4rem; color:#22c55e"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold" style="font-size:1.5rem">
                                    <?= count(array_filter($rows_data, fn($r) => $r['status'] === 'Selesai')) ?>
                                </div>
                                <div class="text-muted small">Selesai</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list fa-sm"></i> Daftar Pengajuan PPX Regular
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="ppxTable" class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>No. PPX</th>
                                            <th>Tanggal</th>
                                            <th>Pengaju</th>
                                            <th>Divisi</th>
                                            <th>Cabang</th>
                                            <th>Total Nominal</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($rows_data) > 0): ?>
                                            <?php $no = 1; foreach ($rows_data as $row): ?>
                                                <tr>
                                                    <td><small class="font-weight-bold"><?= $no++ ?></small></td>
                                                    <td><small class="font-weight-bold"><?= htmlspecialchars($row['no_ppx']) ?></small></td>
                                                    <td><small><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['nama_pic']) ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['divisi']) ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['cabang'] ?: '-') ?></small></td>
                                                    <td><small class="font-weight-bold">Rp <?= number_format($row['total_nominal_detail'] ?: $row['nominal_ppx'], 0, ',', '.') ?></small></td>
                                                    <td>
                                                        <?php
                                                        $badge = 'secondary';
                                                        $extra = '';
                                                        switch ($row['status']) {
                                                            case 'Asmen Finance': $badge = ''; $extra = 'badge-asmen'; break;
                                                            case 'Uang Muka':     $badge = 'success'; break;
                                                            case 'Realisasi':     $badge = ''; $extra = 'badge-realisasi'; break;
                                                            case 'Selesai':       $badge = 'success'; break;
                                                            case 'Revisi':        $badge = 'danger'; break;
                                                        }
                                                        $label = $status_labels[$row['status']] ?? $row['status'];
                                                        ?>
                                                        <span class="badge <?= $badge ? "badge-$badge" : '' ?> <?= $extra ?> badge-sm">
                                                            <?= htmlspecialchars($label) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="../z_pengajuan_ppx/detail.php?no_ppx=<?= urlencode($row['no_ppx']) ?>"
                                                               class="btn btn-outline-info btn-action" title="Detail">
                                                                <i class="fas fa-eye fa-xs"></i>
                                                            </a>
                                                            <?php if ($row['status'] === 'Asmen Finance'): ?>
                                                                <button type="button"
                                                                    class="btn btn-outline-success btn-action"
                                                                    onclick="processAction('<?= htmlspecialchars($row['no_ppx']) ?>', 'approve')"
                                                                    title="Approve">
                                                                    <i class="fas fa-check fa-xs"></i>
                                                                </button>
                                                                <button type="button"
                                                                    class="btn btn-outline-danger btn-action"
                                                                    onclick="processAction('<?= htmlspecialchars($row['no_ppx']) ?>', 'revisi')"
                                                                    title="Revisi">
                                                                    <i class="fas fa-undo fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($layout_dir && file_exists($layout_dir . '/footer.php')) include $layout_dir . '/footer.php'; ?>
        </div>
    </div>
</div>

<?php if ($layout_dir && file_exists($layout_dir . '/confirm_modal.php')) include $layout_dir . '/confirm_modal.php'; ?>

<?php if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php'; ?>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#ppxTable').DataTable({
        ordering: false,
        pageLength: 25,
        language: {
            search: "Cari:", lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            zeroRecords: "Tidak ada data", emptyTable: "Tidak ada data pengajuan PPX", paginate: { previous: "&laquo;", next: "&raquo;" }
        }
    });
});

function processAction(no_ppx, action) {
    var isApprove = action === 'approve';
    var title = isApprove ? 'Konfirmasi Setujui' : 'Konfirmasi Revisi';
    var label = isApprove ? 'MENYETUJUI' : 'MENGEMBALIKAN KE REVISI';
    var message = 'Anda akan ' + label + ' pengajuan PPX: <strong>' + no_ppx + '</strong>\n\nLanjutkan?';
    
    showConfirm(title, message, function() {
        $('#no_pengajuan_input').val(no_ppx);
        $('#action_input').val(action);
        $('#approveForm').submit();
    }, null, isApprove ? 'confirm' : 'delete');
}
</script>

<form id="approveForm" method="POST" action="process_approve.php" style="display:none;">
    <input type="hidden" name="no_pengajuan" id="no_pengajuan_input" value="">
    <input type="hidden" name="action" id="action_input" value="approve">
</form>
</body>
</html>

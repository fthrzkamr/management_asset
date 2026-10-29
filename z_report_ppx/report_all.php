<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_report_ppx\report_all.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission report_ppx_all read
$auth->requirePermission('report_ppx_all', 'read');

$status_labels = [
    'Draft'     => 'Draft',
    'Leader'    => 'Kacap/Manager',
    'Kacab'     => 'Kepala Cabang',
    'Asmen Finance' => 'Asmen Finance',
    'Manager'   => 'Manager Finance',
    'Finance'   => 'GA (General Affairs)',
    'Direktur'  => 'Direktur',
    'Uang Muka' => 'Uang Muka',
    'Done'      => 'Selesai',
    'Selesai'   => 'Selesai',
    'Revisi'    => 'Revisi',
    'Ditolak'   => 'Ditolak'
];

$daftar_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_cabang = isset($_GET['cabang']) ? $_GET['cabang'] : '';
$filter_type = isset($_GET['request_type']) ? $_GET['request_type'] : '';

$where_clauses = [
    "MONTH(pr.tanggal_pengajuan) = ?",
    "YEAR(pr.tanggal_pengajuan) = ?",
    "pr.status NOT IN ('Draft', 'Leader', 'Kacab', 'Revisi', 'Ditolak')"
];
$params = [$filter_bulan, $filter_tahun];
$types = "ss";

if (!empty($filter_cabang)) {
    $where_clauses[] = "pr.cabang = ?";
    $params[] = $filter_cabang;
    $types .= "s";
}

if (!empty($filter_type)) {
    $where_clauses[] = "pr.request_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    d.nama_divisi,
    pr.cabang,
    pr.status,
    pr.request_type,
    pr.nominal_ppx,
    (SELECT SUM(total_harga) FROM ppx_request_detail WHERE no_ppx = pr.no_ppx) as nominal_detail
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
WHERE $where_sql
ORDER BY pr.tanggal_pengajuan DESC, pr.no_ppx DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Query Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$cabang_query = "SELECT nama_cabang FROM master_cabang ORDER BY nama_cabang ASC";
$cabang_result = mysqli_query($conn, $cabang_query);

$tahun_query = "SELECT DISTINCT YEAR(tanggal_pengajuan) as tahun FROM ppx_request ORDER BY tahun DESC";
$tahun_result = mysqli_query($conn, $tahun_query);
$list_tahun = [];
while($t = mysqli_fetch_assoc($tahun_result)) {
    if($t['tahun']) $list_tahun[] = $t['tahun'];
}
if(!in_array(date('Y'), $list_tahun)){
    $list_tahun[] = date('Y');
    rsort($list_tahun);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report PPX Regular & Monthly</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    }
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <style>
        .badge-status { font-size: 0.8rem; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Report PPX Regular & Monthly</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Report PPX</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success btn-sm" id="btnExport">
                                <i class="fas fa-file-excel fa-sm"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filter Report</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="report_all.php">
                                    <div class="row align-items-end">
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label>Bulan</label>
                                                <select class="form-control select2" name="bulan" required>
                                                    <?php foreach ($daftar_bulan as $num => $name): ?>
                                                        <option value="<?= $num ?>" <?= ($filter_bulan === $num) ? 'selected' : '' ?>><?= $name ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label>Tahun</label>
                                                <select class="form-control select2" name="tahun" required>
                                                    <?php foreach ($list_tahun as $thn): ?>
                                                        <option value="<?= $thn ?>" <?= ($filter_tahun == $thn) ? 'selected' : '' ?>><?= $thn ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-0">
                                                <label>Cabang</label>
                                                <select class="form-control select2" name="cabang">
                                                    <option value="">-- Semua Cabang --</option>
                                                    <?php while ($c = mysqli_fetch_assoc($cabang_result)): ?>
                                                        <option value="<?= htmlspecialchars($c['nama_cabang']) ?>" <?= ($filter_cabang === $c['nama_cabang']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label>Tipe PPX</label>
                                                <select class="form-control select2" name="request_type">
                                                    <option value="">-- Semua Tipe --</option>
                                                    <option value="Regular" <?= ($filter_type === 'Regular') ? 'selected' : '' ?>>Regular</option>
                                                    <option value="Monthly_Budget" <?= ($filter_type === 'Monthly_Budget') ? 'selected' : '' ?>>Monthly Budget</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                                            <a href="report_all.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Reset</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-sm-12">
                        <div class="card table-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-stripped table-hover" id="reportTable" style="width:100%">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>No</th>
                                                <th>No. PPX</th>
                                                <th>Tanggal</th>
                                                <th>Tipe</th>
                                                <th>Cabang</th>
                                                <th>PIC</th>
                                                <th>Divisi</th>
                                                <th>Nominal (Rp)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php 
                                        $no = 1;
                                        while ($row = mysqli_fetch_assoc($result)): 
                                            $nominal = !empty($row['nominal_detail']) ? $row['nominal_detail'] : $row['nominal_ppx'];
                                            $status_class = 'secondary';
                                            switch ($row['status']) {
                                                case 'Selesai': case 'Done': $status_class = 'success'; break;
                                                case 'Ditolak': $status_class = 'danger'; break;
                                                case 'Revisi':  $status_class = 'warning'; break;
                                                case 'Uang Muka': $status_class = 'info'; break;
                                                default: $status_class = 'primary'; break;
                                            }
                                        ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><strong><?= htmlspecialchars($row['no_ppx']) ?></strong></td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $row['request_type'] === 'Regular' ? 'success' : 'info' ?> badge-sm">
                                                        <?= $row['request_type'] === 'Regular' ? 'Regular' : 'Monthly' ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['cabang'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['nama_pic']) ?></td>
                                                <td><?= htmlspecialchars($row['nama_divisi'] ?? '-') ?></td>
                                                <td class="text-right">Rp <?= number_format($nominal, 0, ',', '.') ?></td>
                                                <td><span class="badge badge-<?= $status_class ?> badge-status"><?= htmlspecialchars($status_labels[$row['status']] ?? $row['status']) ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($layout_dir && file_exists($layout_dir . '/footer.php')) include $layout_dir . '/footer.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({ placeholder: "-- Pilih --", allowClear: true });
            $('#reportTable').DataTable({
                "pageLength": 25,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": { "next": "&raquo;", "previous": "&laquo;" }
                }
            });

            $('#btnExport').on('click', function(){
                // Untuk demo, kita arahkan ke export_excel_all.php dengan filter yang sama
                const url = `export_excel_all.php?bulan=<?= $filter_bulan ?>&tahun=<?= $filter_tahun ?>&cabang=<?= urlencode($filter_cabang) ?>&request_type=<?= $filter_type ?>`;
                window.open(url, '_blank');
            });
        });
    </script>
</body>
</html>

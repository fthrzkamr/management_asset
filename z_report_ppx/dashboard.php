<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_report_ppx\dashboard.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

// Inisialisasi auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission report_ppx read
$auth->requirePermission('report_ppx', 'read');

// Label Status
$status_labels = [
    'Leader'   => 'Leader',
    'Kacab'    => 'Kepala Cabang',
    'Manager'  => 'Manager Finance',
    'Finance'  => 'GA (General Affairs)',
    'Direktur' => 'Direktur',
    'Selesai'  => 'Selesai',
    'Uang Muka' => 'Uang Muka',
    'Revisi'   => 'Revisi',
    'Ditolak'  => 'Ditolak'
];

// Label Bulan
$daftar_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil parameter filter bulan, tahun, cabang
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_cabang = isset($_GET['cabang']) ? $_GET['cabang'] : '';

// Build Where Clause
// Data yang tampil: harus sesuai bulan & tahun dari tanggal pengajuan,
// JIKA kosong maka default bulan/tahun berjalan.
// Kondisi UTAMA: status BUKAN 'Leader', 'Kacab', 'Revisi', atau 'Ditolak'.
// Artinya yg tampil adalah yang sudah disetujui Kacab/Asmen/Manager (dan sudah masuk ke status Manager, Finance, Direktur, Selesai, Uang Muka).
$where_clauses = [
    "pmb.period_month = ?",
    "pmb.period_year = ?",
    "pmb.status NOT IN ('Leader', 'Kacab', 'Revisi', 'Ditolak')"
];
$params = [$filter_bulan, $filter_tahun];
$types = "ss";

if (!empty($filter_cabang)) {
    $where_clauses[] = "u.id_cabang = (SELECT id_cabang FROM master_cabang WHERE nama_cabang = ? LIMIT 1)";
    $params[] = $filter_cabang;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT 
    pmb.id_budget AS no_ppx,
    pmb.created_at AS tanggal_pengajuan,
    u.nama_user AS nama_pic,
    d.nama_divisi,
    c.nama_cabang AS cabang,
    pmb.status,
    pmb.total_allocation AS nominal_ppx,
    pmb.file_excel
FROM ppx_monthly_budget pmb
LEFT JOIN user_dfm u ON pmb.created_by = u.id_user
LEFT JOIN divisi d ON u.divisi = d.id_divisi
LEFT JOIN master_cabang c ON u.id_cabang = c.id_cabang
WHERE $where_sql
ORDER BY pmb.created_at DESC, pmb.id_budget DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Query Error: " . mysqli_error($conn));
}

if (!empty($filter_cabang)) {
    mysqli_stmt_bind_param($stmt, "sss", $filter_bulan, $filter_tahun, $filter_cabang);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $filter_bulan, $filter_tahun);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Ambil data cabang untuk dropdown filter
$cabang_query = "SELECT nama_cabang FROM master_cabang ORDER BY nama_cabang ASC";
$cabang_result = mysqli_query($conn, $cabang_query);

// Data Tahun untuk Dropdown Filter
$tahun_query = "SELECT DISTINCT period_year as tahun FROM ppx_monthly_budget ORDER BY tahun DESC";
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
    <title>Report PPX Semua Cabang</title>
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
        .badge-status {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Report</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Report</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <!-- Button Export Excel diletakkan dalam form terpisah dengan JS onSubmit agar membaca checkbox terpilih -->
                            <button type="button" class="btn btn-success btn-sm" id="btnExportPick">
                                <i class="fas fa-file-excel fa-sm"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filter Report (Approved Only)</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="dashboard.php">
                                    <div class="row align-items-end">
                                        <div class="col-md-3">
                                            <div class="form-group mb-0">
                                                <label>Bulan</label>
                                                <select class="form-control select2" name="bulan" required>
                                                    <?php foreach ($daftar_bulan as $num => $name): ?>
                                                        <option value="<?= $num ?>" <?= (str_pad($filter_bulan, 2, '0', STR_PAD_LEFT) === $num) ? 'selected' : '' ?>>
                                                            <?= $name ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-0">
                                                <label>Tahun</label>
                                                <select class="form-control select2" name="tahun" required>
                                                    <?php foreach ($list_tahun as $thn): ?>
                                                        <option value="<?= $thn ?>" <?= ($filter_tahun == $thn) ? 'selected' : '' ?>>
                                                            <?= $thn ?>
                                                        </option>
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
                                                        <option value="<?= htmlspecialchars($c['nama_cabang']) ?>" <?= ($filter_cabang === $c['nama_cabang']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($c['nama_cabang']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary d-inline-block mt-4"><i class="fas fa-search"></i> Tampilkan</button>
                                            <a href="dashboard.php" class="btn btn-secondary d-inline-block mt-4"><i class="fas fa-sync"></i> Reset</a>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12 text-muted">
                                            <small><i class="fas fa-info-circle"></i> Catatan: Data yang tampil hanya pengajuan yang sudah melewati tahap approval minimum (status saat ini telah disetujui Kacab / Asmen / diatasnya).</small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Pick untuk Export Excel -->
                <form id="exportForm" action="export_excel.php" method="POST" target="_blank">
                    <!-- HIDDEN FILTERS -->
                    <input type="hidden" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>">
                    <input type="hidden" name="tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                    <input type="hidden" name="cabang" value="<?= htmlspecialchars($filter_cabang) ?>">

                    <!-- Table Card -->
                    <div class="row mt-3">
                        <div class="col-sm-12">
                            <div class="card table-card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-stripped table-hover" id="reportTable" style="width:100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center" style="width: 50px;">
                                                        <input type="checkbox" id="selectAllPicks"> All
                                                    </th>
                                                    <th>No</th>
                                                    <th>Tanggal</th>
                                                    <th>Cabang</th>
                                                    <th>PIC</th>
                                                    <th>Divisi</th>
                                                    <th>Nominal (Rp)</th>
                                                    <th>Lampiran</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php 
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result)): 
                                                // Badge status color
                                                $status_class = 'secondary';
                                                switch ($row['status']) {
                                                    case 'Selesai': $status_class = 'success'; break;
                                                    case 'Ditolak': $status_class = 'danger'; break;
                                                    case 'Revisi':  $status_class = 'warning'; break;
                                                    case 'Uang Muka': $status_class = 'info'; break;
                                                    default: $status_class = 'primary'; break; // Pending di approval
                                                }
                                            ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="picked_ppx[]" value="<?= htmlspecialchars($row['no_ppx']) ?>" class="pick-checkbox">
                                                    </td>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                                                    <td><?= htmlspecialchars($row['cabang'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($row['nama_pic']) ?></td>
                                                    <td><?= htmlspecialchars($row['nama_divisi'] ?? '-') ?></td>
                                                    <td class="text-right">Rp <?= number_format($row['nominal_ppx'], 0, ',', '.') ?></td>
                                                    <td class="text-center">
                                                        <?php if (!empty($row['file_excel'])): ?>
                                                            <a href="../uploads/ppx_excel/<?= htmlspecialchars($row['file_excel']) ?>" class="btn btn-outline-success btn-sm" download title="Download Excel">
                                                                <i class="fas fa-file-excel"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted"><small>N/A</small></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge badge-<?= $status_class ?> badge-status"><?= htmlspecialchars($status_labels[$row['status']] ?? $row['status']) ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </form>

                <?php
                if ($layout_dir && file_exists($layout_dir . '/footer.php')) {
                    include $layout_dir . '/footer.php';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Additional JS for DataTables and Select2 -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "-- Pilih --",
                allowClear: true
            });

            // Initialize DataTable specifically for this report table without conflicting with class '.datatable'
            $('#reportTable').DataTable({
                "pageLength": 50,
                "columnDefs": [
                    { "orderable": false, "targets": 0 } // non-sortable target checkbox
                ],
                "language": {
                    "search": "Cari Cepat:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "infoFiltered": "(difilter dari _MAX_ total data)",
                    "paginate": {
                        "first": "Awal",
                        "last": "Akhir",
                        "next": "Lanjut",
                        "previous": "Kembali"
                    }
                }
            });

            // Feature Pick: Select All Checkboxes
            $('#selectAllPicks').on('click', function(){
                // get the table instance to respect paginated rows as well if needed? No, standard click only checks current page.
                // However, datatables specific function lets us check all:
                var isChecked = $(this).prop('checked');
                $('.pick-checkbox').prop('checked', isChecked);
            });

            // Handle Export Logic
            $('#btnExportPick').on('click', function(e){
                e.preventDefault();
                // Check if there's any checked box. If no, we submit standard form (export all by filter).
                // If there are picked items, we submit them.
                var checkedCount = $('.pick-checkbox:checked').length;
                if (checkedCount === 0) {
                    var confirmAll = confirm("Anda belum memilih (Pick) satupun data. Ingin mengekspor SEMUA data pada Bulan/Cabang ini?");
                    if(confirmAll) {
                        $('#exportForm').submit();
                    }
                } else {
                    $('#exportForm').submit();
                }
            });
        });
    </script>
</body>

</html>

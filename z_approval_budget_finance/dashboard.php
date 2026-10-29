<?php
// Use absolute paths based on this file's directory
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - Approval Realisasi (Finance)
$auth->requirePermission('approval_budget_finance', 'approve');

// Get current user's division name for filtering
$user_id = $_SESSION['id_user'];
$user_query = "SELECT d.nama_divisi
               FROM user_dfm u
               LEFT JOIN divisi d ON u.divisi = d.id_divisi
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['nama_divisi'];

// Build division filter - Finance and Super Admin can see all budgets for approval
$division_filter = "";
if ($auth->isSuperAdmin()) {
    // Super Admin sees all budgets - no filter
} else if (stripos($user_division, 'Finance') === false) {
    // Non-Finance users follow division rules
    if (stripos($user_division, 'HR') !== false || stripos($user_division, 'IT') !== false || stripos($user_division, 'GA') !== false) {
        // HR, IT, GA can see each other's budgets (id_divisi: 1=IT, 6=GA, 7=HR)
        $division_filter = "AND creator_div_name.id_divisi IN (1, 6, 7)";
    } else {
        // Other divisions only see their own
        $division_filter = "AND creator_div_name.nama_divisi = '" . mysqli_real_escape_string($conn, $user_division) . "'";
    }
}
// Finance (including Finance AP) and Super Admin see all budgets - no filter

// Query untuk mengambil data monthly budget yang sudah diapprove Manager Finance (status Active)
// Menambahkan pengecekan : jika di routing diatur user spesifik, maka hanya user itu yang bisa lihat (untuk status pending realisasi).
$query = "SELECT
    mb.id_budget, mb.master_id, mb.period_year, mb.period_month, mb.budget_name,
    mb.total_allocation, mb.total_remaining, mb.status, mb.created_at, mb.created_by, mb.notes,
    u.nama_user as creator_name, u.departemen as creator_departemen, u.divisi as creator_divisi,
    mc.nama_cabang as creator_cabang,
    COUNT(mbi.id_budget_item) as total_items,
    SUM(mbi.used_amount) as total_used,
    (SUM(mbi.used_amount) / mb.total_allocation * 100) as usage_percentage
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN ppx_monthly_budget_item mbi ON mb.id_budget = mbi.id_budget
LEFT JOIN ppx_budget_routing br ON mb.status COLLATE utf8mb4_general_ci = br.step_name COLLATE utf8mb4_general_ci
WHERE mb.status IN ('Finance (Realisasi)', 'Active') 
  $division_filter
  AND (mb.status != 'Finance (Realisasi)' OR br.approver_id IS NULL OR br.approver_id = $user_id)
GROUP BY mb.id_budget
ORDER BY
    FIELD(mb.status, 'Finance (Realisasi)', 'Active'),
    mb.created_at DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Statistics query
$stats_query = "SELECT
    COUNT(CASE WHEN mb.status = 'Active' THEN 1 END) as active_count,
    COUNT(CASE WHEN mb.status = 'Finance (Realisasi)' THEN 1 END) as pending_realisasi_count,
    SUM(mb.total_allocation) as total_budget,
    SUM(mb.total_remaining) as total_remaining,
    SUM(mb.total_allocation - mb.total_remaining) as total_used
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
LEFT JOIN ppx_budget_routing br ON mb.status COLLATE utf8mb4_general_ci = br.step_name COLLATE utf8mb4_general_ci
WHERE mb.status IN ('Active', 'Finance (Realisasi)') 
  $division_filter
  AND (mb.status != 'Finance (Realisasi)' OR br.approver_id IS NULL OR br.approver_id = $user_id)";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Check if there is data
$has_data = mysqli_num_rows($result) > 0;

// Reset result pointer for iteration later
if ($has_data) {
    mysqli_data_seek($result, 0);
}

// Anggaran Besar di tab Finance
$result_besar_fin = mysqli_query($conn, "SELECT mb.id_budget, mb.period_year, mb.period_month,
    mb.budget_name, mb.total_allocation, mb.total_remaining, mb.status, mb.created_at,
    u.nama_user as creator_name, mc.nama_cabang as creator_cabang
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
WHERE mb.status IN ('Finance (Realisasi)', 'Active') AND mb.budget_type = 'besar'
ORDER BY mb.created_at DESC");
$count_besar_fin = mysqli_num_rows($result_besar_fin);

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Realisasi Anggaran Bulanan (Finance)</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        .btn-action {
            margin: 1px;
            padding: 4px 8px;
            font-size: 11px;
        }

        .card-stats {
            transition: all 0.2s ease;
        }

        .card-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            opacity: 0.6;
            font-size: 1.5rem;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .badge-sm {
            font-size: 0.7rem;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php
        $layout_dir = realpath(__DIR__ . '/../layouts');
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Approval Realisasi PPX (Finance)</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Approval Realisasi</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Deteksi Anggaran Bulan Lalu (Fitur Tutup Buku) -->
                <?php
                $current_year = (int)date('Y');
                $current_month = (int)date('n');

                // Cek apakah ada anggaran berstatus aktif dari bulan-bulan sebelumnya
                $check_past_query = "SELECT COUNT(id_budget) as past_count
                                     FROM ppx_monthly_budget
                                     WHERE status IN ('Active', 'Finance (Realisasi)')
                                     AND (period_year < $current_year OR (period_year = $current_year AND period_month < $current_month))";
                $check_past_result = mysqli_query($conn, $check_past_query);
                $past_data = mysqli_fetch_assoc($check_past_result);
                $past_count = $past_data['past_count'] ?? 0;
                ?>

                <?php if ($past_count > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" style="border-left: 5px solid #ff9800;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle text-warning"></i> Peringatan Pergantian Bulan</h5>
                                <p class="mb-0">Ditemukan <strong><?= $past_count ?> Anggaran Bulanan</strong> dari bulan/tahun lalu yang masih belum ditutup. Harap periksa dan selesaikan seluruh realisasi bulan lalu.</p>
                            </div>
                            <div>
                                <button type="button" class="btn btn-danger btn-sm shadow-sm" onclick="confirmClosePastBudgets()">
                                    <i class="fas fa-lock"></i> Tutup Buku Bulan Lalu
                                </button>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>

                    <script>
                    function confirmClosePastBudgets() {
                        const msg = 'Apakah Anda yakin ingin melakukan TUTUP BUKU (Closed) untuk semua anggaran aktif dari bulan-bulan sebelumnya?\n\nAksi ini akan mencegah input realisasi baru pada anggaran tersebut.';
                        
                        if (typeof showConfirm === 'function') {
                            showConfirm('Konfirmasi Tutup Buku', msg, function() {
                                window.location.href = 'close_past_budgets.php';
                            });
                        } else {
                            if (confirm(msg)) {
                                window.location.href = 'close_past_budgets.php';
                            }
                        }
                    }
                    </script>
                <?php endif; ?>

                <!-- Alert Messages -->
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

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="card bg-two flex-fill card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-0">Total Anggaran Aktif</h6>
                                        <h4 class="text-white mb-0">Rp <?= number_format($stats['total_budget'] ?? 0, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-money-bill-wave text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="card bg-three flex-fill card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-0">Total Terpakai</h6>
                                        <h4 class="text-white mb-0">Rp <?= number_format($stats['total_used'] ?? 0, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-hand-holding-usd text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="card bg-four flex-fill card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-0">Total Sisa Anggaran</h6>
                                        <h4 class="text-white mb-0">Rp <?= number_format($stats['total_remaining'] ?? 0, 0, ',', '.') ?></h4>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-wallet text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
                        <div class="card bg-one flex-fill card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-0">Menunggu Realisasi</h6>
                                        <h4 class="text-white mb-0"><?= $stats['pending_realisasi_count'] ?? 0 ?> Budget</h4>
                                    </div>
                                    <div class="stats-icon">
                                        <i class="fas fa-hourglass-half text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab navigasi Biasa / Besar -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#panel-fin-biasa"><i class="fas fa-wallet mr-1"></i>Anggaran Biasa</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#panel-fin-besar"><i class="fas fa-money-bill-wave mr-1 text-success"></i>Anggaran Besar<?php if ($count_besar_fin > 0): ?> <span class="badge badge-success"><?= $count_besar_fin ?></span><?php endif; ?></a></li>
                </ul>

                <div class="tab-content">
                <div class="tab-pane fade show active" id="panel-fin-biasa">
                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Anggaran Bulanan (Pending & Aktif)
                                </h5>
                                <small class="text-muted">Upload bukti pembayaran untuk mengaktifkan budget yang berstatus Pending</small>
                            </div>
                            <div class="card-body">


                                <div class="table-responsive">
                                    <table id="budgetTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th width="3%">No.</th>
                                                <th width="8%">Periode</th>
                                                <th width="15%">Nama Budget</th>
                                                <th width="12%">Total Alokasi</th>
                                                <th width="5%">Items</th>
                                                <th width="10%">PIC</th>
                                                <th width="10%">Cabang</th>
                                                <th width="9%">Tanggal</th>
                                                <th width="8%">Status</th>
                                                <th width="13%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php $row_num = 1; ?>
                                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                    <tr>
                                                        <td><small class="font-weight-bold"><?= $row_num++ ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $month_names[$row['period_month']] ?> <?= $row['period_year'] ?></small></td>
                                                        <td><small><?= htmlspecialchars($row['budget_name']) ?></small></td>
                                                        <td><small class="font-weight-bold">Rp <?= number_format($row['total_allocation'], 0, ',', '.') ?></small></td>
                                                        <td><small class="font-weight-bold"><?= $row['total_items'] ?> item</small></td>
                                                        <td><small><?= htmlspecialchars($row['creator_name'] ?? '-') ?></small></td>
                                                        <td><small><?= htmlspecialchars($row['creator_cabang'] ?? '-') ?></small></td>
                                                        <td><small><?= date('d/m/Y', strtotime($row['created_at'])) ?></small></td>
                                                        <td>
                                                            <small class="badge <?= $row['status'] === 'Active' ? ($row['usage_percentage'] > 80 ? 'badge-danger' : ($row['usage_percentage'] > 50 ? 'badge-warning' : 'badge-success')) : 'badge-info' ?>">
                                                                <?php
                                                                switch($row['status']) {
                                                                    case 'Kacab': echo 'Menunggu Kacab'; break;
                                                                    case 'Manager Finance': echo 'Menunggu Manager'; break;
                                                                    case 'Finance (Realisasi)': echo 'Menunggu Realisasi'; break;
                                                                    case 'Active': echo 'Aktif: ' . number_format($row['usage_percentage'], 1) . '%'; break;
                                                                    case 'Rejected': echo 'Ditolak'; break;
                                                                    case 'Closed': echo 'Ditutup'; break;
                                                                    default: echo $row['status'];
                                                                }
                                                                ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <!-- Detail Button -->
                                                                <a href="detail.php?id=<?= $row['id_budget'] ?>"
                                                                    class="btn btn-outline-info btn-action" title="Detail">
                                                                    <i class="fas fa-eye fa-xs"></i>
                                                                </a>

                                                                <!-- Upload Bukti TF Button - hanya muncul jika Pending -->
                                                                <?php if ($row['status'] === 'Finance (Realisasi)'): ?>
                                                                    <?php
                                                                    $has_master = !empty($row['master_id']);
                                                                    ?>
                                                                    <?php if ($has_master): ?>
                                                                        <button type="button" class="btn btn-outline-primary btn-action"
                                                                            onclick="showRealisasiModal(<?= $row['id_budget'] ?>)" title="Upload Bukti Pembayaran (Aktivasi Budget)">
                                                                            <i class="fas fa-upload fa-xs"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-outline-secondary btn-action" disabled
                                                                            title="Master Anggaran belum ditetapkan. Silakan commit ke Master Anggaran terlebih dahulu.">
                                                                            <i class="fas fa-upload fa-xs"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div><!-- end tab-pane panel-fin-biasa -->

                <!-- Tab Besar (Finance) -->
                <div class="tab-pane fade" id="panel-fin-besar">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Anggaran Besar - Di Finance</h5></div>
                        <div class="card-body">
                            <div class="table-responsive"><table class="table table-striped table-sm">
                                <thead><tr><th>No.</th><th>Periode</th><th>Nama Anggaran</th><th>Total Alokasi</th><th>PIC</th><th>Cabang</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                                <tbody>
                                <?php if ($count_besar_fin > 0): $nf = 1; while ($rf = mysqli_fetch_assoc($result_besar_fin)): ?>
                                <tr>
                                    <td><small><?= $nf++ ?></small></td>
                                    <td><small><?= $month_names[$rf['period_month']] ?> <?= $rf['period_year'] ?></small></td>
                                    <td><small><?= htmlspecialchars($rf['budget_name']) ?></small></td>
                                    <td><small>Rp <?= number_format($rf['total_allocation'],0,',','.') ?></small></td>
                                    <td><small><?= htmlspecialchars($rf['creator_name'] ?? '-') ?></small></td>
                                    <td><small><?= htmlspecialchars($rf['creator_cabang'] ?? '-') ?></small></td>
                                    <td><small><?= date('d/m/Y',strtotime($rf['created_at'])) ?></small></td>
                                    <td>
                                        <small class="badge <?= $rf['status']==='Active' ? 'badge-success' : 'badge-info' ?>">
                                            <?= $rf['status']==='Active' ? 'Aktif' : 'Menunggu Realisasi' ?>
                                        </small>
                                    </td>
                                    <td><a href="detail.php?id=<?= $rf['id_budget'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye fa-xs"></i></a></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="9" class="text-center py-3 text-muted"><i class="fas fa-inbox fa-2x mb-2"></i><p>Tidak ada anggaran besar di Finance</p></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table></div>
                        </div>
                    </div>
                </div>
                </div><!-- end tab-content -->

                <?php
                if ($layout_dir && file_exists($layout_dir . '/footer.php')) {
                    include $layout_dir . '/footer.php';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- JS Dependencies are in footer.php -->

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#budgetTable').DataTable({
                "order": [],
                "ordering": false,
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 data",
                    "zeroRecords": "Tidak ada data yang sesuai",
                    "emptyTable": "Tidak ada anggaran bulanan aktif",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "&raquo;",
                        "previous": "&laquo;"
                    }
                },
                "columnDefs": [
                    { "searchable": false, "targets": [8] },
                    { "orderable": false, "targets": [8] }
                ]
            });
        });

        function showRealisasiModal(budgetId) {
            // Fetch budget info via AJAX
            $.ajax({
                url: 'get_budget_items.php',
                type: 'GET',
                data: { id: budgetId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#budgetId').val(budgetId);
                        $('#budgetInfo').html('<strong>' + response.budget_name + '</strong> - ' + response.period);
                        $('#totalAllocation').text('Rp ' + formatNumber(response.total_allocation));
                        $('#totalRemaining').text('Rp ' + formatNumber(response.total_remaining));
                        // Auto-fill nominal with total allocation and lock it
                        try {
                            $('#nominalDisplay').val(formatNumber(response.total_allocation)).prop('readonly', true);
                            $('#nominalValue').val(response.total_allocation);
                        } catch(e) {}

                        $('#realisasiModal').modal('show');
                    } else {
                        alert('Gagal memuat data budget: ' + response.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat memuat data');
                }
            });
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewContainer = document.getElementById('previewContainer');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type === 'application/pdf') {
                    previewContainer.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        }

        function submitRealisasi() {
            const fileInput = document.getElementById('buktiTF');
            
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Mohon upload bukti pembayaran/TF terlebih dahulu!');
                return false;
            }

            const nominalValue = document.getElementById('nominalValue');
            const tanggalRealisasi = document.querySelector('input[name="tanggal_realisasi"]');
            
            if (!nominalValue || !nominalValue.value || parseFloat(nominalValue.value) <= 0) {
                alert('Mohon isi nominal pembayaran!');
                return false;
            }
            
            if (!tanggalRealisasi || !tanggalRealisasi.value) {
                alert('Mohon isi tanggal pembayaran!');
                return false;
            }

            // Langsung submit tanpa confirm yang bermasalah
            document.getElementById('realisasiForm').submit();
        }
    </script>

    <!-- Modal Upload Bukti Pembayaran -->
    <div class="modal fade" id="realisasiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-receipt"></i> Upload Bukti Pembayaran/Transfer</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="realisasiForm" action="save_realisasi.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="budget_id" id="budgetId">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>Budget:</strong> <span id="budgetInfo"></span>
                            <br>
                            <small class="mt-1 d-block">
                                <strong>Total Alokasi:</strong> <span id="totalAllocation"></span> 
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_realisasi" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nominal Pembayaran <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="text" class="form-control" id="nominalDisplay" 
                                       placeholder="0" required>
                                <input type="hidden" name="nominal" id="nominalValue">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Bukti Pembayaran/Transfer <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="buktiTF" name="bukti_tf" 
                                       accept="image/*,application/pdf" required onchange="previewImage(this)">
                                <label class="custom-file-label" for="buktiTF">Pilih file...</label>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Format: JPG, JPEG, PNG, PDF (Max: 2MB)
                            </small>
                        </div>

                        <!-- Preview Image -->
                        <div id="previewContainer" style="display: none;" class="mt-3">
                            <label>Preview:</label>
                            <div class="text-center">
                                <img id="imagePreview" src="" class="img-thumbnail" style="max-height: 300px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="2" 
                                      placeholder="Keterangan pembayaran (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary" onclick="submitRealisasi()">
                            <i class="fas fa-upload"></i> Upload Bukti
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Format Rupiah for nominal input
        $('#nominalDisplay').on('keyup', function() {
            if ($(this).prop('readonly')) return;
            let value = $(this).val().replace(/[^0-9]/g, '');
            $(this).val(formatNumber(value));
            $('#nominalValue').val(value);
        });

        // Custom file input label
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
        });
    </script>
</body>
</html>

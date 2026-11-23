<?php
/**
 * Master Budget Dashboard
 * Halaman utama untuk mengelola master budget
 */

// Start session jika belum

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/MasterBudgetService.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Cek permission: hanya yang punya permission master_budget:read
$auth->requirePermission('master_budget', 'read');

// Initialize service
$budgetService = new MasterBudgetService($conn);

// Auto-migrate kolom review per-cabang (Fase 2 lanjutan - item 18 koreksi nominal pengajuan)
$review_cols = [
    'approved_allocation' => "ALTER TABLE ppx_monthly_budget ADD COLUMN approved_allocation DECIMAL(15,2) NULL AFTER total_allocation",
    'review_decision'     => "ALTER TABLE ppx_monthly_budget ADD COLUMN review_decision ENUM('Sesuai','Dikoreksi') NULL AFTER approved_allocation",
    'reviewed_by'         => "ALTER TABLE ppx_monthly_budget ADD COLUMN reviewed_by INT(11) NULL AFTER review_decision",
    'reviewed_at'         => "ALTER TABLE ppx_monthly_budget ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by",
];
foreach ($review_cols as $col => $alt_sql) {
    $c_res = @mysqli_query($conn, "SHOW COLUMNS FROM ppx_monthly_budget LIKE '$col'");
    if ($c_res && mysqli_num_rows($c_res) === 0) {
        @mysqli_query($conn, $alt_sql);
    }
}

// Periode yang mau direview (default: bulan depan, sesuai alur pengajuan)
$rv_month = isset($_GET['rv_month']) ? (int)$_GET['rv_month'] : ((int)date('n') + 1);
$rv_year  = isset($_GET['rv_year'])  ? (int)$_GET['rv_year']  : (int)date('Y');
if ($rv_month > 12) { $rv_month = 1; $rv_year++; }
if ($rv_month < 1)  { $rv_month = 12; $rv_year--; }

// Daftar pengajuan per cabang untuk periode terpilih (untuk di-review Ya/Tidak)
$review_items = [];
$rv_q = mysqli_query($conn, "
    SELECT pb.id_budget, pb.period_year, pb.period_month, pb.total_allocation, pb.approved_allocation,
           pb.review_decision, pb.budget_name, pb.status, pb.tipe_anggaran,
           u.nama_user AS pic, mc.nama_cabang
    FROM ppx_monthly_budget pb
    LEFT JOIN user_dfm u ON pb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    WHERE pb.period_year = $rv_year AND pb.period_month = $rv_month
      AND pb.status NOT IN ('Draft', 'Rejected', 'Closed')
    ORDER BY mc.nama_cabang ASC, pb.id_budget ASC
");
if ($rv_q) {
    while ($r = mysqli_fetch_assoc($rv_q)) {
        $review_items[] = $r;
    }
}

// Get all master budgets
$masterBudgets = $budgetService->getAllMasterBudgets();

// Get last month's remaining budget
$last_budget_query = "SELECT 
    mb.master_id,
    mb.balance,
    mb.description,
    mb.created_at
FROM master_budget mb
ORDER BY mb.created_at DESC
LIMIT 1";

$last_budget_result = mysqli_query($conn, $last_budget_query);
$last_budget = mysqli_fetch_assoc($last_budget_result);
$last_remaining = $last_budget ? floatval($last_budget['balance']) : 0;

// Get pending monthly budget items (submitted but not allocated / master not assigned)
 $pending_items = [];
 $pg_sql = "SELECT pb.id_budget, pb.period_year, pb.period_month, pb.total_allocation, pb.approved_allocation, pb.review_decision, pb.budget_name, pb.budget_type,
            u.nama_user as pic
            FROM ppx_monthly_budget pb
            LEFT JOIN user_dfm u ON pb.created_by = u.id_user
            WHERE pb.master_allocated = 0 AND (pb.master_id IS NULL OR pb.master_id = '') 
            AND pb.status NOT IN ('Draft', 'Rejected', 'Closed')
            ORDER BY pb.period_year DESC, pb.period_month DESC, pb.id_budget ASC";
 $pg_res = mysqli_query($conn, $pg_sql);
 if ($pg_res) {
     while ($r = mysqli_fetch_assoc($pg_res)) {
         $pending_items[] = $r;
     }
 }

// Handle success/error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
    

        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Master Anggaran</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Master Anggaran</li>
                    </ul>
                </div>
                <div class="col-auto float-right ml-auto">
                    <div class="btn-group" role="group">
                        <?php if ($auth->hasPermission('master_budget', 'create')): ?>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addMasterModal">
                            <i class="fas fa-plus"></i> <span class="d-none d-md-inline">Tambah Master Budget</span>
                        </button>
                        <button class="btn btn-success" data-toggle="modal" data-target="#topupModal">
                            <i class="fas fa-money-bill-wave"></i> <span class="d-none d-md-inline">Top-Up Dana</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Review Pengajuan Per Cabang (Ya/Tidak + koreksi nominal) -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <h5 class="card-title mb-0 font-weight-bold">
                        <i class="fas fa-clipboard-check text-primary mr-1"></i> Review Pengajuan Per Cabang
                    </h5>
                    <form method="GET" class="form-inline" style="gap:8px;">
                        <select name="rv_month" class="form-control form-control-sm mr-2">
                            <?php
                            $mm_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $rv_month ? 'selected' : '' ?>><?= $mm_names[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                        <input type="number" name="rv_year" class="form-control form-control-sm mr-2" style="width:90px;" value="<?= $rv_year ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-filter"></i> Tampilkan</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($review_items)): ?>
                    <p class="text-muted mb-0">Tidak ada pengajuan untuk periode <?= $mm_names[$rv_month] ?> <?= $rv_year ?>.</p>
                <?php else: ?>
                <form action="review_pengajuan_process.php" method="POST" id="formReviewPengajuan">
                    <input type="hidden" name="rv_month" value="<?= $rv_month ?>">
                    <input type="hidden" name="rv_year" value="<?= $rv_year ?>">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cabang</th>
                                    <th>PIC</th>
                                    <th class="text-right">Nominal Diajukan</th>
                                    <th width="140">Keputusan</th>
                                    <th width="200">Nominal Disetujui</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($review_items as $ri): $bid = (int)$ri['id_budget']; ?>
                                <tr>
                                    <td><?= htmlspecialchars($ri['nama_cabang'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($ri['pic'] ?: '-') ?></td>
                                    <td class="text-right">Rp <?= number_format($ri['total_allocation'], 0, ',', '.') ?></td>
                                    <td>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" class="custom-control-input rv-decision" name="decision_<?= $bid ?>" id="rv_ya_<?= $bid ?>" value="Sesuai" <?= ($ri['review_decision'] !== 'Dikoreksi') ? 'checked' : '' ?> onchange="toggleRvNominal(<?= $bid ?>)">
                                            <label class="custom-control-label" for="rv_ya_<?= $bid ?>">Ya</label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" class="custom-control-input rv-decision" name="decision_<?= $bid ?>" id="rv_tidak_<?= $bid ?>" value="Dikoreksi" <?= ($ri['review_decision'] === 'Dikoreksi') ? 'checked' : '' ?> onchange="toggleRvNominal(<?= $bid ?>)">
                                            <label class="custom-control-label" for="rv_tidak_<?= $bid ?>">Tidak</label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm rv-nominal-input rupiah-mask" id="rv_nominal_<?= $bid ?>" name="approved_<?= $bid ?>"
                                               value="<?= $ri['approved_allocation'] !== null ? number_format($ri['approved_allocation'], 0, ',', '.') : '' ?>"
                                               placeholder="Nominal yang disetujui"
                                               <?= ($ri['review_decision'] !== 'Dikoreksi') ? 'disabled' : '' ?>>
                                    </td>
                                    <td>
                                        <?php if ($ri['review_decision'] === 'Dikoreksi'): ?>
                                            <span class="badge badge-warning">Dikoreksi: Rp <?= number_format($ri['approved_allocation'], 0, ',', '.') ?></span>
                                        <?php elseif ($ri['review_decision'] === 'Sesuai'): ?>
                                            <span class="badge badge-success">Sesuai Pengajuan</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Belum Direview</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Review</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row">
            <?php
            $total_in = 0;
            $total_out = 0;
            $total_balance = 0;
            foreach ($masterBudgets as $budget) {
                $total_in += $budget['amount_in'];
                $total_out += $budget['amount_out'];
                $total_balance += $budget['balance'];
            }
            $usage_percentage = $total_in > 0 ? ($total_out / $total_in * 100) : 0;
            ?>
            
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="dash-widget-header">
                            <span class="dash-widget-icon text-primary border-primary">
                                <i class="fas fa-arrow-down"></i>
                            </span>
                            <div class="dash-count">
                                <h3>Rp <?= number_format($total_in, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="dash-widget-info">
                            <h6 class="text-muted">Total Pemasukan</h6>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="dash-widget-header">
                            <span class="dash-widget-icon text-danger border-danger">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                            <div class="dash-count">
                                <h3>Rp <?= number_format($total_out, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="dash-widget-info">
                            <h6 class="text-muted">Total Pengeluaran</h6>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-danger" style="width: <?= $usage_percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="dash-widget-header">
                            <span class="dash-widget-icon text-success border-success">
                                <i class="fas fa-wallet"></i>
                            </span>
                            <div class="dash-count">
                                <h3>Rp <?= number_format($total_balance, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="dash-widget-info">
                            <h6 class="text-muted">Saldo Tersedia</h6>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: <?= 100 - $usage_percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="dash-widget-header">
                            <span class="dash-widget-icon text-info border-info">
                                <i class="fas fa-chart-pie"></i>
                            </span>
                            <div class="dash-count">
                                <h3><?= number_format($usage_percentage, 2) ?>%</h3>
                            </div>
                        </div>
                        <div class="dash-widget-info">
                            <h6 class="text-muted">Persentase Terpakai</h6>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-info" style="width: <?= $usage_percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Master Budget Table -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Daftar Master Budget</h4>
                    </div>
                    <div class="card-body">
                <div class="table-responsive">
                    <table id="masterBudgetTable" class="table table-bordered table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="min-width: 150px;">Master ID</th>
                                <th style="min-width: 100px;">Tanggal</th>
                                <th style="min-width: 120px;">Kategori</th>
                                <th style="min-width: 130px;" class="text-right">Pemasukan</th>
                                <th style="min-width: 130px;" class="text-right">Pengeluaran</th>
                                <th style="min-width: 130px;" class="text-right">Saldo</th>
                                <th style="min-width: 100px;" class="text-center">% Terpakai</th>
                                <th style="min-width: 200px;">Deskripsi</th>
                                <th style="min-width: 130px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($masterBudgets as $budget): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($budget['master_id']) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($budget['tanggal'])) ?></td>
                                <td>
                                    <?php
                                    $kat = $budget['kategori'] ?? null;
                                    $kat_colors = [
                                        'Kas Kecil'        => 'badge-secondary',
                                        'Kas Besar'        => 'badge-primary',
                                        'Kas Besar BCA'=> 'badge-info',
                                    ];
                                    $kat_class = $kat_colors[$kat] ?? 'badge-light text-dark border';
                                    ?>
                                    <span class="badge <?= $kat_class ?>" style="font-size:0.8rem;">
                                        <?= htmlspecialchars($kat ?: '—') ?>
                                    </span>
                                </td>
                                <td class="text-right">Rp <?= number_format($budget['amount_in'], 0, ',', '.') ?></td>
                                <td class="text-right">Rp <?= number_format($budget['amount_out'], 0, ',', '.') ?></td>
                                <td class="text-right">
                                    <?php
                                    $balance_class = 'balance-positive';
                                    $percentage = $budget['usage_percentage'] ?? 0;
                                    if ($percentage > 80) $balance_class = 'balance-danger';
                                    elseif ($percentage > 60) $balance_class = 'balance-warning';
                                    ?>
                                    <span class="<?= $balance_class ?>">
                                        Rp <?= number_format($budget['balance'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $bar_class = 'bg-success';
                                    if ($percentage > 80) $bar_class = 'bg-danger';
                                    elseif ($percentage > 60) $bar_class = 'bg-warning';
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $bar_class ?>" role="progressbar" 
                                             style="width: <?= $percentage ?>%">
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($budget['description']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="detail.php?id=<?= urlencode($budget['master_id']) ?>" 
                                           class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($auth->hasPermission('master_budget', 'create')): ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="showTopupModal('<?= htmlspecialchars($budget['master_id']) ?>')" 
                                                title="Top-Up">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($auth->hasPermission('master_budget', 'delete')): ?>
                                            <?php if ($budget['amount_out'] > 0): ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Tidak dapat dihapus, dana sudah digunakan">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-danger btn-delete-master" 
                                                        data-master-id="<?= htmlspecialchars($budget['master_id']) ?>" 
                                                        data-description="<?= htmlspecialchars($budget['description']) ?>"
                                                        title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    <?php include '../layouts/footer.php'; ?>
</div>

    
    <!-- Modal: Add Master Budget -->
    <div class="modal fade" id="addMasterModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form action="master_insert.php" method="POST">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Master Budget</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Pilih Periode Anggaran (Bulan & Tahun) - Fase 2 Item 11 -->
                        <div class="row">
                            <div class="col-7">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold small">Periode Bulan <span class="text-danger">*</span></label>
                                    <select name="periode_bulan" id="mb_periode_bulan" class="form-control form-control-sm font-weight-bold" onchange="autoFillMasterDesc()">
                                        <?php
                                        $bulan_names_mb = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                                        $cur_m = (int)date('n');
                                        $next_m = ($cur_m % 12) + 1;
                                        foreach ($bulan_names_mb as $b_num => $b_str): ?>
                                            <option value="<?= $b_num ?>" <?= $b_num === $next_m ? 'selected' : '' ?>><?= $b_str ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-5">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold small">Tahun <span class="text-danger">*</span></label>
                                    <input type="number" name="periode_tahun" id="mb_periode_tahun" class="form-control form-control-sm font-weight-bold" value="<?= (int)date('Y') + ($cur_m == 12 ? 1 : 0) ?>" required onchange="autoFillMasterDesc()">
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">Tanggal Pembuatan Master Budget <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <!-- Opsi Pengisian Nominal Saldo (Fase 2 Item 11) -->
                        <div class="form-group mb-3 p-2 border rounded" style="background: #f8fafc;">
                            <label class="font-weight-bold small d-block mb-1">Opsi Pengisian Saldo Awal</label>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="isi_nominal_ya" name="isi_nominal" value="1" class="custom-control-input" checked onchange="toggleIsiNominal()">
                                <label class="custom-control-label font-weight-bold text-success small" for="isi_nominal_ya">
                                    <i class="fas fa-check-circle mr-1"></i>Ya, Isi Saldo Awal Sekarang
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="isi_nominal_tidak" name="isi_nominal" value="0" class="custom-control-input" onchange="toggleIsiNominal()">
                                <label class="custom-control-label font-weight-bold text-primary small" for="isi_nominal_tidak">
                                    <i class="fas fa-layer-group mr-1"></i>Tidak (Diisi Bertahap via Top-Up)
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:11px;">
                                *Jika memilih "Tidak", master budget akan dibuat dengan saldo awal Rp 0 dan saldo diisi bertahap melalui tombol Top-Up Dana.
                            </small>
                        </div>

                        <div class="form-group" id="group_dana_awal">
                            <label class="font-weight-bold small">Jumlah Dana Awal <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="text" id="amount_display" class="form-control" required placeholder="0" autocomplete="off">
                                <input type="hidden" name="amount" id="amount_value" value="0">
                            </div>
                            <small class="text-muted">Masukkan jumlah dana baru dalam Rupiah</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="d-flex justify-content-between align-items-center">
                                <span>Sisa Budget Bulan Lalu</span>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="has_carryover" name="has_carryover" value="1" onchange="toggleCarryover()" <?= $last_remaining > 0 ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="has_carryover">Gunakan Sisa</label>
                                </div>
                            </label>
                            <div class="input-group" id="carryover_field" style="<?= $last_remaining > 0 ? 'display: flex;' : 'display: none;' ?>">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="text" id="carryover_display" class="form-control" placeholder="0" autocomplete="off" value="<?= $last_remaining > 0 ? number_format($last_remaining, 0, ',', '.') : '' ?>">
                                <input type="hidden" name="carryover_amount" id="carryover_value" value="<?= $last_remaining ?>">
                            </div>
                            <?php if ($last_remaining > 0): ?>
                                <small class="text-success">
                                    <i class="fas fa-info-circle"></i> Terdeteksi sisa budget sebesar <strong>Rp <?= number_format($last_remaining, 0, ',', '.') ?></strong> dari periode sebelumnya
                                </small>
                            <?php else: ?>
                                <small class="text-muted">Tidak ada sisa budget dari periode sebelumnya</small>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pending_items)): ?>
                        <div class="alert alert-warning mt-3">
                            <strong>Pengajuan Bulanan Pending</strong>
                                <div>
                                    <?php foreach ($pending_items as $pi):
                                        $effective_amount = $pi['approved_allocation'] !== null ? (float)$pi['approved_allocation'] : (float)$pi['total_allocation'];
                                    ?>
                                    <div class="form-check py-1">
                                        <input class="form-check-input commit-checkbox" type="checkbox" name="commit_budget_id[]"
                                               value="<?= intval($pi['id_budget']) ?>"
                                               data-amount="<?= $effective_amount ?>"
                                               id="commit_<?= intval($pi['id_budget']) ?>"
                                               onchange="updateSelectedTotal()">
                                        <label class="form-check-label" for="commit_<?= intval($pi['id_budget']) ?>">
                                            <?= str_pad(intval($pi['period_month']),2,'0',STR_PAD_LEFT) ?>/<?= $pi['period_year'] ?> — Rp <?= number_format($effective_amount,0,',','.') ?>
                                            <?php if ($pi['review_decision'] === 'Dikoreksi'): ?>
                                                <span class="text-muted small">(diajukan: Rp <?= number_format($pi['total_allocation'],0,',','.') ?>)</span>
                                            <?php endif; ?>
                                            <?php if (!empty($pi['budget_name'])): ?>
                                                — <strong><?= htmlspecialchars($pi['budget_name']) ?></strong>
                                            <?php endif; ?>
                                            <?php
                                                $b_type = isset($pi['budget_type']) ? $pi['budget_type'] : 'biasa';
                                                $b_label = ($b_type === 'besar') ? 'Besar' : 'Biasa';
                                                $b_class = ($b_type === 'besar') ? 'badge-primary' : 'badge-info';
                                            ?>
                                            <span class="badge <?= $b_class ?> ml-1"><?= $b_label ?></span>
                                            <?php if ($pi['review_decision'] === 'Dikoreksi'): ?>
                                                <span class="badge badge-warning ml-1">Dikoreksi</span>
                                            <?php elseif ($pi['review_decision'] === null): ?>
                                                <span class="badge badge-secondary ml-1">Belum Direview</span>
                                            <?php endif; ?>
                                            <?php if (!empty($pi['pic'])): ?>
                                                — PIC: <?= htmlspecialchars($pi['pic']) ?>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div id="selected_total_info" class="mt-2 pt-2 border-top" style="display: none;">
                                    <strong>Total Pengajuan Terpilih:</strong> <span id="selected_total_amount" class="text-danger font-weight-bold">Rp 0</span>
                                </div>
                        </div>
                        <?php endif; ?>
                        
                        <div id="total_info" class="alert alert-info" style="<?= $last_remaining > 0 ? 'display: block;' : 'display: none;' ?>">
                            <strong>Total Master Budget:</strong> <span id="total_amount">Rp <?= $last_remaining > 0 ? number_format($last_remaining, 0, ',', '.') : '0' ?></span>
                            <div class="mt-1">
                                <small>Dana Baru: <span id="info_new">Rp 0</span> + Sisa Bulan Lalu: <span id="info_carryover">Rp <?= $last_remaining > 0 ? number_format($last_remaining, 0, ',', '.') : '0' ?></span></small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Kategori Anggaran <span class="text-danger">*</span></label>
                            <select name="kategori" id="mb_kategori_select" class="form-control" required onchange="autoFillMasterDesc()">
                                <option value="">— Pilih Kategori —</option>
                                <option value="Kas Kecil">Kas Kecil</option>
                                <option value="Kas Besar">Kas Besar</option>
                                <option value="Kas Besar BCA">Kas Besar BCA</option>
                            </select>
                            <small class="text-muted">Pilih jenis anggaran yang akan dibuat</small>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi <span class="text-danger">*</span></label>
                            <textarea name="description" id="mb_description" class="form-control" rows="3" required placeholder="Contoh: Master Kas Kecil Periode Desember 2026"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal: Top-Up -->
    <div class="modal fade" id="topupModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form action="master_topup.php" method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Top-Up Master Budget</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Pilih Master Budget <span class="text-danger">*</span></label>
                            <select name="master_id" id="topup_master_id" class="form-control" required>
                                <option value="">-- Pilih Master --</option>
                                <?php foreach ($masterBudgets as $budget): ?>
                                <option value="<?= htmlspecialchars($budget['master_id']) ?>">
                                    <?= htmlspecialchars($budget['master_id']) ?> - 
                                    Saldo: Rp <?= number_format($budget['balance'], 0, ',', '.') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Jumlah Top-Up <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="text" id="topup_amount_display" class="form-control" required placeholder="0" autocomplete="off">
                                <input type="hidden" name="amount" id="topup_amount_value">
                            </div>
                            <small class="text-muted">Masukkan jumlah top-up dalam Rupiah (contoh: 2.000.000)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Catatan</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Top-Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal: Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i> <strong>Perhatian!</strong> Tindakan ini tidak dapat dibatalkan.
                    </div>
                    <p><strong>Apakah Anda yakin ingin menghapus Master Budget ini?</strong></p>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <td width="30%"><strong>Master ID</strong></td>
                            <td><span id="delete_master_id"></span></td>
                        </tr>
                        <tr>
                            <td><strong>Deskripsi</strong></td>
                            <td><span id="delete_description"></span></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Error Validasi -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Validasi Gagal</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="error_content"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap4.min.css">

<style>
    /* Responsive styling untuk action buttons */
    .btn-group {
        white-space: nowrap;
    }
    
    /* Responsive cards untuk mobile */
    @media (max-width: 768px) {
        .dash-count h3 {
            font-size: 1.1rem;
        }
        .dash-widget-info h6 {
            font-size: 0.8rem;
        }
        .col-xl-3.col-sm-6.col-12 {
            margin-bottom: 15px;
        }
        .page-header .btn-group .btn {
            padding: 0.5rem 0.75rem;
        }
        .page-header .btn-group .btn i {
            margin: 0;
        }
    }
    
    @media (max-width: 576px) {
        .dash-widget-header {
            flex-direction: column;
            text-align: center;
        }
        .dash-count h3 {
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        .card-body {
            padding: 1rem;
        }
    }
    
    /* Table responsive improvements */
    #masterBudgetTable {
        font-size: 0.875rem;
        width: 100% !important;
    }
    
    #masterBudgetTable td {
        vertical-align: middle;
        white-space: normal;
        word-wrap: break-word;
    }
    
    #masterBudgetTable thead th {
        white-space: nowrap;
        font-size: 0.85rem;
        padding: 0.5rem;
    }
    
    #masterBudgetTable tbody td {
        padding: 0.5rem;
    }
    
    /* Progress bar dalam table */
    #masterBudgetTable .progress {
        margin: 0;
        min-width: 80px;
    }
    
    /* Button group spacing */
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .btn-group .btn i {
        font-size: 0.875rem;
    }
    
    /* Balance colors */
    .balance-positive {
        color: #28a745;
        font-weight: 600;
    }
    
    .balance-warning {
        color: #ffc107;
        font-weight: 600;
    }
    
    .balance-danger {
        color: #dc3545;
        font-weight: 600;
    }
    
    /* Responsive modal */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 0.5rem;
        }
    }

    /* Make scrollable modal bodies fit viewport and allow scrolling */
    .modal-dialog.modal-dialog-scrollable .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* DataTables responsive adjustments */
    table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
        background-color: #007bff;
    }
    
    /* DataTables Wrapper Layout */
    .dataTables_wrapper .row:first-child {
        margin-bottom: 15px;
    }
    
    .dataTables_wrapper .row:last-child {
        margin-top: 15px;
    }
    
    /* Pagination di kanan */
    .dataTables_wrapper .dataTables_paginate {
        float: right;
        text-align: right;
    }
    
    /* Info di kiri */
    .dataTables_wrapper .dataTables_info {
        float: left;
        padding-top: 8px;
    }
    
    /* Clear float */
    .dataTables_wrapper::after {
        content: "";
        display: table;
        clear: both;
    }
</style>

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>

<script>
var deleteMasterId = '';

$(document).ready(function() {
    // Initialize DataTable
    $('#masterBudgetTable').DataTable({
        "pageLength": 10,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]],
        "responsive": true,
        "autoWidth": false,
        "language": {
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Data tidak ditemukan",
            "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "&raquo;",
                "previous": "&laquo;"
            }
        },
        "order": [[3, 'desc']], // Sort by tanggal descending (geser karena kolom Kategori ditambah)
        "columnDefs": [
            { "orderable": false, "targets": 9 }, // Disable sorting on Aksi column
            { "className": "text-center", "targets": [0, 7, 9] },
            { "className": "text-right", "targets": [4, 5, 6] }
        ]
    });
    
    // Handle delete button with event delegation (works with DataTables)
    $(document).on('click', '.btn-delete-master', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var masterId = $(this).data('master-id');
        var description = $(this).data('description');
        
        if (!masterId) {
            alert('Error: Master ID tidak ditemukan!');
            return;
        }
        
        // Set data ke modal
        deleteMasterId = masterId;
        $('#delete_master_id').text(masterId);
        $('#delete_description').text(description);
        
        // Show modal
        $('#deleteModal').modal('show');
    });
    
    // Handle confirm delete button (jQuery handler)
    $('#confirmDeleteBtn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!deleteMasterId) {
            alert('Error: Master ID tidak valid!');
            return;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menghapus...');
        
        // Redirect to delete
        var deleteUrl = 'master_delete.php?id=' + encodeURIComponent(deleteMasterId);
        
        setTimeout(function() {
            window.location.href = deleteUrl;
        }, 100);
    });
    
    // Reset when modal closed
    $('#deleteModal').on('hidden.bs.modal', function() {
        deleteMasterId = '';
        $('#confirmDeleteBtn').prop('disabled', false).html('<i class="fas fa-trash"></i> Ya, Hapus');
    });
    
    // Fallback: Direct click handler untuk button delete
    setTimeout(function() {
        $('.btn-delete-master').off('click').on('click', function(e) {
            e.preventDefault();
            
            var masterId = $(this).data('master-id');
            var description = $(this).data('description');
            
            if (!masterId) {
                alert('Error: Master ID tidak ditemukan!');
                return;
            }
            
            deleteMasterId = masterId;
            $('#delete_master_id').text(masterId);
            $('#delete_description').text(description);
            $('#deleteModal').modal('show');
        });
    }, 500);
});

function showTopupModal(masterId) {
    $('#topup_master_id').val(masterId);
    $('#topupModal').modal('show');
}

// Format number to Rupiah (with dots as thousand separator)
function formatRupiah(angka, prefix = '') {
    var number_string = angka.replace(/[^,\d]/g, '').toString();
    var split = number_string.split(',');
    var sisa = split[0].length % 3;
    var rupiah = split[0].substr(0, sisa);
    var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    
    if (ribuan) {
        var separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix == undefined ? rupiah : (rupiah ? prefix + rupiah : '');
}

// Parse Rupiah format to number
function parseRupiah(rupiah) {
    return parseInt(rupiah.replace(/\./g, '').replace(/,/g, '')) || 0;
}

// Review Pengajuan Per Cabang - toggle input nominal koreksi
function toggleRvNominal(bid) {
    var isTidak = $('#rv_tidak_' + bid).is(':checked');
    var $input = $('#rv_nominal_' + bid);
    $input.prop('disabled', !isTidak);
    if (!isTidak) $input.val('');
}

$(document).on('input', '.rv-nominal-input', function() {
    this.value = formatRupiah(this.value);
});

$('#formReviewPengajuan').on('submit', function(e) {
    var ok = true;
    $('.rv-nominal-input:not([disabled])').each(function() {
        if (parseRupiah($(this).val()) <= 0) ok = false;
    });
    if (!ok) {
        e.preventDefault();
        alert('Untuk pengajuan yang dipilih "Tidak", isi nominal yang disetujui (harus lebih dari 0).');
    }
});

// Show error modal
function showErrorModal(title, content) {
    $('#errorModal .modal-title').html('<i class="fas fa-times-circle"></i> ' + title);
    $('#error_content').html(content);
    $('#errorModal').modal('show');
}

// Get total dari checkbox pengajuan yang dipilih
function getSelectedTotal() {
    var total = 0;
    $('.commit-checkbox:checked').each(function() {
        total += parseFloat($(this).data('amount')) || 0;
    });
    return total;
}

// Update tampilan total pengajuan terpilih
function updateSelectedTotal() {
    var total = getSelectedTotal();
    
    // Hapus warning lama dulu
    $('#saldo_warning').remove();
    
    if (total > 0) {
        $('#selected_total_info').show();
        $('#selected_total_amount').text('Rp ' + formatRupiah(total.toString()));
        
        // Cek apakah saldo cukup
        var amountValue = parseInt($('#amount_value').val()) || 0;
        var carryoverValue = parseInt($('#carryover_value').val()) || 0;
        var totalSaldo = amountValue + carryoverValue;
        
        if (totalSaldo < total) {
            $('#selected_total_amount').removeClass('text-success').addClass('text-danger');
            $('#selected_total_info').append('<div id="saldo_warning" class="text-danger small mt-1"><i class="fas fa-exclamation-triangle"></i> Saldo tidak mencukupi! Kurang: Rp ' + formatRupiah((total - totalSaldo).toString()) + '</div>');
        } else {
            $('#selected_total_amount').removeClass('text-danger').addClass('text-success');
        }
    } else {
        $('#selected_total_info').hide();
    }
}

// Toggle carryover field
function toggleIsiNominal() {
    var isYa = $('#isi_nominal_ya').is(':checked');
    if (isYa) {
        $('#group_dana_awal').show();
        $('#amount_display').prop('required', true);
    } else {
        $('#group_dana_awal').hide();
        $('#amount_display').prop('required', false).val('');
        $('#amount_value').val('0');
        updateTotalAmount();
    }
}

function autoFillMasterDesc() {
    var bln = $('#mb_periode_bulan option:selected').text();
    var thn = $('#mb_periode_tahun').val();
    var kat = $('#mb_kategori_select').val() || 'Anggaran';
    var $desc = $('#mb_description');
    if (!$desc.data('customized')) {
        $desc.val('Master ' + kat + ' Periode ' + bln + ' ' + thn);
    }
}

$(document).on('input', '#mb_description', function() {
    $(this).data('customized', true);
});

function toggleCarryover() {
    const checkbox = document.getElementById('has_carryover');
    const field = document.getElementById('carryover_field');
    const totalInfo = document.getElementById('total_info');
    
    if (checkbox.checked) {
        field.style.display = 'flex';
        totalInfo.style.display = 'block';
    } else {
        field.style.display = 'none';
        document.getElementById('carryover_display').value = '';
        document.getElementById('carryover_value').value = '0';
        updateTotalAmount();
    }
}

// Update total amount calculation
function updateTotalAmount() {
    const newAmount = parseRupiah($('#amount_display').val() || '0');
    const carryoverAmount = parseRupiah($('#carryover_display').val() || '0');
    const total = newAmount + carryoverAmount;
    
    $('#total_amount').text('Rp ' + formatRupiah(total.toString()));
    $('#info_new').text('Rp ' + formatRupiah(newAmount.toString()));
    $('#info_carryover').text('Rp ' + formatRupiah(carryoverAmount.toString()));
    
    if (total > 0) {
        $('#total_info').show();
    } else if (!$('#has_carryover').is(':checked')) {
        $('#total_info').hide();
    }
    
    // Update validasi saldo vs pengajuan terpilih
    updateSelectedTotal();
}

// Initialize Rupiah format inputs
$(document).ready(function() {
    // Format input for Jumlah Dana Awal (Add Master Budget)
    $('#amount_display').on('keyup', function(e) {
        var value = $(this).val();
        $(this).val(formatRupiah(value));
        $('#amount_value').val(parseRupiah(value));
        updateTotalAmount();
    });
    
    // Format input for Carryover Amount
    $('#carryover_display').on('keyup', function(e) {
        var value = $(this).val();
        $(this).val(formatRupiah(value));
        $('#carryover_value').val(parseRupiah(value));
        updateTotalAmount();
    });
    
    // Format input for Jumlah Top-Up
    $('#topup_amount_display').on('keyup', function(e) {
        var value = $(this).val();
        $(this).val(formatRupiah(value));
        $('#topup_amount_value').val(parseRupiah(value));
    });
    
    // Initialize carryover display on modal open - fetch latest data
    $('#addMasterModal').on('shown.bs.modal', function() {
        // Fetch latest remaining budget from server
        $.ajax({
            url: 'get_last_remaining.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.remaining > 0) {
                    // Auto-fill carryover with latest data
                    $('#carryover_display').val(response.remaining_formatted);
                    $('#carryover_value').val(response.remaining);
                    $('#has_carryover').prop('checked', true);
                    $('#carryover_field').show();
                    
                    // Update info message
                    const infoMsg = '<i class="fas fa-info-circle"></i> Terdeteksi sisa budget sebesar <strong>Rp ' + 
                                   response.remaining_formatted + '</strong> dari periode sebelumnya';
                    $('#carryover_field').next('small').html(infoMsg).removeClass('text-muted').addClass('text-success');
                    
                    // Trigger calculation
                    updateTotalAmount();
                } else {
                    // No remaining budget
                    $('#carryover_display').val('');
                    $('#carryover_value').val('0');
                    $('#has_carryover').prop('checked', false);
                    $('#carryover_field').hide();
                    $('#total_info').hide();
                    
                    $('#carryover_field').next('small').html('Tidak ada sisa budget dari periode sebelumnya')
                        .removeClass('text-success').addClass('text-muted');
                }
            },
            error: function() {
                // Silent fail - no action needed
            }
        });
    });
    
    // Reset form on modal close
    $('#addMasterModal').on('hidden.bs.modal', function() {
        $('#amount_display').val('');
        $('#amount_value').val('0');
        // Carryover will be refreshed on next open
    });
    
    // Validate on form submit - Add Master Budget
    $('#addMasterModal form').on('submit', function(e) {
        var amountValue = parseInt($('#amount_value').val()) || 0;
        if (amountValue <= 0) {
            e.preventDefault();
            showErrorModal('Jumlah Dana Awal Tidak Valid', 
                '<p>Jumlah Dana Awal harus lebih besar dari <strong>Rp 0</strong></p>' +
                '<p class="text-muted mb-0">Silakan masukkan jumlah dana yang valid.</p>');
            $('#amount_display').focus();
            return false;
        }
        
        // Validasi: total saldo harus cukup untuk pengajuan yang dipilih
        var carryoverValue = parseInt($('#carryover_value').val()) || 0;
        var totalSaldo = amountValue + carryoverValue;
        var selectedTotal = getSelectedTotal();
        
        if (selectedTotal > 0 && totalSaldo < selectedTotal) {
            e.preventDefault();
            var kekurangan = selectedTotal - totalSaldo;
            showErrorModal('Saldo Tidak Mencukupi', 
                '<div class="alert alert-danger mb-3">' +
                '<i class="fas fa-exclamation-triangle"></i> <strong>Dana yang dimasukkan kurang dari total pengajuan!</strong>' +
                '</div>' +
                '<table class="table table-sm table-bordered">' +
                '<tr><td width="50%">Total Saldo</td><td class="text-right"><strong>Rp ' + formatRupiah(totalSaldo.toString()) + '</strong></td></tr>' +
                '<tr><td>Total Pengajuan Terpilih</td><td class="text-right text-danger"><strong>Rp ' + formatRupiah(selectedTotal.toString()) + '</strong></td></tr>' +
                '<tr class="table-danger"><td>Kekurangan</td><td class="text-right"><strong>Rp ' + formatRupiah(kekurangan.toString()) + '</strong></td></tr>' +
                '</table>' +
                '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Silakan tambah dana atau kurangi pengajuan yang dipilih.</p>');
            return false;
        }

        // Prevent double submit
        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        var originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        // Fallback re-enable if request takes too long or fails silently
        setTimeout(function() {
            submitBtn.prop('disabled', false).html(originalText);
        }, 8000);
        
        return true;
    });
    
    // Validate on form submit - Top-Up
    $('#topupModal form').on('submit', function(e) {
        var amountValue = parseInt($('#topup_amount_value').val()) || 0;
        if (amountValue <= 0) {
            e.preventDefault();
            showErrorModal('Jumlah Top-Up Tidak Valid', 
                '<p>Jumlah Top-Up harus lebih besar dari <strong>Rp 0</strong></p>' +
                '<p class="text-muted mb-0">Silakan masukkan jumlah top-up yang valid.</p>');
            $('#topup_amount_display').focus();
            return false;
        }
    });
    
    // Reset form when modal is closed
    $('#addMasterModal').on('hidden.bs.modal', function() {
        $('#amount_display').val('');
        $('#amount_value').val('');
    });
    
    $('#topupModal').on('hidden.bs.modal', function() {
        $('#topup_amount_display').val('');
        $('#topup_amount_value').val('');
    });
});

function openDeleteModal(masterId, description) {
    if (!masterId) {
        alert('Error: Master ID tidak valid!');
        return;
    }
    
    deleteMasterId = masterId;
    $('#delete_master_id').text(masterId);
    $('#delete_description').text(description);
    
    $('#deleteModal').modal('show');
}

function confirmDelete() {
    if (!deleteMasterId || deleteMasterId === '' || deleteMasterId === 'undefined') {
        alert('Error: Master ID tidak valid! Nilai: "' + deleteMasterId + '"');
        return false;
    }
    
    // Show loading state
    var btn = document.getElementById('confirmDeleteBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
    }
    
    // Redirect to delete
    var deleteUrl = 'master_delete.php?id=' + encodeURIComponent(deleteMasterId);
    
    setTimeout(function() {
        window.location.href = deleteUrl;
    }, 500);
    
    return false;
}
</script>


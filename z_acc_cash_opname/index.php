<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('realisasi_ppx', 'read');

init_acc_fase3_db($conn);
init_ppx_fase2_db($conn);

$user_id = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

$filter_cabang = isset($_GET['cabang']) ? (int)$_GET['cabang'] : 0;
$filter_status = trim($_GET['status'] ?? '');
$filter_month  = trim($_GET['bulan'] ?? date('Y-m'));

$where = ["1=1"];
if ($filter_cabang > 0) {
    $where[] = "co.id_cabang_ppx = $filter_cabang";
}
if (!empty($filter_status)) {
    $where[] = "co.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if (!empty($filter_month)) {
    $where[] = "DATE_FORMAT(co.tanggal_opname, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filter_month) . "'";
}
$where_sql = implode(' AND ', $where);

// List Opnames
$opnames_q = mysqli_query($conn, "
    SELECT co.*, pmc.nama_cabang, pmc.kode_cabang,
           u1.nama_user AS pembuat, u2.nama_user AS approver,
           jh.no_jurnal
    FROM acc_cash_opname co
    JOIN ppx_master_cabang pmc ON co.id_cabang_ppx = pmc.id_cabang_ppx
    LEFT JOIN user_dfm u1 ON co.created_by = u1.id_user
    LEFT JOIN user_dfm u2 ON co.approved_by = u2.id_user
    LEFT JOIN acc_jurnal_header jh ON co.id_jurnal_penyesuaian = jh.id_jurnal
    WHERE $where_sql
    ORDER BY co.tanggal_opname DESC, co.id_opname DESC
");

$opnames = [];
$cnt_klop = 0;
$cnt_kurang = 0;
$cnt_lebih = 0;

if ($opnames_q) {
    while ($r = mysqli_fetch_assoc($opnames_q)) {
        $opnames[] = $r;
        $sel = (float)$r['selisih'];
        if (abs($sel) < 0.01) {
            $cnt_klop++;
        } elseif ($sel < 0) {
            $cnt_kurang++;
        } else {
            $cnt_lebih++;
        }
    }
}

// Master Cabang untuk filter
$cabang_list_q = mysqli_query($conn, "SELECT id_cabang_ppx, kode_cabang, nama_cabang FROM ppx_master_cabang WHERE status = 'Aktif' ORDER BY nama_cabang ASC");
$cabang_options = [];
while ($c = mysqli_fetch_assoc($cabang_list_q)) $cabang_options[] = $c;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Opname Mingguan – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; padding: 18px 20px; }
        .stat-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; }
        .stat-val { font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .badge-klop { background-color: #dcfce7; color: #15803d; font-weight: 700; font-size: 12px; padding: 5px 10px; border-radius: 6px; }
        .badge-kurang { background-color: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 12px; padding: 5px 10px; border-radius: 6px; }
        .badge-lebih { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; font-size: 12px; padding: 5px 10px; border-radius: 6px; }
        .table-co th { background: #f8fafc; font-size: 13px; font-weight: 700; color: #334155; }
        .table-co td { font-size: 13.5px; vertical-align: middle; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>

    <div class="page-wrapper">
        <div class="content container-fluid">
            <!-- Header -->
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-coins text-warning mr-2"></i> Cash Opname (CO) Mingguan
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Akuntansi</li>
                            <li class="breadcrumb-item active">Cash Opname</li>
                        </ul>
                    </div>
                    <div class="col-sm-4 text-right">
                        <a href="create.php" class="btn btn-primary font-weight-bold shadow-sm">
                            <i class="fas fa-plus mr-1"></i> Buat Cash Opname Baru
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['error'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Stats Bar -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-clipboard-check mr-1 text-primary"></i> Total Pemeriksaan</div>
                        <div class="stat-val"><?= count($opnames) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-check-circle mr-1 text-success"></i> Kas Klop (Rp 0)</div>
                        <div class="stat-val text-success"><?= $cnt_klop ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-arrow-down mr-1 text-danger"></i> Selisih Kurang</div>
                        <div class="stat-val text-danger"><?= $cnt_kurang ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-arrow-up mr-1 text-info"></i> Selisih Lebih</div>
                        <div class="stat-val text-info"><?= $cnt_lebih ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                <div class="card-body py-3">
                    <form method="GET" action="index.php" class="row align-items-center">
                        <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Filter Cabang</label>
                            <select name="cabang" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="0">-- Semua Cabang --</option>
                                <?php foreach ($cabang_options as $cb): ?>
                                    <option value="<?= $cb['id_cabang_ppx'] ?>" <?= ($filter_cabang == $cb['id_cabang_ppx']) ? 'selected' : '' ?>>
                                        [<?= $cb['kode_cabang'] ?>] <?= htmlspecialchars($cb['nama_cabang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Bulan Opname</label>
                            <input type="month" name="bulan" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Status Opname</label>
                            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">-- Semua Status --</option>
                                <option value="Draft" <?= ($filter_status === 'Draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="Disetujui" <?= ($filter_status === 'Disetujui') ? 'selected' : '' ?>>Disetujui</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 text-right">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <a href="index.php" class="btn btn-sm btn-light border" title="Reset Filter"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm" style="border-radius:10px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="card-title font-weight-bold mb-0 text-dark">
                        Riwayat Pemeriksaan Fisik Kas (<?= count($opnames) ?> Laporan)
                    </h5>
                    <span class="badge badge-light border">Periode <?= date('F Y', strtotime($filter_month . '-01')) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-co">
                            <thead>
                                <tr>
                                    <th width="15%">No Opname</th>
                                    <th width="10%">Tanggal</th>
                                    <th width="14%">Cabang & Periode</th>
                                    <th width="13%" class="text-right">Saldo Buku</th>
                                    <th width="13%" class="text-right">Fisik Kas</th>
                                    <th width="14%" class="text-center">Hasil Selisih</th>
                                    <th width="12%">Pemeriksa</th>
                                    <th width="9%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($opnames)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-coins fa-3x mb-3 text-secondary d-block"></i>
                                            Belum ada laporan cash opname pada periode ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($opnames as $co): 
                                        $sel = (float)$co['selisih'];
                                    ?>
                                    <tr>
                                        <td class="font-weight-bold text-dark">
                                            <a href="detail.php?id=<?= $co['id_opname'] ?>" class="text-primary">
                                                <?= htmlspecialchars($co['no_opname']) ?>
                                            </a>
                                            <?php if (!empty($co['no_jurnal'])): ?>
                                                <br><span class="badge badge-info" title="Jurnal Penyesuaian"><i class="fas fa-book mr-1"></i><?= $co['no_jurnal'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($co['tanggal_opname'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($co['nama_cabang']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($co['periode_minggu']) ?></small>
                                        </td>
                                        <td class="text-right font-weight-bold text-dark">
                                            Rp <?= number_format((float)$co['saldo_buku'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-right font-weight-bold text-primary">
                                            Rp <?= number_format((float)$co['saldo_fisik'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (abs($sel) < 0.01): ?>
                                                <span class="badge-klop"><i class="fas fa-check mr-1"></i> Klop (Rp 0)</span>
                                            <?php elseif ($sel < 0): ?>
                                                <span class="badge-kurang"><i class="fas fa-minus-circle mr-1"></i> Kurang Rp <?= number_format(abs($sel), 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="badge-lebih"><i class="fas fa-plus-circle mr-1"></i> Lebih Rp <?= number_format($sel, 0, ',', '.') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($co['petugas_opname']) ?>
                                            <?php if (!empty($co['saksi_opname'])): ?>
                                                <br><small class="text-muted">Saksi: <?= htmlspecialchars($co['saksi_opname']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap: 4px;">
                                                <a href="detail.php?id=<?= $co['id_opname'] ?>" class="btn btn-sm btn-light border text-primary" title="Lihat Rincian">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="cetak_opname.php?id=<?= $co['id_opname'] ?>" target="_blank" class="btn btn-sm btn-light border text-secondary" title="Cetak Berita Acara">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <?php if ($is_admin): ?>
                                                    <a href="action.php?action=delete_opname&id=<?= $co['id_opname'] ?>" class="btn btn-sm btn-light border text-danger" title="Hapus Opname" data-confirm="Apakah Anda yakin ingin menghapus laporan Cash Opname ini?" data-confirm-type="delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
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
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
</body>
</html>

<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_budget', 'read');

init_acc_fase3_db($conn);

$filter_tipe  = trim($_GET['tipe'] ?? '');
$filter_start = trim($_GET['start_date'] ?? date('Y-m-01'));
$filter_end   = trim($_GET['end_date'] ?? date('Y-m-t'));
$filter_q     = trim($_GET['q'] ?? '');

$where = ["1=1"];
if (!empty($filter_tipe)) {
    $where[] = "jh.tipe_jurnal = '" . mysqli_real_escape_string($conn, $filter_tipe) . "'";
}
if (!empty($filter_start)) {
    $where[] = "jh.tanggal_jurnal >= '" . mysqli_real_escape_string($conn, $filter_start) . "'";
}
if (!empty($filter_end)) {
    $where[] = "jh.tanggal_jurnal <= '" . mysqli_real_escape_string($conn, $filter_end) . "'";
}
if (!empty($filter_q)) {
    $q_esc = mysqli_real_escape_string($conn, $filter_q);
    $where[] = "(jh.no_jurnal LIKE '%$q_esc%' OR jh.referensi_no LIKE '%$q_esc%' OR jh.keterangan LIKE '%$q_esc%')";
}
$where_sql = implode(' AND ', $where);

// Ambil Header Jurnal
$jurnals_q = mysqli_query($conn, "
    SELECT jh.*, u.nama_user AS pembuat
    FROM acc_jurnal_header jh
    LEFT JOIN user_dfm u ON jh.created_by = u.id_user
    WHERE $where_sql
    ORDER BY jh.tanggal_jurnal DESC, jh.id_jurnal DESC
");

$jurnal_ids = [];
$jurnals = [];
$sum_debit = 0;
$sum_kredit = 0;

if ($jurnals_q) {
    while ($j = mysqli_fetch_assoc($jurnals_q)) {
        $jurnals[] = $j;
        $jurnal_ids[] = (int)$j['id_jurnal'];
        $sum_debit += (float)$j['total_debit'];
        $sum_kredit += (float)$j['total_kredit'];
    }
}

// Ambil Detail Jurnal per Header
$details_by_jurnal = [];
if (!empty($jurnal_ids)) {
    $ids_str = implode(',', $jurnal_ids);
    $det_q = mysqli_query($conn, "
        SELECT jd.*, c.kode_akun, c.nama_akun, c.kategori_akun
        FROM acc_jurnal_detail jd
        JOIN acc_coa c ON jd.id_coa = c.id_coa
        WHERE jd.id_jurnal IN ($ids_str)
        ORDER BY jd.id_jurnal DESC, jd.posisi ASC, jd.id_jurnal_detail ASC
    ");
    if ($det_q) {
        while ($d = mysqli_fetch_assoc($det_q)) {
            $details_by_jurnal[$d['id_jurnal']][] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jurnal Umum & Otomatis – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; padding: 18px 20px; }
        .stat-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; }
        .stat-val { font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        
        .jurnal-entry { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .jurnal-head  { background: #f8fafc; padding: 12px 18px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-jurnal th { background: #f1f5f9; font-size: 12.5px; font-weight: 700; color: #334155; }
        .table-jurnal td { font-size: 13px; vertical-align: middle; }
        .kredit-indent { padding-left: 36px !important; }
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
                    <div class="col-sm-7">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-book-open text-primary mr-2"></i> Jurnal Umum & Jurnal Otomatis
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Akuntansi</li>
                            <li class="breadcrumb-item active">Jurnal Umum</li>
                        </ul>
                    </div>
                    <div class="col-sm-5 text-right">
                        <a href="buku_besar.php" class="btn btn-outline-info font-weight-bold mr-2 shadow-sm">
                            <i class="fas fa-layer-group mr-1"></i> Buku Besar (Ledger)
                        </a>
                        <a href="create.php" class="btn btn-primary font-weight-bold shadow-sm">
                            <i class="fas fa-plus mr-1"></i> Input Jurnal Manual
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
                        <div class="stat-label"><i class="fas fa-file-invoice mr-1 text-primary"></i> Total Entri Jurnal</div>
                        <div class="stat-val"><?= count($jurnals) ?> Transaksi</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-arrow-down mr-1 text-info"></i> Total Debit</div>
                        <div class="stat-val text-info">Rp <?= number_format($sum_debit, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-arrow-up mr-1 text-primary"></i> Total Kredit</div>
                        <div class="stat-val text-primary">Rp <?= number_format($sum_kredit, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-balance-scale mr-1 text-success"></i> Keseimbangan</div>
                        <div class="stat-val text-success">
                            <?php if (abs($sum_debit - $sum_kredit) < 0.01): ?>
                                100% Balanced
                            <?php else: ?>
                                <span class="text-danger">Selisih Rp <?= number_format(abs($sum_debit - $sum_kredit), 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                <div class="card-body py-3">
                    <form method="GET" action="index.php" class="row align-items-center">
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Tipe Jurnal</label>
                            <select name="tipe" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">-- Semua Tipe Jurnal --</option>
                                <option value="Umum" <?= ($filter_tipe === 'Umum') ? 'selected' : '' ?>>Jurnal Umum Manual (JU)</option>
                                <option value="BKK" <?= ($filter_tipe === 'BKK') ? 'selected' : '' ?>>Otomatis Kas Keluar (BKK)</option>
                                <option value="BKM" <?= ($filter_tipe === 'BKM') ? 'selected' : '' ?>>Otomatis Kas Masuk (BKM)</option>
                                <option value="Mutasi_Bank" <?= ($filter_tipe === 'Mutasi_Bank') ? 'selected' : '' ?>>Otomatis Mutasi Bank</option>
                                <option value="Penyesuaian" <?= ($filter_tipe === 'Penyesuaian') ? 'selected' : '' ?>>Jurnal Penyesuaian (CO / Opname)</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Dari Tanggal</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_start) ?>">
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Sampai Tanggal</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_end) ?>">
                        </div>
                        <div class="col-md-3 col-sm-8 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Cari No / Ref / Keterangan</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="mis. JU/2026, BKK/JKT..." value="<?= htmlspecialchars($filter_q) ?>">
                        </div>
                        <div class="col-md-2 col-sm-4 text-right">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary font-weight-bold"><i class="fas fa-search"></i></button>
                            <a href="index.php" class="btn btn-sm btn-light border" title="Reset"><i class="fas fa-undo"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List Jurnal Entries -->
            <?php if (empty($jurnals)): ?>
                <div class="card border-0 shadow-sm text-center py-5" style="border-radius:10px;">
                    <i class="fas fa-book-open fa-3x mb-3 text-secondary d-block"></i>
                    <h5 class="text-muted">Tidak ada transaksi jurnal pada periode yang dipilih.</h5>
                    <p class="text-muted small">Silakan ubah filter tanggal atau klik "+ Input Jurnal Manual" untuk membuat jurnal baru.</p>
                </div>
            <?php else: ?>
                <?php foreach ($jurnals as $j): 
                    $lines = $details_by_jurnal[$j['id_jurnal']] ?? [];
                    $badge_class = 'badge-primary';
                    if ($j['tipe_jurnal'] === 'BKK') $badge_class = 'badge-danger';
                    elseif ($j['tipe_jurnal'] === 'BKM') $badge_class = 'badge-success';
                    elseif ($j['tipe_jurnal'] === 'Mutasi_Bank') $badge_class = 'badge-info';
                    elseif ($j['tipe_jurnal'] === 'Penyesuaian') $badge_class = 'badge-warning text-dark';
                ?>
                <div class="jurnal-entry">
                    <div class="jurnal-head">
                        <div>
                            <span class="badge <?= $badge_class ?> mr-2 px-2 py-1 font-weight-bold" style="font-size: 11px;">
                                <?= htmlspecialchars(str_replace('_', ' ', $j['tipe_jurnal'])) ?>
                            </span>
                            <strong class="text-dark" style="font-size: 14.5px;"><?= htmlspecialchars($j['no_jurnal']) ?></strong>
                            <span class="text-muted mx-2">&bull;</span>
                            <span class="text-secondary"><i class="far fa-calendar-alt mr-1"></i><?= date('d M Y', strtotime($j['tanggal_jurnal'])) ?></span>
                            <?php if (!empty($j['referensi_no'])): ?>
                                <span class="badge badge-light border text-dark ml-2">Ref: <?= htmlspecialchars($j['referensi_no']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 12px;">
                            <?php if ($j['is_balanced']): ?>
                                <span class="badge badge-success px-2 py-1" style="font-size: 11px;"><i class="fas fa-check mr-1"></i> Balanced</span>
                            <?php else: ?>
                                <span class="badge badge-danger px-2 py-1" style="font-size: 11px;"><i class="fas fa-exclamation-triangle mr-1"></i> Unbalanced</span>
                            <?php endif; ?>
                            <?php if ($auth->isSuperAdmin() && in_array($j['tipe_jurnal'], ['Umum', 'Penyesuaian'])): ?>
                                <a href="action.php?action=delete_jurnal&id=<?= $j['id_jurnal'] ?>" class="btn btn-sm btn-link text-danger p-0" title="Hapus Jurnal" data-confirm="Apakah Anda yakin ingin menghapus entri jurnal ini?" data-confirm-type="delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="px-3 py-2 bg-light border-bottom text-muted small">
                        <i class="fas fa-comment-dots mr-1"></i> <strong>Keterangan:</strong> <?= htmlspecialchars($j['keterangan']) ?>
                        <span class="float-right"><i class="fas fa-user-edit mr-1"></i> Diposting oleh: <?= htmlspecialchars($j['pembuat'] ?: 'Sistem') ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 table-jurnal">
                            <thead>
                                <tr>
                                    <th width="15%">Kode Akun</th>
                                    <th width="40%">Nama Akun COA & Memo</th>
                                    <th width="20%" class="text-right">Debit (Rp)</th>
                                    <th width="20%" class="text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lines as $ln): 
                                    $is_kredit = ($ln['posisi'] === 'Kredit');
                                ?>
                                <tr>
                                    <td class="font-weight-bold text-secondary">
                                        <?= htmlspecialchars($ln['kode_akun']) ?>
                                    </td>
                                    <td class="<?= $is_kredit ? 'kredit-indent' : '' ?>">
                                        <strong class="<?= $is_kredit ? 'text-secondary' : 'text-dark' ?>">
                                            <?= htmlspecialchars($ln['nama_akun']) ?>
                                        </strong>
                                        <?php if (!empty($ln['memo'])): ?>
                                            <br><small class="text-muted font-italic"><?= htmlspecialchars($ln['memo']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right font-weight-bold text-dark">
                                        <?= !$is_kredit ? number_format((float)$ln['nominal'], 0, ',', '.') : '–' ?>
                                    </td>
                                    <td class="text-right font-weight-bold text-dark">
                                        <?= $is_kredit ? number_format((float)$ln['nominal'], 0, ',', '.') : '–' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-light font-weight-bold">
                                    <td colspan="2" class="text-right">TOTAL:</td>
                                    <td class="text-right text-info">Rp <?= number_format((float)$j['total_debit'], 0, ',', '.') ?></td>
                                    <td class="text-right text-primary">Rp <?= number_format((float)$j['total_kredit'], 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
</body>
</html>

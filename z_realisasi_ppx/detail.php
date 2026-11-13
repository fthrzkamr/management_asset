<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('realisasi_ppx', 'read');

$user_id   = (int)$_SESSION['id_user'];
$is_admin  = $auth->isSuperAdmin();
$id_budget = isset($_GET['id_budget']) ? (int)$_GET['id_budget'] : 0;

if (!$id_budget) {
    header("Location: dashboard.php");
    exit();
}

$bulan_names = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// ── Ambil data Monthly Budget ────────────────────────────────────────────
$filter_owner = $is_admin ? "" : "AND mb.created_by = $user_id";
$mb_q = mysqli_query($conn, "
    SELECT mb.*, u.nama_user AS nama_pembuat, d.nama_divisi, mc.nama_cabang
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN divisi d   ON u.divisi = d.id_divisi
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    WHERE mb.id_budget = $id_budget $filter_owner
    LIMIT 1");

if (!$mb_q || mysqli_num_rows($mb_q) === 0) {
    $_SESSION['error'] = 'Anggaran bulanan tidak ditemukan atau akses ditolak.';
    header("Location: dashboard.php");
    exit();
}
$budget = mysqli_fetch_assoc($mb_q);

// ── Ambil item detail dari anggaran ini ──────────────────────────────────
$items_q = mysqli_query($conn, "
    SELECT
        d.id AS id_detail, d.rincian, d.qty, d.satuan, d.jumlah_hari, d.jumlah_minggu,
        d.harga_satuan, d.total,
        c.category_name, c.category_code
    FROM ppx_monthly_budget_item_detail d
    LEFT JOIN ppx_budget_category c ON d.id_category = c.id_category
    WHERE d.id_budget = $id_budget
    ORDER BY c.category_name, d.id ASC");

$items = [];
if ($items_q) while ($r = mysqli_fetch_assoc($items_q)) $items[] = $r;

// Kelompokkan per kategori
$per_kategori = [];
foreach ($items as $it) {
    $cat = $it['category_name'] ?: 'Tanpa Kategori';
    $per_kategori[$cat][] = $it;
}

// ── Ambil SEMUA riwayat realisasi untuk anggaran ini ────────────────────
$real_by_detail = []; // [id_detail_budget => [ list of realizations ]]
$all_real_q = mysqli_query($conn, "
    SELECT rd.*, u.nama_user as approver_name
    FROM ppx_realisasi_detail2 rd
    LEFT JOIN user_dfm u ON rd.approved_by = u.id_user
    WHERE rd.id_budget = $id_budget
    ORDER BY rd.tanggal_realisasi DESC, rd.id_real_detail DESC
");
if ($all_real_q) {
    while ($rl = mysqli_fetch_assoc($all_real_q)) {
        $real_by_detail[$rl['id_detail_budget']][] = $rl;
    }
}

// Hitung total ringkasan anggaran
$total_alokasi_budget  = (float)$budget['total_allocation'];
$total_approved_budget = 0;
$total_pending_budget  = 0;
$items_realized_count  = 0;

foreach ($items as $it) {
    $item_reals = $real_by_detail[$it['id_detail']] ?? [];
    $item_app = 0;
    foreach ($item_reals as $r) {
        if ($r['status'] === 'Disetujui') {
            $total_approved_budget += (float)$r['nominal_realisasi'];
            $item_app += (float)$r['nominal_realisasi'];
        } elseif ($r['status'] === 'Menunggu Approval') {
            $total_pending_budget += (float)$r['nominal_realisasi'];
        }
    }
    if ($item_app > 0) {
        $items_realized_count++;
    }
}

$total_sisa_budget = max(0, $total_alokasi_budget - $total_approved_budget);
$budget_progress_pct = $total_alokasi_budget > 0 ? min(100, round(($total_approved_budget / $total_alokasi_budget) * 100, 1)) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Realisasi Per Detail – <?= htmlspecialchars($budget['budget_name']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-block { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; background: #fff; }
        .category-head  { background: #f8fafc; padding: 12px 18px; font-weight: 700; font-size: 14px;
                          border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        
        .summary-card   { border-radius: 10px; border: none; padding: 16px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .info-box       { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 22px; }
        .info-grid      { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 20px; font-size: 13px; }
        .lbl            { color: #64748b; font-size: 12px; margin-bottom: 2px; }
        .val            { font-weight: 600; color: #1e293b; font-size: 13.5px; }

        .item-card      { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 16px; background: #fff; transition: box-shadow .2s; }
        .item-card:hover{ box-shadow: 0 3px 10px rgba(0,0,0,.04); }
        .item-head      { background: #ffffff; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .item-nama      { font-weight: 700; font-size: 14.5px; color: #1e293b; }
        .item-meta      { font-size: 12px; color: #64748b; }
        
        .real-badge     { border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .rb-draft       { background: #ede9fe; color: #6d28d9; }
        .rb-wait        { background: #fef3c7; color: #b45309; }
        .rb-ok          { background: #dcfce7; color: #15803d; }
        .rb-rej         { background: #fee2e2; color: #b91c1c; }

        .real-table     { width: 100%; font-size: 12.5px; margin-top: 8px; border-collapse: separate; border-spacing: 0; }
        .real-table th  { background: #f8fafc; color: #64748b; font-weight: 600; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 11.5px; }
        .real-table td  { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .real-table tr:last-child td { border-bottom: none; }

        .form-real-box  { display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-top: 12px; }
        .bukti-link     { display: inline-flex; align-items: center; gap: 4px; background: #e0f2fe; border-radius: 5px; padding: 2px 8px; font-size: 11px; color: #0369a1; text-decoration: none; margin: 2px; }
        .bukti-link:hover { background: #bae6fd; text-decoration: none; }
        .prev-item      { background: #e0f2fe; border-radius: 5px; padding: 3px 8px; font-size: 11px; color: #0369a1; display: inline-block; margin: 2px; }
    </style>
</head>
<body style="background: #f4f6f9;">
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="fa-solid fa-receipt text-primary mr-1"></i> Realisasi Detail Anggaran
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Realisasi Bulanan</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($budget['budget_name']) ?></li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php foreach (['success', 'error'] as $t): if (isset($_SESSION[$t])): ?>
            <div class="alert alert-<?= $t === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <i class="fas fa-<?= $t === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i>
                <?= htmlspecialchars($_SESSION[$t]) ?>
                <button class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION[$t]); endif; endforeach; ?>

            <!-- Info Anggaran Header -->
            <div class="info-box">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">
                            <?= htmlspecialchars($budget['budget_name']) ?>
                        </h5>
                        <span class="text-muted small">
                            <i class="far fa-calendar-alt mr-1"></i> Periode <?= $bulan_names[$budget['period_month']] ?> <?= $budget['period_year'] ?>
                            &nbsp;·&nbsp; Cabang: <strong><?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></strong>
                            &nbsp;·&nbsp; Pembuat: <strong><?= htmlspecialchars($budget['nama_pembuat'] ?? '-') ?></strong>
                        </span>
                    </div>
                    <div>
                        <span class="badge badge-<?= $budget['status'] === 'Active' ? 'success' : 'warning' ?> p-2 px-3" style="font-size:12px;">
                            Status Budget: <?= htmlspecialchars($budget['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- 4 Summary Cards -->
                <div class="row mt-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="summary-card bg-primary text-white">
                            <div class="small" style="opacity:0.85;">Total Alokasi Anggaran</div>
                            <h5 class="font-weight-bold mb-0 mt-1">Rp <?= number_format($total_alokasi_budget, 0, ',', '.') ?></h5>
                            <small class="d-block mt-1" style="font-size:11px; opacity:0.8;"><?= count($items) ?> Item Rincian</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="summary-card bg-success text-white">
                            <div class="small" style="opacity:0.85;">Realisasi Disetujui</div>
                            <h5 class="font-weight-bold mb-0 mt-1">Rp <?= number_format($total_approved_budget, 0, ',', '.') ?></h5>
                            <small class="d-block mt-1" style="font-size:11px; opacity:0.8;"><?= $budget_progress_pct ?>% Terpakai (<?= $items_realized_count ?> item)</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="summary-card bg-warning text-dark">
                            <div class="small text-muted font-weight-bold">Menunggu Approval</div>
                            <h5 class="font-weight-bold mb-0 mt-1">Rp <?= number_format($total_pending_budget, 0, ',', '.') ?></h5>
                            <small class="d-block mt-1 text-muted" style="font-size:11px;">Dalam proses verifikasi Asmen</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="summary-card bg-info text-white">
                            <div class="small" style="opacity:0.85;">Sisa Alokasi</div>
                            <h5 class="font-weight-bold mb-0 mt-1">Rp <?= number_format($total_sisa_budget, 0, ',', '.') ?></h5>
                            <small class="d-block mt-1" style="font-size:11px; opacity:0.8;">Tersedia untuk direalisasikan</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Petunjuk Pengaju -->
            <div class="alert alert-light border mb-4" style="font-size:12.5px; background:#fff;">
                <i class="fas fa-info-circle text-primary mr-1"></i>
                <strong>Petunjuk Realisasi Per Detail Item:</strong>
                Anda dapat merealisasikan rincian item pengajuan secara mandiri dan berkali-kali. Klik tombol <strong>"+ Input Realisasi"</strong> pada masing-masing item untuk memasukkan nota/struk pengeluaran aktual tanpa perlu menunggu item lain selesai.
            </div>

            <!-- Rincian Item Anggaran -->
            <h5 class="mb-3 font-weight-bold">
                <i class="fas fa-list-check text-primary mr-1"></i> Rincian Item Anggaran & Histori Realisasi
            </h5>

            <?php if (empty($items)): ?>
                <div class="card"><div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Tidak ada rincian item dalam anggaran ini.</p>
                </div></div>
            <?php else: ?>
                <?php foreach ($per_kategori as $cat_name => $cat_items): ?>
                <div class="category-block">
                    <div class="category-head">
                        <span><i class="fas fa-tags text-primary mr-2"></i><?= htmlspecialchars($cat_name) ?></span>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold" style="font-size:12px;">
                            Alokasi: Rp <?= number_format(array_sum(array_column($cat_items, 'total')), 0, ',', '.') ?>
                        </span>
                    </div>

                    <div class="p-3">
                    <?php foreach ($cat_items as $it): 
                        $it_id = (int)$it['id_detail'];
                        $item_reals = $real_by_detail[$it_id] ?? [];
                        
                        $it_alokasi  = (float)$it['total'];
                        $it_approved = 0;
                        $it_pending  = 0;
                        foreach ($item_reals as $r) {
                            if ($r['status'] === 'Disetujui')        $it_approved += (float)$r['nominal_realisasi'];
                            elseif ($r['status'] === 'Menunggu Approval') $it_pending += (float)$r['nominal_realisasi'];
                        }
                        $it_sisa = max(0, $it_alokasi - $it_approved);
                        
                        $meta_parts = [];
                        if ($it['qty'])          $meta_parts[] = $it['qty'] . ' ' . $it['satuan'];
                        if ($it['jumlah_hari'])  $meta_parts[] = $it['jumlah_hari'] . ' hari';
                        if ($it['jumlah_minggu'])$meta_parts[] = $it['jumlah_minggu'] . ' minggu';
                        if ($it['harga_satuan']) $meta_parts[] = 'Rp ' . number_format($it['harga_satuan'], 0, ',', '.') . ' / satuan';
                    ?>
                    <div class="item-card">
                        <!-- Head item -->
                        <div class="item-head">
                            <div style="flex:1;">
                                <div class="item-nama"><?= htmlspecialchars($it['rincian']) ?></div>
                                <div class="item-meta mt-1"><?= implode(' &nbsp;·&nbsp; ', $meta_parts) ?></div>
                                <div class="mt-2 d-flex flex-wrap align-items-center" style="gap:14px; font-size:12.5px;">
                                    <span>Alokasi: <strong class="text-primary">Rp <?= number_format($it_alokasi, 0, ',', '.') ?></strong></span>
                                    <span>Terealisasi: <strong class="text-success">Rp <?= number_format($it_approved, 0, ',', '.') ?></strong></span>
                                    <?php if ($it_pending > 0): ?>
                                    <span>Menunggu: <strong class="text-warning">Rp <?= number_format($it_pending, 0, ',', '.') ?></strong></span>
                                    <?php endif; ?>
                                    <span>Sisa: <strong class="<?= $it_sisa > 0 ? 'text-info' : 'text-muted' ?>">Rp <?= number_format($it_sisa, 0, ',', '.') ?></strong></span>
                                </div>
                            </div>
                            <div class="text-right" style="min-width:140px;">
                                <div>
                                    <?php if ($it_approved >= $it_alokasi && $it_alokasi > 0): ?>
                                        <span class="real-badge rb-ok"><i class="fas fa-check-double"></i> Selesai Direalisasikan</span>
                                    <?php elseif ($it_pending > 0): ?>
                                        <span class="real-badge rb-wait"><i class="fas fa-clock"></i> Ada Menunggu Approval</span>
                                    <?php elseif ($it_approved > 0): ?>
                                        <span class="real-badge rb-ok" style="background:#e0f2fe; color:#0369a1;"><i class="fas fa-pie-chart"></i> Sebagian Terealisasi</span>
                                    <?php else: ?>
                                        <span class="real-badge rb-draft" style="background:#f1f5f9; color:#64748b;"><i class="fas fa-minus-circle"></i> Belum Realisasi</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="openFormRealisasi(<?= $it_id ?>, 0)">
                                        <i class="fas fa-plus mr-1"></i> Input Realisasi
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Form Box (Untuk Create & Edit) -->
                        <div class="form-real-box" id="formBox_<?= $it_id ?>">
                            <h6 class="font-weight-bold mb-3 text-primary" id="formTitle_<?= $it_id ?>">
                                <i class="fas fa-pen-to-square mr-1"></i> Form Input Realisasi Item
                            </h6>
                            <form action="save_realisasi.php" method="POST" enctype="multipart/form-data" id="realForm_<?= $it_id ?>">
                                <input type="hidden" name="id_budget" value="<?= $id_budget ?>">
                                <input type="hidden" name="id_detail" value="<?= $it_id ?>">
                                <input type="hidden" name="id_real_detail" id="idRealDetail_<?= $it_id ?>" value="0">

                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold mb-1">Tanggal Realisasi <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal_realisasi" id="tgl_<?= $it_id ?>" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold mb-1">Nominal Aktual <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                            <input type="text" name="nominal_realisasi" id="nom_<?= $it_id ?>" class="form-control rupiah-input" placeholder="0" required>
                                        </div>
                                        <small class="text-muted" style="font-size:10.5px;">Sisa alokasi: Rp <?= number_format($it_sisa, 0, ',', '.') ?></small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold mb-1">Uang Kembali (Opsional)</label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                            <input type="text" name="uang_kembali" id="uk_<?= $it_id ?>" class="form-control rupiah-input" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold mb-1">Catatan Pengeluaran</label>
                                        <input type="text" name="catatan" id="cat_<?= $it_id ?>" class="form-control form-control-sm" placeholder="Keterangan pembelian / nota">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="small font-weight-bold mb-1">
                                            Upload Bukti Nota / Kwitansi / Transfer <span class="text-danger">*</span>
                                        </label>
                                        <input type="file" name="bukti[]" class="form-control form-control-sm bukti-input" data-idx="<?= $it_id ?>" accept=".jpg,.jpeg,.png,.pdf" multiple>
                                        <small class="text-muted" style="font-size:10.5px;">Format JPG, PNG, atau PDF (Maksimal 5 file × 5MB). Lampirkan foto nota/struk pembelian asli.</small>
                                        <div class="mt-1" id="prev_<?= $it_id ?>"></div>
                                    </div>
                                    <div class="col-12 mt-3 d-flex justify-content-end" style="gap:8px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeFormRealisasi(<?= $it_id ?>)">Batal</button>
                                        <button type="submit" name="action" value="draft" class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-save mr-1"></i> Simpan Draft
                                        </button>
                                        <button type="submit" name="action" value="submit" class="btn btn-sm btn-success" onclick="return confirm('Kirim realisasi item ini ke Asmen Finance?');">
                                            <i class="fas fa-paper-plane mr-1"></i> Kirim ke Asmen
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Daftar Riwayat Realisasi untuk Item ini -->
                        <div class="p-3 pt-0">
                            <?php if (empty($item_reals)): ?>
                                <div class="text-muted small py-2 font-italic" style="font-size:12px;">
                                    <i class="fas fa-info-circle mr-1"></i> Belum ada realisasi untuk item ini.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="real-table">
                                        <thead>
                                            <tr>
                                                <th width="4%">No</th>
                                                <th width="12%">No. Realisasi</th>
                                                <th width="11%">Tanggal</th>
                                                <th width="14%" class="text-right">Nominal Realisasi</th>
                                                <th width="11%" class="text-right">Uang Kembali</th>
                                                <th width="20%">Catatan</th>
                                                <th width="12%">Bukti</th>
                                                <th width="12%">Status</th>
                                                <th width="8%" class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $r_no = 1; foreach ($item_reals as $rl): 
                                                $st = $rl['status'];
                                                $st_badge = 'rb-wait';
                                                $st_lbl = $st;
                                                if ($st === 'Disetujui') {
                                                    $st_badge = 'rb-ok';
                                                    $st_lbl = '<i class="fas fa-check-circle mr-1"></i> Disetujui';
                                                } elseif ($st === 'Ditolak') {
                                                    $st_badge = 'rb-rej';
                                                    $st_lbl = '<i class="fas fa-times-circle mr-1"></i> Ditolak';
                                                } elseif ($st === 'Draft') {
                                                    $st_badge = 'rb-draft';
                                                    $st_lbl = '<i class="fas fa-pencil mr-1"></i> Draft';
                                                } else {
                                                    $st_lbl = '<i class="fas fa-clock mr-1"></i> Menunggu Asmen';
                                                }
                                                $can_edit = in_array($st, ['Draft', 'Ditolak']);
                                            ?>
                                            <tr>
                                                <td><?= $r_no++ ?></td>
                                                <td><span class="font-weight-bold text-dark"><?= htmlspecialchars($rl['no_realisasi'] ?: '-') ?></span></td>
                                                <td><?= $rl['tanggal_realisasi'] ? date('d/m/Y', strtotime($rl['tanggal_realisasi'])) : '-' ?></td>
                                                <td class="text-right font-weight-bold text-success">
                                                    Rp <?= number_format($rl['nominal_realisasi'], 0, ',', '.') ?>
                                                </td>
                                                <td class="text-right text-muted">
                                                    <?= $rl['uang_kembali'] > 0 ? 'Rp ' . number_format($rl['uang_kembali'], 0, ',', '.') : '—' ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($rl['catatan'] ?: '—') ?>
                                                    <?php if ($rl['catatan_approval']): ?>
                                                    <div class="text-danger small mt-1 font-italic">
                                                        <i class="fas fa-comment mr-1"></i> Catatan Asmen: <?= htmlspecialchars($rl['catatan_approval']) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($rl['bukti_files'])): ?>
                                                        <?php foreach (explode(',', $rl['bukti_files']) as $bf): 
                                                            $bf = trim($bf); if (!$bf) continue;
                                                            $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                                            $ic = in_array($ext, ['jpg','jpeg','png']) ? 'fa-image' : 'fa-file-pdf';
                                                        ?>
                                                        <a href="../uploads/realisasi/<?= htmlspecialchars($bf) ?>" target="_blank" class="bukti-link">
                                                            <i class="fas <?= $ic ?>"></i> <?= htmlspecialchars($bf) ?>
                                                        </a>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="real-badge <?= $st_badge ?>"><?= $st_lbl ?></span>
                                                    <?php if ($rl['approved_by'] && $st === 'Disetujui'): ?>
                                                    <div class="text-muted" style="font-size:10px;">Oleh: <?= htmlspecialchars($rl['approver_name'] ?? 'Asmen') ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($can_edit): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-info btn-sm p-1 px-2"
                                                                title="Edit Realisasi"
                                                                onclick="editRealisasi(<?= $it_id ?>, <?= htmlspecialchars(json_encode($rl)) ?>)">
                                                            <i class="fas fa-edit fa-xs"></i>
                                                        </button>
                                                        <?php if ($st === 'Draft'): ?>
                                                        <form action="save_realisasi.php" method="POST" style="display:inline;" onsubmit="return confirm('Hapus draft realisasi ini?');">
                                                            <input type="hidden" name="id_budget" value="<?= $id_budget ?>">
                                                            <input type="hidden" name="id_detail" value="<?= $it_id ?>">
                                                            <input type="hidden" name="id_real_detail" value="<?= $rl['id_real_detail'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2" title="Hapus Draft">
                                                                <i class="fas fa-trash fa-xs"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="fas fa-lock"></i></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div> <!-- /item-card -->
                    <?php endforeach; ?>
                    </div> <!-- /category body -->
                </div> <!-- /category-block -->
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>

<script>
function openFormRealisasi(itemId, realDetailId) {
    document.getElementById('formBox_' + itemId).style.display = 'block';
    document.getElementById('idRealDetail_' + itemId).value = '0';
    document.getElementById('formTitle_' + itemId).innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Tambah Realisasi Baru';
    document.getElementById('nom_' + itemId).value = '';
    document.getElementById('uk_' + itemId).value = '';
    document.getElementById('cat_' + itemId).value = '';
    document.getElementById('tgl_' + itemId).value = '<?= date('Y-m-d') ?>';
    document.getElementById('prev_' + itemId).innerHTML = '';
}

function closeFormRealisasi(itemId) {
    document.getElementById('formBox_' + itemId).style.display = 'none';
}

function editRealisasi(itemId, data) {
    document.getElementById('formBox_' + itemId).style.display = 'block';
    document.getElementById('formTitle_' + itemId).innerHTML = '<i class="fas fa-pen-to-square mr-1"></i> Edit Realisasi #' + (data.no_realisasi || '');
    document.getElementById('idRealDetail_' + itemId).value = data.id_real_detail;
    document.getElementById('tgl_' + itemId).value = data.tanggal_realisasi || '<?= date('Y-m-d') ?>';
    document.getElementById('nom_' + itemId).value = formatNumber(parseInt(data.nominal_realisasi || 0));
    document.getElementById('uk_' + itemId).value = data.uang_kembali > 0 ? formatNumber(parseInt(data.uang_kembali)) : '';
    document.getElementById('cat_' + itemId).value = data.catatan || '';
    
    // Tampilkan file lama
    let prev = document.getElementById('prev_' + itemId);
    prev.innerHTML = '';
    if (data.bukti_files) {
        let files = data.bukti_files.split(',');
        files.forEach(function(bf) {
            bf = bf.trim();
            if (!bf) return;
            let el = document.createElement('a');
            el.href = '../uploads/realisasi/' + bf;
            el.target = '_blank';
            el.className = 'bukti-link mr-1';
            el.innerHTML = '<i class="fas fa-paperclip"></i> ' + bf;
            prev.appendChild(el);
        });
    }
}

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Preview file upload
document.querySelectorAll('.bukti-input').forEach(function(inp) {
    inp.addEventListener('change', function() {
        var idx = this.dataset.idx;
        var prev = document.getElementById('prev_' + idx);
        if (!prev) return;
        var maxSize = 5 * 1024 * 1024;
        if (this.files.length > 5) {
            alert('Maksimal 5 file lampiran!');
            this.value = '';
            return;
        }
        Array.from(this.files).forEach(function(file) {
            if (file.size > maxSize) {
                alert(file.name + ' terlalu besar (maksimal 5MB)!');
                return;
            }
            var ext = file.name.split('.').pop().toLowerCase();
            var icon = ['jpg', 'jpeg', 'png'].includes(ext) ? 'fa-image' : 'fa-file-pdf';
            var el = document.createElement('span');
            el.className = 'prev-item';
            el.innerHTML = '<i class="fas ' + icon + '"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            prev.appendChild(el);
        });
    });
});

// Format Rupiah Input
document.querySelectorAll('.rupiah-input').forEach(function(inp) {
    inp.addEventListener('input', function(e) {
        let val = this.value.replace(/[^,\d]/g, '');
        let split = val.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        this.value = rupiah;
    });
});
</script>
</body>
</html>

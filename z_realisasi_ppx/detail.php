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
    SELECT rd.*, u.nama_user as approver_name, mbd.rincian as nama_item, bc.category_name
    FROM ppx_realisasi_detail2 rd
    LEFT JOIN user_dfm u ON rd.approved_by = u.id_user
    LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
    LEFT JOIN ppx_budget_category bc ON mbd.id_category = bc.id_category
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
$total_kembali_budget  = 0;
$items_realized_count  = 0;

foreach ($items as $it) {
    $item_reals = $real_by_detail[$it['id_detail']] ?? [];
    $item_app = 0;
    foreach ($item_reals as $r) {
        if ($r['status'] === 'Disetujui') {
            if ($r['jenis_transaksi'] === 'BKM') {
                $total_kembali_budget += (float)$r['uang_kembali'];
            } else {
                $total_approved_budget += (float)$r['nominal_realisasi'];
                $item_app += (float)$r['nominal_realisasi'];
            }
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
    <title>Realisasi Kas (BKK/BKM) – <?= htmlspecialchars($budget['budget_name']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-block { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; background: #fff; }
        .category-head  { background: #f8fafc; padding: 12px 18px; font-weight: 700; font-size: 14px;
                          border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        
        /* ── 4 Summary Cards Bersih & Kontras Tinggi ── */
        .sc-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-left: 4px solid #cbd5e1 !important;
            border-radius: 10px;
            padding: 14px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            height: 100%;
            transition: transform .2s, box-shadow .2s;
        }
        .sc-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .sc-card.card-alokasi   { border-left-color: #2563eb !important; background: #f8faff !important; }
        .sc-card.card-realisasi { border-left-color: #059669 !important; background: #f6fdf9 !important; }
        .sc-card.card-menunggu  { border-left-color: #d97706 !important; background: #fffdf5 !important; }
        .sc-card.card-sisa      { border-left-color: #0284c7 !important; background: #f6fbfe !important; }

        .sc-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b !important;
            margin-bottom: 4px;
        }
        .sc-value {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 4px;
        }
        .card-alokasi   .sc-value { color: #1e3a8a !important; }
        .card-realisasi .sc-value { color: #047857 !important; }
        .card-menunggu  .sc-value { color: #b45309 !important; }
        .card-sisa      .sc-value { color: #0369a1 !important; }

        .sc-sub {
            font-size: 11.5px;
            color: #64748b;
        }
        .sc-icon {
            opacity: 0.8;
            padding-left: 8px;
        }

        .info-box       { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 22px; }

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

        .bukti-link     { display: inline-flex; align-items: center; gap: 4px; background: #e0f2fe; border-radius: 5px; padding: 2px 8px; font-size: 11px; color: #0369a1; text-decoration: none; margin: 2px; }
        .bukti-link:hover { background: #bae6fd; text-decoration: none; }
        .prev-item      { background: #e0f2fe; border-radius: 5px; padding: 4px 10px; font-size: 11.5px; color: #0369a1; display: inline-block; margin: 3px; }

        /* ── Modal BKK/BKM Styling (Persis sesuai gambar) ── */
        .modal-kas-title { font-weight: 700; font-size: 18px; color: #0f172a; }
        .modal-kas-sub   { font-size: 12px; color: #64748b; font-weight: 600; }
        
        .kas-tab-group   { display: flex; gap: 12px; margin-bottom: 18px; }
        .kas-tab-btn     { flex: 1; padding: 12px 16px; border-radius: 8px; font-weight: 700; font-size: 13.5px;
                           border: 2px solid #cbd5e1; background: #fff; color: #334155; text-align: center; cursor: pointer; transition: all .2s; }
        .kas-tab-btn.active-bkk { background: #1e3a8a; border-color: #1e3a8a; color: #fff; box-shadow: 0 2px 6px rgba(30,58,138,0.25); }
        .kas-tab-btn.active-bkm { background: #047857; border-color: #047857; color: #fff; box-shadow: 0 2px 6px rgba(4,120,87,0.25); }

        .drop-zone {
            border: 2px dashed #94a3b8; border-radius: 8px; padding: 22px 16px; text-align: center;
            background: #f8fafc; cursor: pointer; transition: background .2s, border-color .2s;
        }
        .drop-zone:hover { background: #f1f5f9; border-color: #3b82f6; }
        
        .rincian-table th { background: #1e3a8a; color: #fff; padding: 9px 12px; font-size: 12px; font-weight: 600; border: none; }
        .rincian-table td { padding: 8px 10px; vertical-align: middle; background: #fff; }
        .rincian-table.table-bkm th { background: #047857; }

        /* ── Select2 Custom Styling for Table Cell ── */
        .select2-container { width: 100% !important; min-width: 0 !important; }
        .select2-container--default .select2-selection--single {
            height: 31px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #fff;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b;
            font-size: 12px;
            line-height: 29px;
            padding-left: 8px;
            padding-right: 24px;
            width: 100%;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
            right: 6px;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 18px;
            color: #94a3b8;
            font-size: 13px;
        }
        .select2-dropdown {
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            font-size: 12px;
            z-index: 1065 !important; /* higher than modal (1050) */
        }
        .select2-container--default .select2-search--dropdown {
            padding: 6px 8px;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 8px;
            font-size: 12px;
            outline: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #1e3a8a;
            color: #fff;
        }
        .select2-container--default .select2-results__group {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #f8fafc;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
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
                            <i class="fa-solid fa-receipt text-primary mr-1"></i> Transaksi Kas & Realisasi Anggaran
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Realisasi Bulanan</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($budget['budget_name']) ?></li>
                        </ul>
                    </div>
                    <div class="col-auto d-flex" style="gap:8px;">
                        <button type="button" class="btn btn-primary font-weight-bold" onclick="openKasModal('BKK', 0)">
                            <i class="fas fa-plus-circle mr-1"></i> Input Transaksi Kas (BKK / BKM)
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
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
                        <span class="badge p-2 px-3 font-weight-bold" style="font-size:12px; <?= $budget['status'] === 'Active' ? 'background:#dcfce7; color:#15803d; border:1px solid #86efac;' : 'background:#fef3c7; color:#b45309; border:1px solid #fde68a;' ?>">
                            <i class="fas fa-<?= $budget['status'] === 'Active' ? 'check-circle' : 'clock' ?> mr-1"></i> Status Budget: <?= htmlspecialchars($budget['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- 4 Summary Cards Bersih & Kontras Tinggi -->
                <div class="row mt-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-alokasi">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Total Alokasi Anggaran</div>
                                    <div class="sc-value">Rp <?= number_format($total_alokasi_budget, 0, ',', '.') ?></div>
                                    <div class="sc-sub"><i class="fa-solid fa-layer-group mr-1"></i><?= count($items) ?> Item Rincian</div>
                                </div>
                                <div class="sc-icon" style="color:#2563eb;">
                                    <i class="fa-solid fa-wallet fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-realisasi">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Realisasi (BKK)</div>
                                    <div class="sc-value">Rp <?= number_format($total_approved_budget, 0, ',', '.') ?></div>
                                    <div class="sc-sub font-weight-bold" style="color:#059669;"><i class="fa-solid fa-chart-pie mr-1"></i><?= $budget_progress_pct ?>% (<?= $items_realized_count ?> item)</div>
                                </div>
                                <div class="sc-icon" style="color:#059669;">
                                    <i class="fa-solid fa-receipt fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-menunggu">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Menunggu Asmen</div>
                                    <div class="sc-value">Rp <?= number_format($total_pending_budget, 0, ',', '.') ?></div>
                                    <div class="sc-sub" style="color:#b45309;"><i class="fa-solid fa-clock mr-1"></i>Verifikasi nota & kas</div>
                                </div>
                                <div class="sc-icon" style="color:#d97706;">
                                    <i class="fa-solid fa-hourglass-half fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-sisa">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Sisa Alokasi</div>
                                    <div class="sc-value">Rp <?= number_format($total_sisa_budget, 0, ',', '.') ?></div>
                                    <div class="sc-sub" style="color:#0284c7;"><i class="fa-solid fa-circle-check mr-1"></i>Tersedia realisasi</div>
                                </div>
                                <div class="sc-icon" style="color:#0284c7;">
                                    <i class="fa-solid fa-piggy-bank fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    <div class="category-head" style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                        <span style="color:#1e293b; font-weight:700;"><i class="fas fa-tags mr-2" style="color:#2563eb;"></i><?= htmlspecialchars($cat_name) ?></span>
                        <span class="badge px-3 py-1 font-weight-bold" style="font-size:12px; background:#1e3a8a !important; color:#ffffff !important; border-radius:6px;">
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
                            if ($r['status'] === 'Disetujui' && $r['jenis_transaksi'] === 'BKK') {
                                $it_approved += (float)$r['nominal_realisasi'];
                            } elseif ($r['status'] === 'Menunggu Approval' && $r['jenis_transaksi'] === 'BKK') {
                                $it_pending += (float)$r['nominal_realisasi'];
                            }
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
                                    <span>Alokasi: <strong style="color:#1e3a8a;">Rp <?= number_format($it_alokasi, 0, ',', '.') ?></strong></span>
                                    <span>Terealisasi: <strong style="color:#047857;">Rp <?= number_format($it_approved, 0, ',', '.') ?></strong></span>
                                    <?php if ($it_pending > 0): ?>
                                    <span>Menunggu: <strong style="color:#b45309;">Rp <?= number_format($it_pending, 0, ',', '.') ?></strong></span>
                                    <?php endif; ?>
                                    <span>Sisa: <strong style="color:<?= $it_sisa > 0 ? '#0284c7' : '#64748b' ?>;">Rp <?= number_format($it_sisa, 0, ',', '.') ?></strong></span>
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
                                            onclick="openKasModal('BKK', <?= $it_id ?>)">
                                        <i class="fas fa-plus mr-1"></i> Input Transaksi
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Daftar Riwayat Realisasi untuk Item ini -->
                        <div class="p-3 pt-2">
                            <?php if (empty($item_reals)): ?>
                                <div class="text-muted small py-1 font-italic" style="font-size:12px;">
                                    <i class="fas fa-info-circle mr-1"></i> Belum ada transaksi kas untuk item ini.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="real-table">
                                        <thead>
                                            <tr>
                                                <th width="4%">No</th>
                                                <th width="10%">Jenis</th>
                                                <th width="13%">No. Transaksi</th>
                                                <th width="10%">Tanggal</th>
                                                <th width="18%">Dibayar Kepada / Vendor</th>
                                                <th width="14%" class="text-right">Jumlah (Rp)</th>
                                                <th width="13%">Bukti Nota</th>
                                                <th width="12%">Status</th>
                                                <th width="6%" class="text-center">Aksi</th>
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
                                                $is_bkm   = ($rl['jenis_transaksi'] === 'BKM');
                                                $nom_disp = $is_bkm ? (float)$rl['uang_kembali'] : (float)$rl['nominal_realisasi'];
                                            ?>
                                            <tr>
                                                <td><?= $r_no++ ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $is_bkm ? 'success' : 'primary' ?> px-2 py-1 font-weight-bold" style="font-size:10.5px;">
                                                        <?= $rl['jenis_transaksi'] ?>
                                                    </span>
                                                </td>
                                                <td><span class="font-weight-bold text-dark"><?= htmlspecialchars($rl['no_realisasi'] ?: '-') ?></span></td>
                                                <td><?= $rl['tanggal_realisasi'] ? date('d/m/Y', strtotime($rl['tanggal_realisasi'])) : '-' ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($rl['dibayar_kepada'] ?: ($rl['keperluan'] ?: '—')) ?></strong>
                                                    <?php if ($rl['catatan'] && $rl['catatan'] !== $rl['dibayar_kepada']): ?>
                                                    <div class="text-muted small"><?= htmlspecialchars($rl['catatan']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($rl['catatan_approval']): ?>
                                                    <div class="text-danger small mt-1 font-italic">
                                                        <i class="fas fa-comment mr-1"></i> Catatan Asmen: <?= htmlspecialchars($rl['catatan_approval']) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right font-weight-bold <?= $is_bkm ? 'text-info' : 'text-success' ?>">
                                                    <?= $is_bkm ? '+ ' : '' ?>Rp <?= number_format($nom_disp, 0, ',', '.') ?>
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
                                                                title="Edit Transaksi"
                                                                onclick="editKasTransaction(<?= htmlspecialchars(json_encode($rl)) ?>)">
                                                            <i class="fas fa-edit fa-xs"></i>
                                                        </button>
                                                        <?php if ($st === 'Draft'): ?>
                                                        <form action="save_realisasi.php" method="POST" style="display:inline;" onsubmit="return confirm('Hapus draft transaksi ini?');">
                                                            <input type="hidden" name="id_budget" value="<?= $id_budget ?>">
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

<!-- ============================================================== -->
<!-- MODAL INPUT TRANSAKSI KAS (BKK / BKM) — PERSIS SEPERTI GAMBAR -->
<!-- ============================================================== -->
<div class="modal fade" id="modalInputKas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 860px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            
            <!-- Modal Header -->
            <div class="modal-header px-4 py-3" style="background:#fff; border-bottom: 1px solid #e2e8f0;">
                <div>
                    <h5 class="modal-title modal-kas-title" id="modalKasTitle">Input Transaksi Kas</h5>
                </div>
                <div class="text-right">
                    <div class="modal-kas-sub">
                        PT DUA FARMA MAHAKARSA — Cabang <?= htmlspecialchars($budget['nama_cabang'] ?? 'Jakarta') ?>
                    </div>
                    <small class="text-muted">Tahun anggaran <?= $budget['period_year'] ?></small>
                </div>
                <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close" style="outline:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Form -->
            <form action="save_realisasi.php" method="POST" enctype="multipart/form-data" id="formTransaksiKas">
                <input type="hidden" name="id_budget" value="<?= $id_budget ?>">
                <input type="hidden" name="id_real_detail" id="kasIdRealDetail" value="0">
                <input type="hidden" name="jenis_transaksi" id="kasJenisTransaksi" value="BKK">

                <div class="modal-body px-4 py-3" style="background: #ffffff;">

                    <!-- 2 Big Tabs BKK / BKM -->
                    <div class="kas-tab-group">
                        <button type="button" class="kas-tab-btn active-bkk" id="btnTabBKK" onclick="switchKasTab('BKK')">
                            BKK — Bukti Kas Keluar (Pengeluaran)
                        </button>
                        <button type="button" class="kas-tab-btn" id="btnTabBKM" onclick="switchKasTab('BKM')">
                            BKM — Bukti Kas Masuk (Penerimaan)
                        </button>
                    </div>

                    <!-- Row 1: Tanggal, Dibayar Kepada, Keperluan -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="small font-weight-bold text-dark mb-1">Tanggal</label>
                            <input type="date" name="tanggal_realisasi" id="kasTanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold text-dark mb-1" id="lblDibayar">Dibayar Kepada</label>
                            <input type="text" name="dibayar_kepada" id="kasDibayar" class="form-control" placeholder="Nama toko / vendor / karyawan" required>
                        </div>
                        <div class="col-md-5">
                            <label class="small font-weight-bold text-dark mb-1">Keperluan</label>
                            <input type="text" name="keperluan" id="kasKeperluan" class="form-control" placeholder="Contoh: Pembelian ATK kantor" required>
                        </div>
                    </div>

                    <!-- Section 1: Upload Nota / Kwitansi -->
                    <div class="mb-4">
                        <label class="font-weight-bold text-dark mb-2" style="font-size: 13.5px;">
                            1. Upload Nota / Kwitansi
                        </label>
                        <div class="drop-zone" onclick="document.getElementById('kasFileInput').click()">
                            <i class="fas fa-file-invoice text-primary fa-2x mb-2 d-block"></i>
                            <p class="mb-1 text-muted" style="font-size: 13px;">
                                Klik atau tarik file ke sini (foto JPG/PNG atau PDF, boleh lebih dari satu) — nota digabung ke dalam bukti transaksi
                            </p>
                            <input type="file" id="kasFileInput" name="bukti[]" accept=".jpg,.jpeg,.png,.pdf" multiple style="display:none;" onchange="handleKasFiles(this)">
                            <div id="kasFilePreview" class="d-flex flex-wrap justify-content-center mt-2"></div>
                        </div>
                    </div>

                    <!-- Section 2: Rincian Biaya / Akun -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold text-dark mb-0" style="font-size: 13.5px;">
                                2. Rincian Biaya / Akun
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addKasRow()">
                                <i class="fas fa-plus mr-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div class="table-responsive border rounded" style="overflow-x: auto;">
                            <table class="table table-sm mb-0 rincian-table" id="tableKasRincian">
                                <thead>
                                    <tr>
                                        <th width="45%">Akun (ketik kode / nama untuk mencari)</th>
                                        <th width="32%">Keterangan</th>
                                        <th width="18%" class="text-right">Jumlah (Rp)</th>
                                        <th width="5%" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyKasRincian">
                                    <!-- Dynamic Rows will be inserted here -->
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light font-weight-bold">
                                        <td colspan="2" class="text-right">Total Transaksi:</td>
                                        <td class="text-right text-success" id="kasTotalDisplay" style="font-size: 14px;">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer d-flex justify-content-between align-items-center px-4 py-3" style="background:#f8fafc; border-top: 1px solid #e2e8f0;">
                    <div class="small text-muted font-weight-bold" id="footerAkunKas">
                        Akun kas: 1101-21-01 Kas Operasional <?= htmlspecialchars($budget['nama_cabang'] ?? 'Jakarta') ?>
                    </div>
                    <div class="d-flex" style="gap: 8px;">
                        <button type="button" class="btn btn-light border px-3" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="action" value="draft" class="btn btn-outline-primary px-3">
                            <i class="fas fa-save mr-1"></i> Simpan Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-primary px-4 font-weight-bold" id="btnSimpanKas" style="background:#1e3a8a; border-color:#1e3a8a;">
                            Simpan Transaksi
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>

<!-- Options items untuk dropdown rincian akun -->
<script>
const budgetItems = <?= json_encode(array_map(function($it) use ($real_by_detail) {
    $it_id = (int)$it['id_detail'];
    $item_reals = $real_by_detail[$it_id] ?? [];
    $it_app = 0;
    foreach ($item_reals as $r) {
        if ($r['status'] === 'Disetujui' && $r['jenis_transaksi'] === 'BKK') {
            $it_app += (float)$r['nominal_realisasi'];
        }
    }
    $sisa = max(0, (float)$it['total'] - $it_app);
    return [
        'id' => $it['id_detail'],
        'rincian' => $it['rincian'],
        'kategori' => $it['category_name'],
        'kode' => $it['category_code'],
        'total' => (float)$it['total'],
        'sisa' => $sisa
    ];
}, $items)) ?>;

let currentJenis = 'BKK';

function switchKasTab(jenis) {
    currentJenis = jenis;
    document.getElementById('kasJenisTransaksi').value = jenis;
    const btnBKK = document.getElementById('btnTabBKK');
    const btnBKM = document.getElementById('btnTabBKM');
    const tbl = document.getElementById('tableKasRincian');
    const lblDibayar = document.getElementById('lblDibayar');
    const btnSimpan = document.getElementById('btnSimpanKas');

    if (jenis === 'BKK') {
        btnBKK.className = 'kas-tab-btn active-bkk';
        btnBKM.className = 'kas-tab-btn';
        tbl.classList.remove('table-bkm');
        lblDibayar.textContent = 'Dibayar Kepada';
        document.getElementById('kasDibayar').placeholder = 'Nama toko / vendor / karyawan';
        document.getElementById('kasKeperluan').placeholder = 'Contoh: Pembelian ATK kantor';
        btnSimpan.style.background = '#1e3a8a';
        btnSimpan.style.borderColor = '#1e3a8a';
    } else {
        btnBKM.className = 'kas-tab-btn active-bkm';
        btnBKK.className = 'kas-tab-btn';
        tbl.classList.add('table-bkm');
        lblDibayar.textContent = 'Diterima Dari';
        document.getElementById('kasDibayar').placeholder = 'Nama pihak penyetor / toko pengembalian';
        document.getElementById('kasKeperluan').placeholder = 'Contoh: Pengembalian sisa belanja ATK kantor';
        btnSimpan.style.background = '#047857';
        btnSimpan.style.borderColor = '#047857';
    }
}

function openKasModal(jenis, presetItemId) {
    document.getElementById('formTransaksiKas').reset();
    document.getElementById('kasIdRealDetail').value = '0';
    document.getElementById('kasFilePreview').innerHTML = '';

    // Destroy instance Select2 lama jika ada
    $('#tbodyKasRincian .select-akun').each(function() {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
    });
    document.getElementById('tbodyKasRincian').innerHTML = '';
    document.getElementById('kasTanggal').value = '<?= date('Y-m-d') ?>';

    switchKasTab(jenis || 'BKK');
    addKasRow(presetItemId || 0);

    $('#modalInputKas').modal('show');
}

function addKasRow(selectedId, keteranganVal, jumlahVal) {
    const tbody = document.getElementById('tbodyKasRincian');
    const tr = document.createElement('tr');

    let selectHtml = '<select name="item_detail_id[]" class="form-control form-control-sm select-akun" required style="width:100%;">';
    selectHtml += '<option value="">-- Ketik kode / nama untuk mencari --</option>';

    // Group by category
    const categories = {};
    budgetItems.forEach(it => {
        const cat = it.kategori || 'Umum';
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(it);
    });

    for (const [catName, catItems] of Object.entries(categories)) {
        selectHtml += `<optgroup label="${catName}">`;
        catItems.forEach(it => {
            const sel = (selectedId && it.id == selectedId) ? 'selected' : '';
            const prefix = it.kode ? `[${it.kode}] ` : '';
            selectHtml += `<option value="${it.id}" ${sel}>${prefix}${it.rincian} (Sisa: Rp ${formatRupiah(it.sisa)})</option>`;
        });
        selectHtml += `</optgroup>`;
    }
    selectHtml += '</select>';

    const ketText = keteranganVal || '';
    const jmlText = jumlahVal ? formatRupiah(jumlahVal) : '';

    tr.innerHTML = `
        <td style="min-width: 260px;">${selectHtml}</td>
        <td><input type="text" name="item_keterangan[]" class="form-control form-control-sm" placeholder="Keterangan (opsional)" value="${ketText}"></td>
        <td><input type="text" name="item_jumlah[]" class="form-control form-control-sm rupiah-kas text-right font-weight-bold" placeholder="0" value="${jmlText}" oninput="calcKasTotal()" required></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeKasRow(this)" title="Hapus Baris">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    // Inisialisasi Select2 pencarian pada elemen select yang baru dibuat
    const $sel = $(tr).find('.select-akun');
    if ($.fn.select2) {
        $sel.select2({
            dropdownParent: $('#modalInputKas'),
            width: '100%',
            placeholder: 'mis. 5313 / BBM / Listrik',
            allowClear: true
        });
    }

    calcKasTotal();
}

function removeKasRow(btn) {
    const tbody = document.getElementById('tbodyKasRincian');
    if (tbody.children.length <= 1) {
        alert('Minimal harus ada 1 baris rincian akun biaya!');
        return;
    }
    const tr = btn.closest('tr');
    const $sel = $(tr).find('.select-akun');
    if ($sel.length && $sel.data('select2')) {
        $sel.select2('destroy');
    }
    tr.remove();
    calcKasTotal();
}

function calcKasTotal() {
    let total = 0;
    document.querySelectorAll('.rupiah-kas').forEach(inp => {
        let val = inp.value.replace(/[^0-9]/g, '');
        inp.value = val ? formatRupiah(parseInt(val)) : '';
        total += parseInt(val || 0);
    });
    document.getElementById('kasTotalDisplay').textContent = 'Rp ' + formatRupiah(total);
}

function handleKasFiles(input) {
    const prev = document.getElementById('kasFilePreview');
    prev.innerHTML = '';
    if (!input.files || input.files.length === 0) return;

    if (input.files.length > 10) {
        alert('Maksimal 10 file!');
        input.value = '';
        return;
    }

    Array.from(input.files).forEach(file => {
        const ext = file.name.split('.').pop().toLowerCase();
        const ic = ['jpg','jpeg','png'].includes(ext) ? 'fa-image' : 'fa-file-pdf';
        const span = document.createElement('span');
        span.className = 'prev-item';
        span.innerHTML = `<i class="fas ${ic}"></i> ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
        prev.appendChild(span);
    });
}

function editKasTransaction(data) {
    document.getElementById('formTransaksiKas').reset();
    document.getElementById('kasIdRealDetail').value = data.id_real_detail;
    document.getElementById('kasTanggal').value = data.tanggal_realisasi || '<?= date('Y-m-d') ?>';
    document.getElementById('kasDibayar').value = data.dibayar_kepada || '';
    document.getElementById('kasKeperluan').value = data.keperluan || data.catatan || '';
    
    $('#tbodyKasRincian .select-akun').each(function() {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
    });
    document.getElementById('tbodyKasRincian').innerHTML = '';

    const isBkm = (data.jenis_transaksi === 'BKM');
    switchKasTab(isBkm ? 'BKM' : 'BKK');

    const nom = isBkm ? parseInt(data.uang_kembali || 0) : parseInt(data.nominal_realisasi || 0);
    addKasRow(data.id_detail_budget, data.catatan, nom);

    // Bukti lama
    const prev = document.getElementById('kasFilePreview');
    prev.innerHTML = '';
    if (data.bukti_files) {
        data.bukti_files.split(',').forEach(bf => {
            bf = bf.trim(); if (!bf) return;
            const a = document.createElement('a');
            a.href = '../uploads/realisasi/' + bf;
            a.target = '_blank';
            a.className = 'bukti-link mr-1';
            a.innerHTML = `<i class="fas fa-paperclip"></i> ${bf}`;
            prev.appendChild(a);
        });
    }

    $('#modalInputKas').modal('show');
}

function formatRupiah(num) {
    return (num || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Auto-focus kotak pencarian saat Select2 dibuka
$(document).on('select2:open', () => {
    setTimeout(() => {
        const searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    }, 50);
});
</script>
</body>
</html>

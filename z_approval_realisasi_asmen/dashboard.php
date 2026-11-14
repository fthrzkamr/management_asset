<?php
/**
 * Approval Realisasi – Dashboard Asmen
 * ─────────────────────────────────────
 * Tampilan:
 *  1. LIST semua anggaran bulanan (ppx_monthly_budget) dari semua user
 *  2. Klik baris -> expand untuk melihat rincian item & realisasi per detail item
 *  3. Asmen dapat menyetujui / menolak realisasi per detail item yang diajukan
 */
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_realisasi_asmen', 'approve');

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// Pastikan kolom jenis_transaksi tersedia di ppx_realisasi_detail2 (auto-migrate untuk server)
$chk_col = mysqli_query($conn, "SHOW COLUMNS FROM ppx_realisasi_detail2 LIKE 'jenis_transaksi'");
if ($chk_col && mysqli_num_rows($chk_col) === 0) {
    @mysqli_query($conn, "ALTER TABLE ppx_realisasi_detail2
        ADD COLUMN jenis_transaksi ENUM('BKK','BKM') NOT NULL DEFAULT 'BKK' AFTER no_realisasi,
        ADD COLUMN dibayar_kepada VARCHAR(255) DEFAULT NULL AFTER tanggal_realisasi,
        ADD COLUMN keperluan VARCHAR(255) DEFAULT NULL AFTER dibayar_kepada");
}

/* ───────── 1. Ambil semua anggaran bulanan aktif + info realisasi ───────── */
$q_budgets = mysqli_query($conn, "
    SELECT
        mb.id_budget,
        mb.budget_name,
        mb.period_month,
        mb.period_year,
        mb.total_allocation,
        mb.created_at   AS budget_created,
        u.id_user,
        u.nama_user,
        mc.nama_cabang,

        COALESCE(rd_stats.total_approved, 0) AS total_real_approved,
        COALESCE(rd_stats.total_pending, 0)  AS total_real_pending,
        COALESCE(rd_stats.count_pending, 0)  AS count_pending,
        COALESCE(rd_stats.count_approved, 0) AS count_approved,
        COALESCE(rd_stats.count_rejected, 0) AS count_rejected,
        COALESCE(rd_stats.total_entries, 0)  AS total_entries,
        (SELECT COUNT(*) FROM ppx_monthly_budget_item_detail WHERE id_budget = mb.id_budget) AS total_items

    FROM ppx_monthly_budget mb
    JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN (
        SELECT 
            id_budget,
            SUM(CASE WHEN status = 'Disetujui' AND jenis_transaksi = 'BKM' THEN -nominal_realisasi WHEN status = 'Disetujui' THEN nominal_realisasi ELSE 0 END) AS total_approved,
            SUM(CASE WHEN status = 'Menunggu Approval' THEN nominal_realisasi ELSE 0 END) AS total_pending,
            COUNT(CASE WHEN status = 'Menunggu Approval' THEN 1 END) AS count_pending,
            COUNT(CASE WHEN status = 'Disetujui' THEN 1 END) AS count_approved,
            COUNT(CASE WHEN status = 'Ditolak' THEN 1 END) AS count_rejected,
            COUNT(*) AS total_entries
        FROM ppx_realisasi_detail2
        GROUP BY id_budget
    ) rd_stats ON rd_stats.id_budget = mb.id_budget
    WHERE mb.status = 'Active' OR COALESCE(rd_stats.total_entries, 0) > 0
    ORDER BY rd_stats.count_pending DESC, mb.period_year DESC, mb.period_month DESC, u.nama_user ASC
");

$db_ok   = ($q_budgets !== false);
$budgets = [];
if ($db_ok) while ($r = mysqli_fetch_assoc($q_budgets)) $budgets[] = $r;

/* ───────── 2. Ambil semua rincian item anggaran + semua realisasi detail ──── */
$id_budget_all = array_unique(array_column($budgets, 'id_budget'));
$items_map     = [];   // [ id_budget => [ items ] ]
$reals_map     = [];   // [ id_detail_budget => [ list of realizations ] ]

if (!empty($id_budget_all)) {
    $bids = implode(',', array_map('intval', $id_budget_all));

    // Ambil item anggaran
    $q_items = mysqli_query($conn, "
        SELECT
            bd.id AS id_detail, bd.id_budget,
            bd.rincian, bd.qty, bd.satuan, bd.harga_satuan, bd.total AS total_ajuan,
            bc.category_name, bc.category_code
        FROM ppx_monthly_budget_item_detail bd
        LEFT JOIN ppx_budget_category bc ON bd.id_category = bc.id_category
        WHERE bd.id_budget IN ($bids)
        ORDER BY bc.category_name ASC, bd.id ASC
    ");
    if ($q_items) {
        while ($it = mysqli_fetch_assoc($q_items)) {
            $items_map[$it['id_budget']][] = $it;
        }
    }

    // Ambil semua entri realisasi untuk budget-budget ini
    $q_reals = mysqli_query($conn, "
        SELECT rd.*, u.nama_user as approver_name
        FROM ppx_realisasi_detail2 rd
        LEFT JOIN user_dfm u ON rd.approved_by = u.id_user
        WHERE rd.id_budget IN ($bids)
        ORDER BY rd.tanggal_realisasi DESC, rd.id_real_detail DESC
    ");
    if ($q_reals) {
        while ($rl = mysqli_fetch_assoc($q_reals)) {
            $reals_map[$rl['id_detail_budget']][] = $rl;
        }
    }
}

/* ───────── 3. Statistik ringkasan ────────────────────────────────────────── */
$cnt_all      = count($budgets);
$cnt_pending  = count(array_filter($budgets, fn($r) => (int)$r['count_pending'] > 0));
$cnt_approved = count(array_filter($budgets, fn($r) => (int)$r['count_approved'] > 0 && (int)$r['count_pending'] === 0));
$cnt_no_real  = count(array_filter($budgets, fn($r) => (int)$r['total_entries'] === 0));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Realisasi Anggaran (Asmen)</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { border-radius:10px; border:none; padding:14px 16px; box-shadow:0 2px 8px rgba(0,0,0,.07); text-align:center; }
        .pulse { animation:pw 2s ease-in-out infinite; }
        @keyframes pw { 0%,100%{box-shadow:0 2px 8px rgba(0,0,0,.07);} 50%{box-shadow:0 0 0 5px rgba(245,158,11,.22);} }

        .f-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
        .f-tab  { padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600;
                  border:2px solid #e5e7eb; background:#fff; cursor:pointer; transition:.15s; }
        .f-tab.active { border-color:#3b82f6; background:#eff6ff; color:#1d4ed8; }

        .tbl-wrap    { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
        .tbl-head    { display:flex; padding:10px 16px; background:#f1f5f9;
                       border-bottom:1px solid #e5e7eb; font-size:11.5px; font-weight:700; color:#64748b;
                       text-transform:uppercase; letter-spacing:.4px; }
        .tbl-row     { border-bottom:1px solid #f1f5f9; }
        .tbl-row:last-child { border-bottom:none; }

        .row-main {
            display:flex; align-items:center;
            padding:12px 16px; cursor:pointer;
            background:#fff; transition:.12s;
        }
        .row-main:hover        { background:#f8fafc; }
        .tbl-row.open .row-main{ background:#eff6ff; border-bottom:1px solid #dbeafe; }

        .col-user   { flex:0 0 200px; min-width:140px; }
        .col-period { flex:0 0 120px; }
        .col-name   { flex:1; min-width:140px; }
        .col-alloc  { flex:0 0 140px; text-align:right; }
        .col-status { flex:0 0 180px; padding-left:10px; }
        .col-toggle { flex:0 0 32px; text-align:center; color:#94a3b8; }

        .av { width:30px; height:30px; border-radius:50%; flex-shrink:0;
              background:linear-gradient(135deg,#3b82f6,#1d4ed8);
              color:#fff; display:inline-flex; align-items:center; justify-content:center;
              font-size:11px; font-weight:700; margin-right:8px; }

        .pill { display:inline-flex; align-items:center; gap:5px;
                border-radius:20px; padding:3px 12px; font-size:11px; font-weight:600; }
        .s-wait { background:#fef3c7; color:#92400e; }
        .s-ok   { background:#d1fae5; color:#065f46; }
        .s-rej  { background:#fee2e2; color:#991b1b; }
        .s-none { background:#f1f5f9; color:#64748b; }

        .row-expand { display:none; background:#fafafa; padding:18px 20px; border-bottom:1px solid #e2e8f0; }
        .tbl-row.open .row-expand { display:block; }

        .item-box { background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px; overflow:hidden; }
        .item-box-head { padding:10px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .sub-table { width:100%; font-size:12px; border-collapse:separate; border-spacing:0; }
        .sub-table th { background:#f1f5f9; color:#64748b; padding:7px 10px; font-size:11px; font-weight:600; }
        .sub-table td { padding:8px 10px; border-bottom:1px solid #f8fafc; vertical-align:middle; }

        .bk-img { width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #cbd5e1; cursor:pointer; }
        .bk-pdf { display:inline-flex; align-items:center; gap:3px; background:#fee2e2; border-radius:4px;
                  padding:3px 8px; font-size:11px; color:#b91c1c; text-decoration:none; font-weight:600; }

        .btn-act-ok { background:#10b981; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:11.5px; font-weight:600; cursor:pointer; }
        .btn-act-ok:hover { background:#059669; }
        .btn-act-ng { background:#ef4444; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:11.5px; font-weight:600; cursor:pointer; }
        .btn-act-ng:hover { background:#dc2626; }
    </style>
</head>
<body style="background:#f4f6f9;">
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="fa-solid fa-stamp text-warning mr-1"></i> Approval Realisasi Anggaran
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Approval Realisasi</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php foreach (['success','error'] as $t): if (!empty($_SESSION[$t])): ?>
            <div class="alert alert-<?= $t==='success'?'success':'danger' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION[$t]) ?>
                <button class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION[$t]); endif; endforeach; ?>

            <?php if (!$db_ok): ?>
            <div class="alert alert-warning">Tabel anggaran belum tersedia atau query bermasalah.</div>
            <?php elseif (empty($budgets)): ?>
            <div class="card"><div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>Belum ada pengajuan anggaran bulanan aktif.</p>
            </div></div>
            <?php else: ?>

            <!-- ── Stats Cards ── -->
            <div class="row mb-3">
                <div class="col-6 col-md-3 mb-2">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-primary mb-1"><?= $cnt_all ?></div>
                        <div class="small text-muted">Total Anggaran</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="stat-card card <?= $cnt_pending ? 'pulse' : '' ?>">
                        <div class="h3 font-weight-bold text-warning mb-1"><?= $cnt_pending ?></div>
                        <div class="small text-muted"><i class="fas fa-clock mr-1"></i>Ada Menunggu Approve</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-success mb-1"><?= $cnt_approved ?></div>
                        <div class="small text-muted"><i class="fas fa-check-circle mr-1"></i>Terealisasi & Disetujui</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-secondary mb-1"><?= $cnt_no_real ?></div>
                        <div class="small text-muted"><i class="fas fa-minus-circle mr-1"></i>Belum Ada Realisasi</div>
                    </div>
                </div>
            </div>

            <!-- ── Filter Tabs ── -->
            <div class="f-tabs">
                <div class="f-tab active" onclick="doFilter('all',this)">
                    Semua <span class="badge badge-secondary ml-1"><?= $cnt_all ?></span>
                </div>
                <div class="f-tab" onclick="doFilter('wait',this)">
                    <i class="fas fa-clock text-warning"></i> Menunggu
                    <span class="badge badge-warning ml-1"><?= $cnt_pending ?></span>
                </div>
                <div class="f-tab" onclick="doFilter('ok',this)">
                    <i class="fas fa-check-circle text-success"></i> Selesai
                </div>
                <div class="f-tab" onclick="doFilter('none',this)">
                    <i class="fas fa-minus-circle text-secondary"></i> Belum Ada Realisasi
                </div>
            </div>

            <!-- ── List Anggaran ── -->
            <div class="tbl-wrap">
                <div class="tbl-head">
                    <div class="col-user">Pengaju & Cabang</div>
                    <div class="col-period">Periode</div>
                    <div class="col-name">Nama Anggaran</div>
                    <div class="col-alloc">Alokasi & Realisasi</div>
                    <div class="col-status">Status Realisasi</div>
                    <div class="col-toggle"></div>
                </div>

                <?php foreach ($budgets as $b):
                    $mid       = (int)$b['id_budget'];
                    $c_pend    = (int)$b['count_pending'];
                    $c_app     = (int)$b['count_approved'];
                    $tot_alloc = (float)$b['total_allocation'];
                    $tot_real  = (float)$b['total_real_approved'];
                    $tot_pend  = (float)$b['total_real_pending'];

                    if ($c_pend > 0) {
                        $fattr   = 'wait';
                        $pillCls = 's-wait';
                        $pillTxt = "<i class='fas fa-clock'></i> $c_pend Menunggu Approval";
                    } elseif ($tot_real > 0) {
                        $fattr   = 'ok';
                        $pillCls = 's-ok';
                        $pillTxt = "<i class='fas fa-check-circle'></i> $c_app Disetujui";
                    } else {
                        $fattr   = 'none';
                        $pillCls = 's-none';
                        $pillTxt = '<i class="fas fa-minus-circle"></i> Belum Direalisasi';
                    }

                    $initial = strtoupper(substr($b['nama_user'] ?? 'U', 0, 1));
                    $items   = $items_map[$mid] ?? [];
                    $row_uid = 'row-' . $mid;
                ?>
                <div class="tbl-row" data-filter="<?= $fattr ?>" id="<?= $row_uid ?>">
                    <div class="row-main" onclick="toggleRow('<?= $row_uid ?>')">
                        <div class="col-user d-flex align-items-center">
                            <div class="av"><?= $initial ?></div>
                            <div>
                                <div style="font-size:13px; font-weight:600; color:#1e293b;">
                                    <?= htmlspecialchars($b['nama_user'] ?? '-') ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($b['nama_cabang'] ?? '-') ?></small>
                            </div>
                        </div>
                        <div class="col-period" style="font-size:13px;">
                            <?= $bulan_names[$b['period_month']] ?> <?= $b['period_year'] ?>
                        </div>
                        <div class="col-name">
                            <div style="font-size:13px; color:#1e293b; font-weight:600;">
                                <?= htmlspecialchars($b['budget_name']) ?>
                            </div>
                            <small class="text-muted"><?= $b['total_items'] ?> item rincian</small>
                        </div>
                        <div class="col-alloc">
                            <div class="text-primary font-weight-bold" style="font-size:13px;">
                                Rp <?= number_format($tot_alloc, 0, ',', '.') ?>
                            </div>
                            <?php if ($tot_real > 0): ?>
                            <small class="text-success font-weight-bold">
                                Rp <?= number_format($tot_real, 0, ',', '.') ?> disetujui
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-status">
                            <span class="pill <?= $pillCls ?>"><?= $pillTxt ?></span>
                        </div>
                        <div class="col-toggle">
                            <i class="fas fa-chevron-down" id="ico-<?= $row_uid ?>"></i>
                        </div>
                    </div>

                    <!-- Panel Expand: Detail Realisasi Per Item -->
                    <div class="row-expand">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="font-weight-bold mb-0 text-dark">
                                <i class="fas fa-list-check text-primary mr-1"></i> Rincian Realisasi Per Detail Item
                            </h6>
                            <?php if ($c_pend > 0): ?>
                            <form action="action.php" method="POST" style="display:inline;" onsubmit="return confirm('Setujui SEMUA (<?= $c_pend ?>) realisasi item yang menunggu pada budget ini?');">
                                <input type="hidden" name="id_budget" value="<?= $mid ?>">
                                <input type="hidden" name="action" value="Disetujui">
                                <input type="hidden" name="redirect_to" value="dashboard.php">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-check-double mr-1"></i> Setujui Semua yang Menunggu (<?= $c_pend ?> Item)
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($items)): ?>
                            <p class="text-muted small">Tidak ada rincian item pada anggaran ini.</p>
                        <?php else: ?>
                            <?php foreach ($items as $it): 
                                $it_id = (int)$it['id_detail'];
                                $it_reals = $reals_map[$it_id] ?? [];
                            ?>
                            <div class="item-box">
                                <div class="item-box-head">
                                    <div>
                                        <span class="badge badge-light border mr-1 font-weight-normal"><?= htmlspecialchars($it['category_name'] ?? '') ?></span>
                                        <strong style="font-size:13px;"><?= htmlspecialchars($it['rincian']) ?></strong>
                                        <small class="text-muted ml-2">(<?= $it['qty'] ?> <?= $it['satuan'] ?> @ Rp <?= number_format($it['harga_satuan'],0,',','.') ?>)</small>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-muted small">Alokasi:</span>
                                        <strong class="text-primary ml-1" style="font-size:13px;">Rp <?= number_format($it['total_ajuan'], 0, ',', '.') ?></strong>
                                    </div>
                                </div>

                                <div class="p-2 px-3">
                                    <?php if (empty($it_reals)): ?>
                                        <div class="text-muted small py-1 font-italic" style="font-size:11.5px;">
                                            <i class="fas fa-info-circle mr-1"></i> Pengaju belum menginputkan realisasi untuk item ini.
                                        </div>
                                    <?php else: ?>
                                        <table class="sub-table">
                                            <thead>
                                                <tr>
                                                    <th width="8%">Jenis</th>
                                                    <th width="14%">No. Transaksi</th>
                                                    <th width="10%">Tanggal</th>
                                                    <th width="14%" class="text-right">Nominal</th>
                                                    <th width="18%">Penerima / Vendor</th>
                                                    <th width="18%">Keperluan & Catatan</th>
                                                    <th width="8%">Bukti</th>
                                                    <th width="10%">Status / Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($it_reals as $rl): 
                                                    $rd_id = (int)$rl['id_real_detail'];
                                                    $st    = $rl['status'];
                                                    $jenis = $rl['jenis_transaksi'] ?? 'BKK';
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($jenis === 'BKM'): ?>
                                                            <span class="badge badge-success px-2 py-1" style="font-size:11px; font-weight:700;">
                                                                <i class="fas fa-arrow-down mr-1"></i> BKM
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-primary px-2 py-1" style="font-size:11px; font-weight:700; background:#1e3a8a;">
                                                                <i class="fas fa-arrow-up mr-1"></i> BKK
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong class="text-dark"><?= htmlspecialchars($rl['no_realisasi'] ?: '-') ?></strong></td>
                                                    <td><?= $rl['tanggal_realisasi'] ? date('d/m/Y', strtotime($rl['tanggal_realisasi'])) : '-' ?></td>
                                                    <td class="text-right font-weight-bold <?= $jenis === 'BKM' ? 'text-info' : 'text-success' ?>">
                                                        <?= $jenis === 'BKM' ? '+' : '' ?>Rp <?= number_format($rl['nominal_realisasi'], 0, ',', '.') ?>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($rl['dibayar_kepada'] ?: '—') ?></div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($rl['keperluan'])): ?>
                                                            <div style="color:#334155; font-size:12px; font-weight:500;"><?= htmlspecialchars($rl['keperluan']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($rl['catatan']) && $rl['catatan'] !== $rl['keperluan']): ?>
                                                            <small class="text-muted d-block"><?= htmlspecialchars($rl['catatan']) ?></small>
                                                        <?php endif; ?>
                                                        <?php if ($rl['catatan_approval']): ?>
                                                        <div class="text-danger small mt-1 font-italic">
                                                            <i class="fas fa-comment mr-1"></i> <?= htmlspecialchars($rl['catatan_approval']) ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($rl['bukti_files'])): ?>
                                                            <?php foreach (explode(',', $rl['bukti_files']) as $bf): 
                                                                $bf  = trim($bf); if (!$bf) continue;
                                                                $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                                                $url = '../uploads/realisasi/' . htmlspecialchars($bf);
                                                            ?>
                                                                <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                                                                <img src="<?= $url ?>" class="bk-img"
                                                                     onclick="previewImg('<?= $url ?>','<?= htmlspecialchars($bf) ?>')"
                                                                     title="<?= htmlspecialchars($bf) ?>">
                                                                <?php else: ?>
                                                                <a href="<?= $url ?>" target="_blank" class="bk-pdf">
                                                                    <i class="fas fa-file-pdf"></i> PDF
                                                                </a>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted small">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($st === 'Menunggu Approval'): ?>
                                                            <div class="d-flex align-items-center" style="gap:5px;">
                                                                <!-- Form Setujui -->
                                                                <form action="action.php" method="POST" style="display:inline;" onsubmit="return confirm('Setujui transaksi <?= $jenis ?> ini?');">
                                                                    <input type="hidden" name="id_real_detail" value="<?= $rd_id ?>">
                                                                    <input type="hidden" name="action" value="Disetujui">
                                                                    <input type="hidden" name="redirect_to" value="dashboard.php">
                                                                    <button type="submit" class="btn-act-ok" title="Setujui Transaksi Ini">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                                <!-- Tombol Tolak (Modal prompt) -->
                                                                <button type="button" class="btn-act-ng" title="Tolak Transaksi Ini"
                                                                        onclick="promptReject(<?= $rd_id ?>, '<?= htmlspecialchars($rl['no_realisasi'] ?: '') ?>')">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        <?php elseif ($st === 'Disetujui'): ?>
                                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Disetujui</span>
                                                            <?php if ($rl['approved_at']): ?>
                                                            <div class="text-muted" style="font-size:10px;"><?= date('d/m/Y H:i', strtotime($rl['approved_at'])) ?></div>
                                                            <?php endif; ?>
                                                        <?php elseif ($st === 'Ditolak'): ?>
                                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Ditolak</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary px-2 py-1"><?= $st ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div><!-- /row-expand -->
                </div><!-- /tbl-row -->
                <?php endforeach; ?>
            </div><!-- /tbl-wrap -->
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Reject dengan Catatan -->
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="fas fa-times-circle mr-1"></i> Tolak Realisasi Item</h6>
                <button class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="action.php" method="POST" id="formReject">
                <input type="hidden" name="id_real_detail" id="rejectIdRealDetail" value="">
                <input type="hidden" name="action" value="Ditolak">
                <input type="hidden" name="redirect_to" value="dashboard.php">
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Tolak realisasi <strong id="rejectLabel"></strong> dan berikan catatan revisi agar pengaju dapat memperbaiki:
                    </p>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Alasan Penolakan / Catatan <span class="text-danger">*</span></label>
                        <textarea name="catatan_approval" id="rejectCatatan" class="form-control" rows="3" required placeholder="Contoh: Bukti nota buram/tidak terbaca, mohon upload ulang nota asli."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-paper-plane mr-1"></i> Kirim Penolakan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Preview Gambar -->
<div class="modal fade" id="mPreview" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="mPreviewLbl">Bukti Nota / Kwitansi</h6>
                <button class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center" id="mPreviewBody"></div>
        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>

<script>
function toggleRow(uid) {
    const row  = document.getElementById(uid);
    const icon = document.getElementById('ico-' + uid);
    const open = row.classList.contains('open');
    document.querySelectorAll('.tbl-row.open').forEach(el => {
        el.classList.remove('open');
        const ic = document.getElementById('ico-' + el.id);
        if (ic) { ic.classList.replace('fa-chevron-up','fa-chevron-down'); }
    });
    if (!open) {
        row.classList.add('open');
        icon.classList.replace('fa-chevron-down','fa-chevron-up');
    }
}

function doFilter(f, el) {
    document.querySelectorAll('.f-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.tbl-row').forEach(r => {
        r.style.display = (f === 'all' || r.dataset.filter === f) ? '' : 'none';
    });
}

function promptReject(idRealDetail, noReal) {
    document.getElementById('rejectIdRealDetail').value = idRealDetail;
    document.getElementById('rejectLabel').textContent = noReal ? '#' + noReal : '';
    document.getElementById('rejectCatatan').value = '';
    $('#modalReject').modal('show');
}

function previewImg(url, name) {
    document.getElementById('mPreviewLbl').textContent = name;
    document.getElementById('mPreviewBody').innerHTML  = '<img src="' + url + '" style="max-width:100%; border-radius:6px;">';
    $('#mPreview').modal('show');
}
</script>
</body>
</html>

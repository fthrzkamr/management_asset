<?php
/**
 * Approval Realisasi – Dashboard Asmen
 * ─────────────────────────────────────
 * Tampilan:
 *  1. LIST semua anggaran (ppx_monthly_budget) dari semua user
 *  2. Klik satu baris → expand:
 *       - Semua rincian item anggaran per kategori
 *       - Jumlah yang sudah direalisasi (jika ada realisasi yg sudah disubmit)
 *       - Tombol Setujui / Tolak (hanya jika status realisasi = Menunggu Approval)
 */
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_realisasi_asmen', 'approve');

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

/* ───────── 1. Ambil semua anggaran bulanan + info realisasi (jika ada) ───── */
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
        rl.id_realisasi,
        rl.no_realisasi,
        rl.status       AS status_rl,
        rl.total_nominal_realisasi,
        rl.catatan_approval,
        rl.approved_at
    FROM ppx_monthly_budget mb
    JOIN user_dfm u
         ON mb.created_by = u.id_user
    LEFT JOIN ppx_realisasi_monthly rl
         ON  rl.id_budget = mb.id_budget
         AND rl.status   != 'Draft'
    WHERE mb.status = 'Active'
    ORDER BY mb.period_year DESC, mb.period_month DESC, u.nama_user ASC
");

$db_ok   = ($q_budgets !== false);
$budgets = [];
if ($db_ok) while ($r = mysqli_fetch_assoc($q_budgets)) $budgets[] = $r;

/* ───────── 2. Ambil semua rincian item anggaran + nilai realisasinya ──────── */
$id_budget_all = array_unique(array_column($budgets, 'id_budget'));
$id_real_all   = array_filter(array_unique(array_column($budgets, 'id_realisasi')));
$items_map     = [];   // [ id_budget => [ items ] ]

if (!empty($id_budget_all)) {
    $bids = implode(',', array_map('intval', $id_budget_all));
    $rids = !empty($id_real_all) ? implode(',', array_map('intval', $id_real_all)) : '0';

    $q_items = mysqli_query($conn, "
        SELECT
            bd.id AS id_detail, bd.id_budget,
            bd.rincian, bd.qty, bd.satuan, bd.harga_satuan, bd.total AS total_ajuan,
            bc.category_name,
            rd.id_real_detail,
            rd.id_realisasi     AS rd_id_realisasi,
            rd.nominal_realisasi,
            rd.uang_kembali,
            rd.catatan          AS catatan_item,
            rd.bukti_files
        FROM ppx_monthly_budget_item_detail bd
        LEFT JOIN ppx_budget_category bc
               ON bd.id_category = bc.id_category
        LEFT JOIN ppx_realisasi_detail2 rd
               ON  rd.id_detail_budget = bd.id
               AND rd.id_realisasi IN ($rids)
        WHERE bd.id_budget IN ($bids)
        ORDER BY bc.category_name ASC, bd.id ASC
    ");
    if ($q_items) {
        while ($it = mysqli_fetch_assoc($q_items)) {
            $items_map[$it['id_budget']][] = $it;
        }
    }
}

/* ───────── 3. Statistik ringkasan ────────────────────────────────────────── */
$cnt_all      = count($budgets);
$cnt_pending  = count(array_filter($budgets, fn($r) => $r['status_rl'] === 'Menunggu Approval'));
$cnt_approved = count(array_filter($budgets, fn($r) => $r['status_rl'] === 'Disetujui'));
$cnt_no_real  = count(array_filter($budgets, fn($r) => empty($r['id_realisasi'])));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Realisasi Bulanan</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ═══ Stat cards ═══ */
        .stat-card { border-radius:10px; border:none; padding:14px 16px;
                     box-shadow:0 2px 8px rgba(0,0,0,.07); text-align:center; }
        .pulse { animation:pw 2s ease-in-out infinite; }
        @keyframes pw { 0%,100%{box-shadow:0 2px 8px rgba(0,0,0,.07);}
                        50%{box-shadow:0 0 0 5px rgba(245,158,11,.22);} }

        /* ═══ Filter ═══ */
        .f-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
        .f-tab  { padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600;
                  border:2px solid #e5e7eb; background:#fff; cursor:pointer; transition:.15s; }
        .f-tab.active { border-color:#3b82f6; background:#eff6ff; color:#1d4ed8; }

        /* ═══ Tabel list ═══ */
        .tbl-wrap    { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
        .tbl-head    { display:flex; padding:9px 16px; background:#f1f5f9;
                       border-bottom:1px solid #e5e7eb;
                       font-size:11.5px; font-weight:700; color:#64748b;
                       text-transform:uppercase; letter-spacing:.4px; }
        .tbl-row     { border-bottom:1px solid #f1f5f9; }
        .tbl-row:last-child { border-bottom:none; }

        /* ═══ Baris klikable ═══ */
        .row-main {
            display:flex; align-items:center;
            padding:11px 16px; cursor:pointer;
            background:#fff; transition:.12s;
        }
        .row-main:hover        { background:#f8fafc; }
        .tbl-row.open .row-main{ background:#eff6ff; border-bottom:1px solid #dbeafe; }

        /* ═══ Kolom ═══ */
        .col-user   { flex:0 0 170px; min-width:130px; }
        .col-period { flex:0 0 120px; }
        .col-name   { flex:1; min-width:120px; }
        .col-alloc  { flex:0 0 120px; text-align:right; }
        .col-status { flex:0 0 165px; padding-left:6px; }
        .col-toggle { flex:0 0 32px; text-align:center; color:#94a3b8; }

        .av { width:28px; height:28px; border-radius:50%; flex-shrink:0;
              background:linear-gradient(135deg,#3b82f6,#1d4ed8);
              color:#fff; display:inline-flex; align-items:center; justify-content:center;
              font-size:11px; font-weight:700; margin-right:7px; }

        /* ═══ Pill status ═══ */
        .pill { display:inline-flex; align-items:center; gap:3px; border-radius:20px;
                padding:3px 9px; font-size:11px; font-weight:600; }
        .s-wait { background:#fef3c7; color:#92400e; }
        .s-ok   { background:#d1fae5; color:#065f46; }
        .s-rej  { background:#fee2e2; color:#991b1b; }
        .s-none { background:#f1f5f9; color:#64748b; }

        /* ═══ Panel expand ═══ */
        .row-expand { display:none; padding:16px; background:#fafcff;
                      border-bottom:1px solid #e5e7eb; }
        .tbl-row.open .row-expand { display:block; }

        /* ═══ Label kategori ═══ */
        .cat-lbl { font-size:10.5px; font-weight:700; color:#6366f1;
                   text-transform:uppercase; letter-spacing:.6px;
                   margin:12px 0 5px; }
        .cat-lbl:first-child { margin-top:0; }

        /* ═══ Baris item ═══ */
        .item-row {
            display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start;
            padding:8px 10px; border-radius:7px; margin-bottom:3px;
            font-size:12.5px; border:1px solid #f1f5f9;
        }
        .item-row.realized    { background:#f0fdf4; border-left:3px solid #34d399; }
        .item-row.not-realized{ background:#fffbeb; border-left:3px solid #fbbf24; }

        .ic-name  { flex:0 0 220px; }
        .ic-name .sub { font-size:11px; color:#94a3b8; display:block; margin-top:1px; }
        .ic-ajuan { flex:0 0 110px; text-align:right; color:#3b82f6; font-weight:600; }
        .ic-real  { flex:0 0 120px; text-align:right; font-weight:600; }
        .ic-kemb  { flex:0 0 110px; text-align:right; color:#64748b; }
        .ic-bukti { flex:1; min-width:80px; }

        /* ═══ Total strip ═══ */
        .tot { display:flex; gap:8px; background:#f1f5f9; border-radius:8px;
               padding:8px 12px; margin-top:8px; font-size:12.5px; font-weight:700; }

        /* ═══ Bukti ═══ */
        .bk-img { width:42px; height:34px; object-fit:cover; border-radius:5px;
                  border:1px solid #e5e7eb; cursor:pointer; margin:1px; }
        .bk-pdf { display:inline-flex; align-items:center; gap:3px; font-size:11px;
                  background:#fee2e2; color:#991b1b; border-radius:5px;
                  padding:2px 6px; text-decoration:none; }

        /* ═══ Approve box ═══ */
        .ap-box { background:#f0fdf4; border:1px solid #86efac;
                  border-radius:10px; padding:14px; margin-top:12px; }
        .btn-ok { background:#10b981; color:#fff; border:none; border-radius:7px;
                  padding:7px 18px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .btn-ok:hover { background:#059669; }
        .btn-ng { background:#ef4444; color:#fff; border:none; border-radius:7px;
                  padding:7px 18px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .btn-ng:hover { background:#dc2626; }

        .empty { text-align:center; padding:50px 20px; color:#9ca3af; }

        @media(max-width:768px){
            .col-alloc, .col-period { display:none; }
            .col-name { flex:1; }
            .ic-ajuan,.ic-real,.ic-kemb { display:none; }
        }
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
                        <h3 class="page-title mb-1">
                            <i class="fa-solid fa-stamp text-warning"></i> Approval Realisasi
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
            <div class="alert alert-warning">Tabel anggaran belum tersedia.</div>
            <?php elseif (empty($budgets)): ?>
            <div class="card"><div class="empty">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>Belum ada anggaran terdaftar.</p>
            </div></div>
            <?php else: ?>

            <!-- ── Stats ── -->
            <div class="row mb-4">
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-primary mb-1"><?= $cnt_all ?></div>
                        <div class="small text-muted">Total Anggaran</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-card card <?= $cnt_pending ? 'pulse' : '' ?>">
                        <div class="h3 font-weight-bold text-warning mb-1"><?= $cnt_pending ?></div>
                        <div class="small text-muted"><i class="fas fa-clock mr-1"></i>Menunggu Approve</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-success mb-1"><?= $cnt_approved ?></div>
                        <div class="small text-muted"><i class="fas fa-check-circle mr-1"></i>Disetujui</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <div class="stat-card card">
                        <div class="h3 font-weight-bold text-secondary mb-1"><?= $cnt_no_real ?></div>
                        <div class="small text-muted"><i class="fas fa-minus-circle mr-1"></i>Belum Realisasi</div>
                    </div>
                </div>
            </div>

            <!-- ── Filter ── -->
            <div class="f-tabs">
                <div class="f-tab active" onclick="doFilter('all',this)">
                    Semua <span class="badge badge-secondary ml-1"><?= $cnt_all ?></span>
                </div>
                <div class="f-tab" onclick="doFilter('wait',this)">
                    <i class="fas fa-clock text-warning"></i> Menunggu
                    <span class="badge badge-warning ml-1"><?= $cnt_pending ?></span>
                </div>
                <div class="f-tab" onclick="doFilter('ok',this)">
                    <i class="fas fa-check-circle text-success"></i> Disetujui
                </div>
                <div class="f-tab" onclick="doFilter('rej',this)">
                    <i class="fas fa-times-circle text-danger"></i> Ditolak
                </div>
                <div class="f-tab" onclick="doFilter('none',this)">
                    <i class="fas fa-minus-circle text-secondary"></i> Belum Realisasi
                </div>
            </div>

            <!-- ── Tabel list anggaran ── -->
            <div class="tbl-wrap">
                <!-- Header kolom -->
                <div class="tbl-head">
                    <div class="col-user">Pengaju</div>
                    <div class="col-period">Periode</div>
                    <div class="col-name">Nama Anggaran</div>
                    <div class="col-alloc">Alokasi</div>
                    <div class="col-status">Status Realisasi</div>
                    <div class="col-toggle"></div>
                </div>

                <!-- Baris per anggaran -->
                <?php foreach ($budgets as $b):
                    $mid  = $b['id_budget'];
                    $id   = $b['id_realisasi'];  // null jika belum ada realisasi
                    $st   = $b['status_rl'] ?? null;

                    // Filter attribute & badge
                    if ($st === 'Menunggu Approval') {
                        $fattr   = 'wait';
                        $pillCls = 's-wait';
                        $pillTxt = '<i class="fas fa-clock"></i> Menunggu Approval';
                    } elseif ($st === 'Disetujui') {
                        $fattr   = 'ok';
                        $pillCls = 's-ok';
                        $pillTxt = '<i class="fas fa-check-circle"></i> Disetujui';
                    } elseif ($st === 'Ditolak') {
                        $fattr   = 'rej';
                        $pillCls = 's-rej';
                        $pillTxt = '<i class="fas fa-times-circle"></i> Ditolak';
                    } else {
                        $fattr   = 'none';
                        $pillCls = 's-none';
                        $pillTxt = '<i class="fas fa-minus-circle"></i> Belum Direalisasi';
                    }

                    $initial = strtoupper(substr($b['nama_user'] ?? 'U', 0, 1));
                    $items   = $items_map[$mid] ?? [];

                    // Kelompokkan per kategori
                    $per_cat = [];
                    foreach ($items as $it) {
                        $cat = $it['category_name'] ?: 'Tanpa Kategori';
                        $per_cat[$cat][] = $it;
                    }
                    $t_ajuan = array_sum(array_column($items, 'total_ajuan'));
                    $t_real  = array_sum(array_column($items, 'nominal_realisasi'));
                    $t_kemb  = array_sum(array_column($items, 'uang_kembali'));
                    $row_uid = 'row-' . $mid; // unik per budget
                ?>
                <div class="tbl-row" data-filter="<?= $fattr ?>" id="<?= $row_uid ?>">

                    <!-- Baris ringkasan (klikable) -->
                    <div class="row-main" onclick="toggleRow('<?= $row_uid ?>')">
                        <div class="col-user d-flex align-items-center">
                            <div class="av"><?= $initial ?></div>
                            <span style="font-size:13px;font-weight:600;">
                                <?= htmlspecialchars($b['nama_user'] ?? '-') ?>
                            </span>
                        </div>
                        <div class="col-period" style="font-size:13px;">
                            <?= $bulan_names[$b['period_month']] ?> <?= $b['period_year'] ?>
                        </div>
                        <div class="col-name">
                            <div style="font-size:13px;color:#374151;font-weight:500;">
                                <?= htmlspecialchars($b['budget_name']) ?>
                            </div>
                            <?php if ($b['no_realisasi']): ?>
                            <small class="text-muted"><?= htmlspecialchars($b['no_realisasi']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-alloc">
                            <strong class="text-primary" style="font-size:13px;">
                                Rp <?= number_format($b['total_allocation'] ?? 0, 0, ',', '.') ?>
                            </strong>
                        </div>
                        <div class="col-status">
                            <span class="pill <?= $pillCls ?>"><?= $pillTxt ?></span>
                        </div>
                        <div class="col-toggle">
                            <i class="fas fa-chevron-down" id="ico-<?= $row_uid ?>"></i>
                        </div>
                    </div>

                    <!-- Panel expand: detail rincian + approve -->
                    <div class="row-expand">

                        <?php if ($b['catatan_approval']): ?>
                        <div class="alert alert-<?= $st==='Disetujui'?'success':'warning' ?> py-2 mb-3" style="font-size:12.5px;">
                            <i class="fas fa-comment-dots mr-1"></i>
                            <strong>Catatan Asmen:</strong> <?= htmlspecialchars($b['catatan_approval']) ?>
                            <?php if ($b['approved_at']): ?>
                            <span class="text-muted ml-2">(<?= date('d/m/Y H:i', strtotime($b['approved_at'])) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Sub-header kolom item -->
                        <div style="display:flex;gap:8px;padding:4px 10px;font-size:11px;
                                    font-weight:700;color:#94a3b8;text-transform:uppercase;
                                    letter-spacing:.4px;margin-bottom:4px;">
                            <div class="ic-name">Rincian</div>
                            <div class="ic-ajuan">Ajuan</div>
                            <div class="ic-real">Realisasi</div>
                            <div class="ic-kemb">Uang Kembali</div>
                            <div class="ic-bukti">Bukti</div>
                        </div>

                        <!-- Daftar item per kategori -->
                        <?php if (empty($items)): ?>
                        <p class="text-muted small">Tidak ada rincian item pada anggaran ini.</p>
                        <?php else: ?>

                        <?php foreach ($per_cat as $cat_name => $cat_items): ?>
                        <div class="cat-lbl">
                            <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($cat_name) ?>
                        </div>
                        <?php foreach ($cat_items as $it):
                            $done = !empty($it['id_real_detail']);
                        ?>
                        <div class="item-row <?= $done ? 'realized' : 'not-realized' ?>">
                            <div class="ic-name">
                                <strong><?= htmlspecialchars($it['rincian'] ?? '-') ?></strong>
                                <?php if (!empty($it['qty'])): ?>
                                <span class="sub"><?= $it['qty'] ?> <?= $it['satuan'] ?>
                                    &times; Rp <?= number_format($it['harga_satuan'] ?? 0, 0, ',', '.') ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($it['catatan_item']): ?>
                                <span class="sub"><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($it['catatan_item']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ic-ajuan">
                                Rp <?= number_format($it['total_ajuan'] ?? 0, 0, ',', '.') ?>
                            </div>
                            <div class="ic-real">
                                <?php if ($done): ?>
                                <span class="text-success">
                                    Rp <?= number_format($it['nominal_realisasi'] ?? 0, 0, ',', '.') ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:11px;font-style:italic;">
                                    Belum direalisasi
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="ic-kemb">
                                <?= $done ? 'Rp '.number_format($it['uang_kembali'] ?? 0, 0, ',', '.') : '—' ?>
                            </div>
                            <div class="ic-bukti">
                                <?php if ($done && $it['bukti_files']): ?>
                                <?php foreach (explode(',', $it['bukti_files']) as $bf):
                                    $bf  = trim($bf);
                                    if (!$bf) continue;
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
                                <?php elseif ($done): ?>
                                <span class="text-danger" style="font-size:11px;">
                                    <i class="fas fa-exclamation-triangle"></i> Belum ada bukti
                                </span>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:11px;">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; endforeach; ?>

                        <!-- Total -->
                        <div class="tot">
                            <div style="flex:0 0 220px;">Total</div>
                            <div style="flex:0 0 110px;text-align:right;color:#3b82f6;">
                                Rp <?= number_format($t_ajuan, 0, ',', '.') ?>
                            </div>
                            <div style="flex:0 0 120px;text-align:right;color:#10b981;">
                                Rp <?= number_format($t_real, 0, ',', '.') ?>
                            </div>
                            <div style="flex:0 0 110px;text-align:right;color:#64748b;">
                                Rp <?= number_format($t_kemb, 0, ',', '.') ?>
                            </div>
                            <div style="flex:1;"></div>
                        </div>
                        <?php endif; ?>

                        <!-- Tombol Approve/Tolak: hanya jika ada realisasi yg Menunggu -->
                        <?php if ($id && $st === 'Menunggu Approval'): ?>
                        <div class="ap-box">
                            <div class="font-weight-bold mb-2" style="font-size:13px;">
                                <i class="fas fa-gavel text-success"></i> Keputusan Asmen Finance
                            </div>
                            <form action="action.php" method="POST" id="form-<?= $id ?>">
                                <input type="hidden" name="id_realisasi" value="<?= $id ?>">
                                <input type="hidden" name="redirect_to"  value="dashboard.php">
                                <input type="hidden" name="action" id="act-<?= $id ?>" value="">
                                <div class="row align-items-end">
                                    <div class="col-md-8 mb-2">
                                        <label class="small font-weight-bold mb-1">
                                            Catatan
                                            <span class="text-muted font-weight-normal">
                                                (wajib diisi jika menolak)
                                            </span>
                                        </label>
                                        <textarea name="catatan_approval"
                                            class="form-control form-control-sm"
                                            id="cat-<?= $id ?>" rows="2"
                                            placeholder="Tulis catatan approval..."></textarea>
                                    </div>
                                    <div class="col-md-4 mb-2 d-flex" style="gap:8px;align-items:flex-end;">
                                        <button type="button" class="btn-ok"
                                                onclick="doApproval(<?= $id ?>,'Disetujui')">
                                            <i class="fas fa-check-circle"></i> Setujui
                                        </button>
                                        <button type="button" class="btn-ng"
                                                onclick="doApproval(<?= $id ?>,'Ditolak')">
                                            <i class="fas fa-times-circle"></i> Tolak
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                    </div><!-- /row-expand -->
                </div><!-- /tbl-row -->
                <?php endforeach; ?>
            </div><!-- /tbl-wrap -->

            <?php endif; ?>
        <?php
        if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
        if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
        ?>
        </div>
    </div>
</div>

<!-- Modal preview gambar -->
<div class="modal fade" id="mPreview" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="mPreviewLbl">Bukti</h6>
                <button class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center" id="mPreviewBody"></div>
        </div>
    </div>
</div>


<script>
/* Expand/collapse satu baris */
function toggleRow(uid) {
    const row  = document.getElementById(uid);
    const icon = document.getElementById('ico-' + uid);
    const open = row.classList.contains('open');
    // tutup semua
    document.querySelectorAll('.tbl-row.open').forEach(el => {
        el.classList.remove('open');
        const ic = document.getElementById('ico-' + el.id);
        if (ic) { ic.classList.replace('fa-chevron-up','fa-chevron-down'); }
    });
    // buka yg diklik
    if (!open) {
        row.classList.add('open');
        icon.classList.replace('fa-chevron-down','fa-chevron-up');
    }
}

/* Filter tab */
function doFilter(f, el) {
    document.querySelectorAll('.f-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.tbl-row').forEach(r => {
        r.style.display = (f === 'all' || r.dataset.filter === f) ? '' : 'none';
    });
}

/* Submit approval */
function doApproval(id, action) {
    const catEl = document.getElementById('cat-' + id);
    if (action === 'Ditolak' && !catEl.value.trim()) {
        alert('Catatan wajib diisi saat menolak!');
        catEl.focus(); return;
    }
    if (!confirm(action === 'Disetujui' ? 'Setujui realisasi ini?' : 'Tolak dan kembalikan ke pengaju?')) return;
    document.getElementById('act-' + id).value = action;
    document.getElementById('form-' + id).submit();
}

/* Preview bukti gambar */
function previewImg(url, name) {
    document.getElementById('mPreviewLbl').textContent = name;
    document.getElementById('mPreviewBody').innerHTML  = '<img src="' + url + '" style="max-width:100%">';
    $('#mPreview').modal('show');
}
</script>
</body>
</html>

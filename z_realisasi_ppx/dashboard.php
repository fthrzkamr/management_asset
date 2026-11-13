<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('realisasi_ppx', 'read');

$user_id  = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

// Filter: admin bisa lihat semua, user hanya punyanya
$filter_user = $is_admin ? "" : "AND mb.created_by = $user_id";

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// Ambil semua monthly_budget user dengan ringkasan realisasi detail per item
$query = "
SELECT
    mb.id_budget,
    mb.period_year AS tahun,
    mb.period_month AS bulan,
    mb.budget_name,
    mb.total_allocation,
    mb.total_used,
    mb.total_remaining,
    mb.status AS status_budget,
    mb.tipe_anggaran,
    u.nama_user AS nama_pembuat,
    mc.nama_cabang,

    -- Info realisasi teragregasi per detail
    COALESCE(rd_stats.total_approved, 0) AS total_approved,
    COALESCE(rd_stats.count_pending, 0)  AS count_pending,
    COALESCE(rd_stats.count_draft, 0)    AS count_draft,
    COALESCE(rd_stats.items_realized, 0) AS items_realized,
    (SELECT COUNT(*) FROM ppx_monthly_budget_item_detail WHERE id_budget = mb.id_budget) AS total_items

FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN (
    SELECT 
        id_budget,
        SUM(CASE WHEN status = 'Disetujui' THEN nominal_realisasi ELSE 0 END) AS total_approved,
        COUNT(CASE WHEN status = 'Menunggu Approval' THEN 1 END) AS count_pending,
        COUNT(CASE WHEN status = 'Draft' THEN 1 END) AS count_draft,
        COUNT(DISTINCT CASE WHEN status = 'Disetujui' THEN id_detail_budget END) AS items_realized
    FROM ppx_realisasi_detail2
    GROUP BY id_budget
) rd_stats ON rd_stats.id_budget = mb.id_budget

WHERE mb.status NOT IN ('Draft')
  $filter_user
ORDER BY mb.period_year DESC, mb.period_month DESC, mb.id_budget DESC";

$result = mysqli_query($conn, $query);

$budgets = [];
if ($result) while ($r = mysqli_fetch_assoc($result)) $budgets[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Realisasi Bulanan PPX</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rl-card {
            border-radius: 14px; border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            transition: transform .2s, box-shadow .2s;
            overflow: hidden; cursor: pointer;
        }
        .rl-card:hover { transform: translateY(-5px); box-shadow: 0 10px 28px rgba(0,0,0,.15); }

        /* Head warna per status realisasi */
        .head-new      { background: linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff; }
        .head-draft    { background: linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; }
        .head-waiting  { background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
        .head-approved { background: linear-gradient(135deg,#10b981,#059669); color:#fff; }
        .head-rejected { background: linear-gradient(135deg,#ef4444,#dc2626); color:#fff; }

        .card-head-inner { padding: 16px 20px 12px; }
        .bulan-label { font-size: 18px; font-weight: 700; letter-spacing: .4px; }
        .card-body-inner { padding: 14px 20px 18px; }

        .stat-row { display:flex; justify-content:space-between; margin-bottom:5px; font-size:12.5px; }
        .stat-row .lbl { color:#6b7280; }
        .stat-row .val { font-weight:600; color:#111; }

        .pill { display:inline-flex; align-items:center; gap:5px;
                border-radius:20px; padding:3px 12px; font-size:11px; font-weight:600; }
        .pill-new      { background:#dbeafe; color:#1e40af; }
        .pill-draft    { background:#ede9fe; color:#5b21b6; }
        .pill-waiting  { background:#fef3c7; color:#92400e; }
        .pill-approved { background:#d1fae5; color:#065f46; }
        .pill-rejected { background:#fee2e2; color:#991b1b; }

        .prog-wrap { height:6px; border-radius:4px; background:#e5e7eb; margin-top:6px; overflow:hidden; }
        .prog-bar  { height:100%; border-radius:4px; background:#3b82f6;
                     transition: width .6s ease; }
        .prog-bar.danger { background:#ef4444; }

        .empty-state { text-align:center; padding:60px 20px; color:#9ca3af; }
        .empty-state i { font-size:60px; margin-bottom:16px; display:block; }
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
                            <i class="fa-solid fa-check-double text-primary"></i> Realisasi Anggaran Bulanan
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Realisasi Bulanan</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php foreach (['success','error'] as $t): if (isset($_SESSION[$t])): ?>
                <div class="alert alert-<?= $t==='success'?'success':'danger' ?> alert-dismissible fade show">
                    <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i>
                    <?= htmlspecialchars($_SESSION[$t]) ?>
                    <button class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION[$t]); endif; ?>
            <?php endforeach; ?>

            <?php if (empty($budgets)): ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-xmark text-muted"></i>
                        <p class="h5 font-weight-bold mb-2">Belum Ada Anggaran Bulanan</p>
                        <p class="text-muted">Pengajuan Anggaran Bulanan (Monthly Budget) yang sudah disetujui akan muncul di sini untuk direalisasikan.</p>
                        <a href="../z_ppx_budget/monthly_budget_create.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus"></i> Buat Anggaran Bulanan
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($budgets as $mb):
                        $tot_alloc   = (float)$mb['total_allocation'];
                        $tot_app     = (float)$mb['total_approved'];
                        $cnt_pend    = (int)$mb['count_pending'];
                        $cnt_draft   = (int)$mb['count_draft'];
                        $tot_items   = (int)$mb['total_items'];
                        $it_realized = (int)$mb['items_realized'];

                        // Tentukan tampilan berdasarkan status realisasi detail
                        if ($cnt_pend > 0) {
                            $head_cls = 'head-waiting';
                            $pill_cls = 'pill-waiting';
                            $pill_ico = 'fas fa-clock';
                            $pill_txt = "$cnt_pend Menunggu Asmen";
                            $hint     = 'Ada realisasi item menunggu persetujuan';
                        } elseif ($it_realized >= $tot_items && $tot_items > 0) {
                            $head_cls = 'head-approved';
                            $pill_cls = 'pill-approved';
                            $pill_ico = 'fas fa-check-double';
                            $pill_txt = 'Semua Terealisasi';
                            $hint     = 'Seluruh item rincian sudah direalisasikan';
                        } elseif ($tot_app > 0) {
                            $head_cls = 'head-approved';
                            $pill_cls = 'pill-approved';
                            $pill_ico = 'fas fa-pie-chart';
                            $pill_txt = "$it_realized/$tot_items Item Terealisasi";
                            $hint     = 'Klik untuk lanjut realisasi item lainnya';
                        } elseif ($cnt_draft > 0) {
                            $head_cls = 'head-draft';
                            $pill_cls = 'pill-draft';
                            $pill_ico = 'fas fa-edit';
                            $pill_txt = "$cnt_draft Draft Tersimpan";
                            $hint     = 'Klik untuk lanjut input & kirim draft';
                        } else {
                            $head_cls = 'head-new';
                            $pill_cls = 'pill-new';
                            $pill_ico = 'fas fa-plus-circle';
                            $pill_txt = 'Belum Realisasi';
                            $hint     = 'Klik untuk mulai input realisasi per item';
                        }

                        $url = "detail.php?id_budget={$mb['id_budget']}";

                        // Progress penggunaan anggaran
                        $pct = $tot_alloc > 0 ? min(100, round(($tot_app / $tot_alloc) * 100)) : 0;
                        $prog_danger = $pct >= 80;
                    ?>
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-4">
                        <div class="rl-card card h-100" onclick="window.location='<?= $url ?>'">

                            <div class="<?= $head_cls ?> card-head-inner">
                                <div class="bulan-label">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?= $bulan_names[$mb['bulan']] ?> <?= $mb['tahun'] ?>
                                </div>
                                <small style="opacity:.85; font-size:11px;">
                                    <?= htmlspecialchars($mb['nama_cabang'] ?? ($mb['tipe_anggaran'] ?? '')) ?>
                                </small>
                            </div>

                            <div class="card-body-inner">
                                <!-- Status realisasi -->
                                <div class="mb-2">
                                    <span class="pill <?= $pill_cls ?>">
                                        <i class="<?= $pill_ico ?>"></i> <?= $pill_txt ?>
                                    </span>
                                </div>

                                <!-- Nama budget -->
                                <p class="small text-muted mb-2 font-weight-bold" style="font-size:12px; line-height:1.3;">
                                    <?= htmlspecialchars($mb['budget_name']) ?>
                                </p>

                                <hr style="margin:8px 0;">

                                <div class="stat-row">
                                    <span class="lbl"><i class="fas fa-coins mr-1"></i>Total Alokasi</span>
                                    <span class="val text-primary">Rp <?= number_format($tot_alloc, 0, ',', '.') ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="lbl"><i class="fas fa-shopping-cart mr-1"></i>Terealisasi</span>
                                    <span class="val text-success">Rp <?= number_format($tot_app, 0, ',', '.') ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="lbl"><i class="fas fa-wallet mr-1"></i>Sisa</span>
                                    <span class="val text-info">Rp <?= number_format(max(0, $tot_alloc - $tot_app), 0, ',', '.') ?></span>
                                </div>

                                <!-- Progress bar utilisasi -->
                                <div class="prog-wrap mt-1">
                                    <div class="prog-bar <?= $prog_danger ? 'danger' : '' ?>"
                                         style="width:<?= $pct ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1" style="font-size:10.5px; color:#9ca3af;">
                                    <span><?= $tot_items ?> item total</span>
                                    <span><?= $pct ?>% terealisasi</span>
                                </div>

                                <hr style="margin:8px 0;">
                                <div class="text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-hand-pointer mr-1"></i> <?= $hint ?>
                                    </small>
                                </div>
                            </div>

                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_realisasi_asmen', 'approve');

$asmen_id = (int)$_SESSION['id_user'];
$user_id  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { header("Location: dashboard.php"); exit(); }

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// Info user pengaju
$uq = mysqli_query($conn, "SELECT id_user, nama_user FROM user_dfm WHERE id_user = $user_id LIMIT 1");
if (!$uq || mysqli_num_rows($uq) === 0) {
    $_SESSION['error'] = 'User tidak ditemukan.';
    header("Location: dashboard.php"); exit();
}
$pengaju = mysqli_fetch_assoc($uq);

// Semua realisasi header milik user ini
$rl_q = mysqli_query($conn, "
    SELECT rl.*, mb.budget_name, mb.total_allocation
    FROM ppx_realisasi_monthly rl
    LEFT JOIN ppx_monthly_budget mb ON rl.id_budget = mb.id_budget
    WHERE rl.user_id = $user_id
    ORDER BY FIELD(rl.status,'Menunggu Approval','Ditolak','Draft','Disetujui'),
             rl.tahun DESC, rl.bulan DESC
");

$realisasi_list = [];
if ($rl_q) while ($r = mysqli_fetch_assoc($rl_q)) $realisasi_list[] = $r;

// Ambil detail semua item untuk semua realisasi milik user ini
$id_list = array_column($realisasi_list, 'id_realisasi');
$details_map = []; // [id_realisasi => [ items... ]]

if (!empty($id_list)) {
    $ids_str = implode(',', array_map('intval', $id_list));
    $det_q = mysqli_query($conn, "
        SELECT rd.*,
               bd.rincian, bd.qty, bd.satuan, bd.harga_satuan, bd.total AS total_ajuan,
               bc.category_name
        FROM ppx_realisasi_detail2 rd
        LEFT JOIN ppx_monthly_budget_item_detail bd ON rd.id_detail_budget = bd.id_detail
        LEFT JOIN ppx_budget_category bc ON bd.id_category = bc.id_category
        WHERE rd.id_realisasi IN ($ids_str)
        ORDER BY bc.category_name, rd.id_real_detail ASC
    ");
    if ($det_q) {
        while ($d = mysqli_fetch_assoc($det_q)) {
            $details_map[$d['id_realisasi']][] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Realisasi — <?= htmlspecialchars($pengaju['nama_user']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ── Realisasi Block ── */
        .rl-block {
            border: 1px solid #e5e7eb; border-radius: 14px;
            margin-bottom: 22px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .rl-block.pending  { border-left: 5px solid #f59e0b; }
        .rl-block.approved { border-left: 5px solid #10b981; }
        .rl-block.rejected { border-left: 5px solid #ef4444; }
        .rl-block.draft    { border-left: 5px solid #6366f1; }

        .rl-head {
            background: #f8fafc; padding: 14px 18px;
            border-bottom: 1px solid #e5e7eb;
            display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
        }
        .rl-body { padding: 16px 18px; }

        /* ── Item Cards ── */
        .item-row {
            border: 1px solid #e5e7eb; border-radius: 10px;
            margin-bottom: 10px; background: #fff; padding: 12px 14px;
        }
        .item-row:last-child { margin-bottom: 0; }
        .label-sm { font-size: 11.5px; color: #6b7280; margin-bottom: 2px; }

        /* ── Badges ── */
        .pill {
            display: inline-flex; align-items: center; gap: 4px;
            border-radius: 20px; padding: 3px 11px; font-size: 11px; font-weight: 600;
        }
        .pill-pending  { background:#fef3c7; color:#92400e; }
        .pill-approved { background:#d1fae5; color:#065f46; }
        .pill-rejected { background:#fee2e2; color:#991b1b; }
        .pill-draft    { background:#ede9fe; color:#5b21b6; }

        /* ── Summary bar ── */
        .summary-bar {
            display:flex; flex-wrap:wrap; gap:12px;
            background:#eff6ff; border-radius:10px;
            padding:12px 16px; margin-bottom:10px; font-size:13px;
        }
        .summary-bar div { flex:1; min-width:100px; }

        /* ── Approve panel ── */
        .approve-panel {
            background:#f0fdf4; border:1px solid #86efac;
            border-radius:10px; padding:16px; margin-top:12px;
        }
        .reject-panel {
            background:#fef2f2; border:1px solid #fca5a5;
            border-radius:10px; padding:16px; margin-top:12px;
        }

        /* ── Bukti thumbnail ── */
        .bukti-thumb {
            width:72px; height:56px; object-fit:cover;
            border-radius:6px; border:1px solid #e5e7eb; cursor:pointer;
        }
        .bukti-link {
            display:inline-flex; align-items:center; gap:4px;
            background:#e0f2fe; border-radius:6px;
            padding:4px 8px; font-size:11.5px; color:#0369a1;
            text-decoration:none; margin:2px;
        }
        .bukti-link:hover { background:#bae6fd; }

        /* ── User header ── */
        .user-header {
            background: linear-gradient(135deg,#3b82f6,#1d4ed8);
            color:#fff; border-radius:14px; padding:18px 22px; margin-bottom:22px;
        }
        .avatar-lg {
            width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.25);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; font-weight:700; flex-shrink:0;
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <!-- Breadcrumb -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="fa-solid fa-stamp text-warning"></i> Review Realisasi
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Approval Realisasi</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($pengaju['nama_user']) ?></li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <?php foreach (['success','error'] as $t): if (isset($_SESSION[$t])): ?>
            <div class="alert alert-<?= $t==='success'?'success':'danger' ?> alert-dismissible fade show">
                <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i>
                <?= htmlspecialchars($_SESSION[$t]) ?>
                <button class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION[$t]); endif; endforeach; ?>

            <!-- User Header -->
            <div class="user-header d-flex align-items-center" style="gap:16px;">
                <div class="avatar-lg"><?= strtoupper(substr($pengaju['nama_user'], 0, 1)) ?></div>
                <div>
                    <div style="font-size:18px; font-weight:700;"><?= htmlspecialchars($pengaju['nama_user']) ?></div>
                    <div style="opacity:.85; font-size:13px;">
                        <?php
                        $cnt_total   = count($realisasi_list);
                        $cnt_pending = count(array_filter($realisasi_list, fn($r)=>$r['status']==='Menunggu Approval'));
                        $cnt_done    = count(array_filter($realisasi_list, fn($r)=>$r['status']==='Disetujui'));
                        $cnt_rej     = count(array_filter($realisasi_list, fn($r)=>$r['status']==='Ditolak'));
                        ?>
                        <?= $cnt_total ?> anggaran
                        &bull; <?= $cnt_pending ?> menunggu approval
                        &bull; <?= $cnt_done ?> disetujui
                        <?= $cnt_rej > 0 ? "&bull; $cnt_rej ditolak" : '' ?>
                    </div>
                </div>
            </div>

            <!-- Daftar Realisasi per Budget -->
            <?php if (empty($realisasi_list)): ?>
                <div class="card text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada realisasi dari pengaju ini.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($realisasi_list as $rl):
                $status    = $rl['status'];
                $id_rl     = $rl['id_realisasi'];
                $items     = $details_map[$id_rl] ?? [];
                $can_approve = ($status === 'Menunggu Approval');
                $blockCls  = match($status) {
                    'Menunggu Approval' => 'pending',
                    'Disetujui'         => 'approved',
                    'Ditolak'           => 'rejected',
                    default             => 'draft',
                };
                $pillCls   = match($status) {
                    'Menunggu Approval' => 'pill-pending',
                    'Disetujui'         => 'pill-approved',
                    'Ditolak'           => 'pill-rejected',
                    default             => 'pill-draft',
                };
                $pillIco   = match($status) {
                    'Menunggu Approval' => 'fas fa-clock',
                    'Disetujui'         => 'fas fa-check-circle',
                    'Ditolak'           => 'fas fa-times-circle',
                    default             => 'fas fa-edit',
                };
                $total_real_items = array_sum(array_column($items, 'nominal_realisasi'));
            ?>
            <div class="rl-block <?= $blockCls ?>">
                <!-- Header Blok -->
                <div class="rl-head">
                    <div class="flex-grow-1">
                        <div class="font-weight-bold" style="font-size:14.5px;">
                            <i class="far fa-calendar-alt text-primary mr-1"></i>
                            <?= $bulan_names[$rl['bulan']] ?> <?= $rl['tahun'] ?>
                            <small class="text-muted ml-1">— <?= htmlspecialchars($rl['budget_name'] ?? '') ?></small>
                        </div>
                        <small class="text-muted">No: <?= htmlspecialchars($rl['no_realisasi']) ?></small>
                    </div>
                    <span class="pill <?= $pillCls ?>">
                        <i class="<?= $pillIco ?>"></i> <?= $status ?>
                    </span>
                </div>

                <!-- Body -->
                <div class="rl-body">
                    <!-- Ringkasan nilai -->
                    <div class="summary-bar">
                        <div>
                            <div class="label-sm">Total Alokasi Anggaran</div>
                            <strong class="text-primary">Rp <?= number_format($rl['total_allocation'] ?? 0, 0, ',', '.') ?></strong>
                        </div>
                        <div>
                            <div class="label-sm">Total Nominal Realisasi</div>
                            <strong class="text-success">Rp <?= number_format($rl['total_nominal_realisasi'] ?? 0, 0, ',', '.') ?></strong>
                        </div>
                        <div>
                            <div class="label-sm">Jumlah Item</div>
                            <strong><?= count($items) ?> item</strong>
                        </div>
                        <?php if ($rl['approved_at']): ?>
                        <div>
                            <div class="label-sm">Tgl Approval</div>
                            <strong><?= date('d/m/Y H:i', strtotime($rl['approved_at'])) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($rl['catatan_approval']): ?>
                    <div class="alert alert-<?= $status==='Disetujui'?'success':'warning' ?> py-2 mb-3" style="font-size:12.5px;">
                        <i class="fas fa-comment-dots mr-1"></i>
                        <strong>Catatan Asmen:</strong> <?= htmlspecialchars($rl['catatan_approval']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Detail per Item -->
                    <?php if (empty($items)): ?>
                        <p class="text-muted small">Belum ada item detail yang diinput.</p>
                    <?php else: ?>
                        <?php
                        // Kelompokkan per kategori
                        $per_cat = [];
                        foreach ($items as $it) {
                            $cat = $it['category_name'] ?: 'Tanpa Kategori';
                            $per_cat[$cat][] = $it;
                        }
                        foreach ($per_cat as $cat_name => $cat_items):
                        ?>
                        <div class="mb-1" style="font-size:12px; font-weight:600; color:#6366f1; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px!important;">
                            <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($cat_name) ?>
                        </div>
                        <?php foreach ($cat_items as $det): ?>
                        <div class="item-row">
                            <div class="row align-items-start">
                                <div class="col-md-4 mb-2">
                                    <div class="font-weight-bold" style="font-size:13px;">
                                        <?= htmlspecialchars($det['rincian'] ?? '-') ?>
                                    </div>
                                    <?php if (!empty($det['qty'])): ?>
                                    <small class="text-muted"><?= $det['qty'] ?> <?= $det['satuan'] ?> &times; Rp <?= number_format($det['harga_satuan'], 0, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-md-2 mb-2">
                                    <div class="label-sm">Ajuan</div>
                                    <strong class="text-primary" style="font-size:13px;">Rp <?= number_format($det['total_ajuan'] ?? 0, 0, ',', '.') ?></strong>
                                </div>
                                <div class="col-6 col-md-2 mb-2">
                                    <div class="label-sm">Realisasi</div>
                                    <strong class="text-success" style="font-size:13px;">Rp <?= number_format($det['nominal_realisasi'] ?? 0, 0, ',', '.') ?></strong>
                                </div>
                                <div class="col-6 col-md-2 mb-2">
                                    <div class="label-sm">Uang Kembali</div>
                                    <strong style="font-size:13px;">Rp <?= number_format($det['uang_kembali'] ?? 0, 0, ',', '.') ?></strong>
                                </div>
                                <div class="col-6 col-md-2 mb-2">
                                    <!-- Bukti -->
                                    <?php if ($det['bukti_files']): ?>
                                    <div class="label-sm">Bukti</div>
                                    <div class="d-flex flex-wrap" style="gap:4px;">
                                        <?php foreach (explode(',', $det['bukti_files']) as $bf):
                                            $bf = trim($bf); if (!$bf) continue;
                                            $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                            $url = '../uploads/realisasi/' . htmlspecialchars($bf);
                                        ?>
                                            <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                                            <img src="<?= $url ?>" class="bukti-thumb"
                                                 onclick="previewImg('<?= $url ?>','<?= htmlspecialchars($bf) ?>')"
                                                 title="<?= htmlspecialchars($bf) ?>">
                                            <?php else: ?>
                                            <a href="<?= $url ?>" target="_blank" class="bukti-link">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-danger" style="font-size:11px;"><i class="fas fa-exclamation-triangle"></i> Belum ada bukti</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($det['catatan']): ?>
                            <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                <i class="fas fa-comment-alt mr-1"></i> <?= htmlspecialchars($det['catatan']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endforeach; ?>
                    <?php endif; ?>

                    <!-- Panel Approve / Tolak (hanya untuk yang menunggu) -->
                    <?php if ($can_approve): ?>
                    <div class="approve-panel mt-3" id="panel-<?= $id_rl ?>">
                        <h6 class="mb-3">
                            <i class="fas fa-gavel text-success"></i> Keputusan Asmen Finance
                        </h6>
                        <form action="action.php" method="POST">
                            <input type="hidden" name="id_realisasi" value="<?= $id_rl ?>">
                            <input type="hidden" name="redirect_to" value="detail_user.php?user_id=<?= $user_id ?>">
                            <input type="hidden" name="action" id="act-<?= $id_rl ?>" value="">
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold">
                                    Catatan <span class="text-muted font-weight-normal">(wajib diisi jika menolak)</span>
                                </label>
                                <textarea name="catatan_approval" class="form-control form-control-sm"
                                    id="cat-<?= $id_rl ?>" rows="2"
                                    placeholder="Misal: Data sesuai / Nominal tidak sesuai bukti..."></textarea>
                            </div>
                            <div class="d-flex" style="gap:8px;">
                                <button type="button" class="btn btn-success btn-sm"
                                        onclick="doApproval(<?= $id_rl ?>,'Disetujui')">
                                    <i class="fas fa-check-circle"></i> Setujui
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="doApproval(<?= $id_rl ?>,'Ditolak')">
                                    <i class="fas fa-times-circle"></i> Tolak & Kembalikan
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                </div><!-- /rl-body -->
            </div><!-- /rl-block -->
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- Modal Preview Gambar -->
<div class="modal fade" id="modalBukti" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modalBuktiLabel">Preview</h6>
                <button class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center" id="modalBuktiBody"></div>
        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script>
function previewImg(url, name) {
    document.getElementById('modalBuktiLabel').textContent = name;
    document.getElementById('modalBuktiBody').innerHTML = '<img src="' + url + '" style="max-width:100%;">';
    $('#modalBukti').modal('show');
}

function doApproval(id, action) {
    const catatan = document.getElementById('cat-' + id).value.trim();
    if (action === 'Ditolak' && !catatan) {
        alert('Harap isi catatan saat menolak realisasi!');
        document.getElementById('cat-' + id).focus();
        return;
    }
    const msg = action === 'Disetujui'
        ? 'Setujui realisasi ini?'
        : 'Tolak dan kembalikan ke pengaju untuk diperbaiki?';
    if (!confirm(msg)) return;
    document.getElementById('act-' + id).value = action;
    document.getElementById('panel-' + id).querySelector('form').submit();
}
</script>
</body>
</html>

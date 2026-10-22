<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_realisasi_asmen', 'approve');

$asmen_id = (int)$_SESSION['id_user'];
$id_real  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_real) { header("Location: dashboard.php"); exit(); }

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// Ambil header realisasi
$r = mysqli_query($conn, "SELECT rl.*, u.nama_user AS nama_pengaju
    FROM ppx_realisasi_monthly rl
    LEFT JOIN user_dfm u ON rl.user_id = u.id_user
    WHERE rl.id_realisasi = $id_real LIMIT 1");
if (!$r || mysqli_num_rows($r) === 0) {
    $_SESSION['error'] = 'Data realisasi tidak ditemukan.';
    header("Location: dashboard.php"); exit();
}
$realisasi = mysqli_fetch_assoc($r);

// Ambil detail per item budget dari tabel baru
$det_q = mysqli_query($conn, "
    SELECT rd.*,
           bd.rincian, bd.qty, bd.satuan, bd.harga_satuan, bd.total AS total_ajuan,
           bc.category_name
    FROM ppx_realisasi_detail2 rd
    LEFT JOIN ppx_monthly_budget_item_detail bd ON rd.id_detail_budget = bd.id_detail
    LEFT JOIN ppx_budget_category bc ON bd.id_category = bc.id_category
    WHERE rd.id_realisasi = $id_real
    ORDER BY bc.category_name, rd.id_real_detail ASC");

$details = [];
if ($det_q) while ($d = mysqli_fetch_assoc($det_q)) $details[] = $d;

$can_approve = ($realisasi['status'] === 'Menunggu Approval');
$total_ajuan = array_sum(array_column($details, 'total_ajuan'));
$total_real  = array_sum(array_column($details, 'nominal_realisasi'));
$selisih     = $total_ajuan - $total_real;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Realisasi — <?= htmlspecialchars($realisasi['no_realisasi']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .info-box  { background:#f8fafc; border-radius:10px; padding:16px 20px; margin-bottom:20px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 20px; font-size:13px; }
        @media(max-width:576px){ .info-grid{grid-template-columns:1fr;} }
        .lbl { color:#6b7280; } .val { font-weight:600; color:#111; }
        .ppx-card { border:1px solid #e5e7eb; border-radius:10px; margin-bottom:14px; overflow:hidden; }
        .ppx-head  { background:#f1f5f9; padding:12px 16px; border-bottom:1px solid #e5e7eb;
                     display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; }
        .ppx-body  { padding:16px; }
        .bukti-link { display:inline-flex; align-items:center; gap:5px; background:#e0f2fe;
                      border-radius:6px; padding:4px 10px; font-size:12px; color:#0369a1;
                      text-decoration:none; margin:3px; }
        .bukti-link:hover { background:#bae6fd; }
        .bukti-img-thumb { width:80px; height:60px; object-fit:cover; border-radius:6px;
                           border:1px solid #e5e7eb; cursor:pointer; }
        .totals-bar { display:flex; flex-wrap:wrap; gap:16px; background:#eff6ff;
                      padding:14px 20px; border-radius:10px; margin-bottom:20px; }
        .totals-bar div { flex:1; min-width:120px; }
        .label-sm { font-size:12px; color:#6b7280; margin-bottom:2px; }
        .selisih-minus { color:#dc2626; font-weight:700; }
        .selisih-plus  { color:#059669; font-weight:700; }
        /* Modal bukti */
        #modalBukti img { max-width:100%; border-radius:8px; }
        .badge-wait2  { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 12px; font-size:12px; }
        .badge-ok2    { background:#d1fae5; color:#065f46; border-radius:20px; padding:3px 12px; font-size:12px; }
        .badge-rej2   { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 12px; font-size:12px; }
        .approve-panel { background:#f0fdf4; border:1px solid #86efac; border-radius:12px; padding:20px; }
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
                            <i class="fa-solid fa-stamp text-warning"></i>
                            Review Realisasi — <?= htmlspecialchars($realisasi['no_realisasi']) ?>
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Approval Realisasi</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($realisasi['no_realisasi']) ?></li>
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

            <!-- Info Header Realisasi -->
            <div class="info-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="fas fa-info-circle text-primary"></i> Informasi Realisasi</h6>
                    <?php if ($realisasi['status']==='Menunggu Approval'): ?>
                        <span class="badge-wait2"><i class="fas fa-clock"></i> Menunggu Approval</span>
                    <?php elseif($realisasi['status']==='Disetujui'): ?>
                        <span class="badge-ok2"><i class="fas fa-check-circle"></i> Disetujui</span>
                    <?php elseif($realisasi['status']==='Ditolak'): ?>
                        <span class="badge-rej2"><i class="fas fa-times-circle"></i> Ditolak</span>
                    <?php endif; ?>
                </div>
                <div class="info-grid">
                    <div><span class="lbl">No Realisasi:</span>
                         <span class="val"><?= htmlspecialchars($realisasi['no_realisasi']) ?></span></div>
                    <div><span class="lbl">Pengaju:</span>
                         <span class="val"><?= htmlspecialchars($realisasi['nama_pengaju'] ?? '-') ?></span></div>
                    <div><span class="lbl">Periode:</span>
                         <span class="val"><?= $bulan_names[$realisasi['bulan']] ?> <?= $realisasi['tahun'] ?></span></div>
                    <div><span class="lbl">Tgl Submit:</span>
                         <span class="val"><?= $realisasi['created_at'] ? date('d/m/Y H:i', strtotime($realisasi['created_at'])) : '-' ?></span></div>
                    <?php if ($realisasi['approved_at']): ?>
                    <div><span class="lbl">Tgl Approval:</span>
                         <span class="val"><?= date('d/m/Y H:i', strtotime($realisasi['approved_at'])) ?></span></div>
                    <?php endif; ?>
                    <?php if ($realisasi['catatan_approval']): ?>
                    <div style="grid-column:1/-1;"><span class="lbl">Catatan Asmen:</span>
                         <span class="val"><?= nl2br(htmlspecialchars($realisasi['catatan_approval'])) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Totals Bar -->
            <div class="totals-bar">
                <div>
                    <div class="label-sm">Total Item</div>
                    <strong><?= count($details) ?> item rincian</strong>
                </div>
                <div>
                    <div class="label-sm">Total Nominal Ajuan</div>
                    <strong class="text-primary">Rp <?= number_format($total_ajuan, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <div class="label-sm">Total Nominal Realisasi</div>
                    <strong class="text-success">Rp <?= number_format($total_real, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <div class="label-sm">Selisih (Ajuan − Realisasi)</div>
                    <strong class="<?= $selisih >= 0 ? 'selisih-minus' : 'selisih-plus' ?>">
                        <?= $selisih >= 0 ? '−' : '+' ?> Rp <?= number_format(abs($selisih), 0, ',', '.') ?>
                    </strong>
                </div>
            </div>

            <!-- Detail per Item -->
            <h6 class="mb-3"><i class="fas fa-list-alt text-primary"></i> Detail Realisasi per Item Rincian</h6>
            <?php if (empty($details)): ?>
                <p class="text-muted">Tidak ada detail.</p>
            <?php else: ?>
            <?php foreach ($details as $det): ?>
            <div class="ppx-card">
                <div class="ppx-head">
                    <div>
                        <strong class="text-primary"><?= htmlspecialchars($det['rincian'] ?? '-') ?></strong>
                        <?php
                            $meta = [];
                            if (!empty($det['qty'])) $meta[] = $det['qty'] . ' ' . $det['satuan'];
                            if (!empty($det['harga_satuan'])) $meta[] = 'Rp ' . number_format($det['harga_satuan'],0,',','.');
                        ?>
                        <?php if ($meta): ?>
                        <span class="text-muted small ml-2"><?= implode(' · ', $meta) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge badge-info badge-sm">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($det['category_name'] ?? '-') ?>
                        </span>
                    </div>
                </div>
                <div class="ppx-body">
                    <div class="row mb-2">
                        <div class="col-md-3 mb-2">
                            <div class="label-sm">Nominal Ajuan</div>
                            <strong class="text-primary">Rp <?= number_format($det['total_ajuan'], 0, ',', '.') ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label-sm">Nominal Realisasi</div>
                            <strong class="text-success">Rp <?= number_format($det['nominal_realisasi'], 0, ',', '.') ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label-sm">Uang Kembali</div>
                            <strong>Rp <?= number_format($det['uang_kembali'] ?? 0, 0, ',', '.') ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label-sm">Selisih Item</div>
                            <?php $s = $det['total_ajuan'] - $det['nominal_realisasi']; ?>
                            <strong class="<?= $s >=0 ? 'selisih-minus' : 'selisih-plus' ?>">
                                <?= $s>=0?'−':'+' ?> Rp <?= number_format(abs($s), 0, ',', '.') ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($det['catatan']): ?>
                    <p class="small text-muted mb-2"><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($det['catatan']) ?></p>
                    <?php endif; ?>

                    <!-- Bukti -->
                    <?php if ($det['bukti_files']): ?>
                    <div>
                        <p class="small font-weight-bold mb-1"><i class="fas fa-paperclip"></i> Bukti Pembayaran:</p>
                        <div class="d-flex flex-wrap" style="gap:8px;">
                            <?php foreach (explode(',', $det['bukti_files']) as $bf):
                                $bf = trim($bf); if (!$bf) continue;
                                $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                $is_img = in_array($ext, ['jpg','jpeg','png']);
                                $url = '../uploads/realisasi/' . htmlspecialchars($bf);
                            ?>
                                <?php if ($is_img): ?>
                                <img src="<?= $url ?>" class="bukti-img-thumb"
                                     onclick="previewImg('<?= $url ?>', '<?= htmlspecialchars($bf) ?>')"
                                     title="<?= htmlspecialchars($bf) ?>">
                                <?php else: ?>
                                <a href="<?= $url ?>" target="_blank" class="bukti-link">
                                    <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($bf) ?>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="small text-danger"><i class="fas fa-exclamation-triangle"></i> Belum ada bukti diupload.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Panel Approve / Tolak -->
            <?php if ($can_approve): ?>
            <div class="approve-panel mt-4">
                <h6 class="mb-3"><i class="fas fa-gavel text-success"></i> Keputusan Asmen Finance</h6>
                <form action="action.php" method="POST" id="formApprove">
                    <input type="hidden" name="id_realisasi" value="<?= $id_real ?>">
                    <input type="hidden" name="action" id="approve_action" value="">

                    <div class="form-group">
                        <label class="font-weight-bold small">Catatan Approval <span class="text-muted font-weight-normal">(wajib diisi jika menolak)</span></label>
                        <textarea name="catatan_approval" class="form-control form-control-sm"
                            rows="3" id="catatan_field"
                            placeholder="Misal: Data sudah sesuai / Nominal tidak cocok dengan bukti..."></textarea>
                    </div>

                    <div class="d-flex" style="gap:10px;">
                        <button type="button" class="btn btn-success"
                            onclick="submitApproval('Disetujui')">
                            <i class="fas fa-check-circle"></i> Setujui Realisasi
                        </button>
                        <button type="button" class="btn btn-danger"
                            onclick="submitApproval('Ditolak')">
                            <i class="fas fa-times-circle"></i> Tolak &amp; Kembalikan
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php endif; // empty details ?>

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

function submitApproval(action) {
    const catatan = document.getElementById('catatan_field').value.trim();
    if (action === 'Ditolak' && !catatan) {
        alert('Harap isi catatan saat menolak realisasi!');
        document.getElementById('catatan_field').focus();
        return;
    }
    const msg = action === 'Disetujui'
        ? 'Setujui realisasi ini? Pengaju akan mendapat notifikasi bahwa realisasinya sudah disetujui.'
        : 'Tolak realisasi ini? Pengaju harus memperbaiki dan mengirim ulang.';
    if (!confirm(msg)) return;
    document.getElementById('approve_action').value = action;
    document.getElementById('formApprove').submit();
}
</script>
</body>
</html>

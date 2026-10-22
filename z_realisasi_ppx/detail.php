<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn())  { header("Location: ../login.php"); exit(); }
$auth->requirePermission('realisasi_ppx', 'read');

$user_id      = (int)$_SESSION['id_user'];
$is_admin     = $auth->isSuperAdmin();
$id_budget    = isset($_GET['id_budget']) ? (int)$_GET['id_budget'] : 0;

if (!$id_budget) { header("Location: dashboard.php"); exit(); }

$bulan_names = ['','Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];

// ── Ambil data Monthly Budget ────────────────────────────────────────────
$filter_owner = $is_admin ? "" : "AND mb.created_by = $user_id";
$mb_q = mysqli_query($conn, "
    SELECT mb.*, u.nama_user AS nama_pembuat, d.nama_divisi
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN divisi d   ON u.divisi = d.id_divisi
    WHERE mb.id_budget = $id_budget $filter_owner
    LIMIT 1");

if (!$mb_q || mysqli_num_rows($mb_q) === 0) {
    $_SESSION['error'] = 'Anggaran bulanan tidak ditemukan atau akses ditolak.';
    header("Location: dashboard.php"); exit();
}
$budget = mysqli_fetch_assoc($mb_q);

// ── Ambil item detail dari anggaran ini ──────────────────────────────────
$items_q = mysqli_query($conn, "
    SELECT
        d.id AS id_detail, d.rincian, d.qty, d.satuan, d.jumlah_hari, d.jumlah_minggu,
        d.harga_satuan, d.total,
        c.category_name
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

// ── Ambil semua record realisasi per item ────────────────────────────────
$real_details = []; // [id_detail => data_realisasi]
$rl_chk = mysqli_query($conn, "
    SELECT r.id_realisasi, r.no_realisasi, r.status AS status_realisasi, r.catatan_approval,
           d.id_detail_budget, d.nominal_realisasi, d.uang_kembali, d.catatan, d.bukti_files
    FROM ppx_realisasi_monthly r
    JOIN ppx_realisasi_detail2 d ON r.id_realisasi = d.id_realisasi
    WHERE r.id_budget = $id_budget AND r.user_id = $user_id
");
if ($rl_chk) {
    while ($rl = mysqli_fetch_assoc($rl_chk)) {
        // Asumsi satu item = max satu realisasi aktif
        $real_details[$rl['id_detail_budget']] = $rl;
    }
}

$total_nominal_ajuan = (float)($budget['total_allocation']);
$total_nominal_real  = array_sum(array_column($real_details, 'nominal_realisasi'));
$selisih = $total_nominal_ajuan - $total_nominal_real;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Realisasi – <?= $bulan_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php'; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-block { border:1px solid #e5e7eb; border-radius:10px; margin-bottom:18px; overflow:hidden; }
        .category-head  { background:#f1f5f9; padding:10px 16px; font-weight:600; font-size:13.5px;
                          border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .info-box      { background:#f8fafc; border-radius:10px; padding:16px 20px; margin-bottom:20px; }
        .info-grid     { display:grid; grid-template-columns:1fr 1fr; gap:5px 20px; font-size:13px; }
        @media(max-width:576px){ .info-grid{ grid-template-columns:1fr; } }
        .lbl { color:#6b7280; }
        .val { font-weight:600; color:#111; }
        
        .item-card { border:1px solid #e5e7eb; border-radius:10px; margin-bottom:18px; background:#fff; overflow:hidden;}
        .item-head { background:#f9fafb; padding:12px 16px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .item-body { padding:14px 16px; }
        .item-nama { font-weight:600; font-size:14px; margin-bottom:4px;}
        .item-meta { font-size:12px; color:#6b7280; }
        
        .totals-bar     { display:flex; flex-wrap:wrap; gap:16px; background:#eff6ff;
                          padding:14px 20px; border-radius:10px; margin-bottom:22px; }
        .totals-bar > div { flex:1; min-width:120px; }
        .label-sm       { font-size:11.5px; color:#6b7280; margin-bottom:2px; }
        .bukti-link     { display:inline-flex; align-items:center; gap:4px; background:#e0f2fe;
                          border-radius:6px; padding:3px 10px; font-size:11.5px;
                          color:#0369a1; text-decoration:none; margin:2px; }
        .bukti-link:hover { background:#bae6fd; }
        
        .badge-status { border-radius:20px; padding:4px 12px; font-size:11.5px; font-weight:600; display:inline-flex; align-items:center; gap:5px; }
        .bs-new      { background:#dbeafe; color:#1e40af; }
        .bs-draft    { background:#ede9fe; color:#5b21b6; }
        .bs-wait     { background:#fef3c7; color:#92400e; }
        .bs-ok       { background:#d1fae5; color:#065f46; }
        .bs-rej      { background:#fee2e2; color:#991b1b; }
        
        .realisasi-form-box { display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-top:10px; }
        .realisasi-data-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px; margin-top:10px; font-size:13px; }
        
        .upload-preview { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; }
        .prev-item { background:#e0f2fe; border-radius:6px; padding:3px 10px;
                     font-size:11px; color:#0369a1; }
        .selisih-minus { color:#dc2626; font-weight:700; }
        .selisih-plus  { color:#059669; font-weight:700; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <!-- Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="far fa-calendar-alt text-primary"></i>
                            Realisasi – <?= $bulan_names[$budget['period_month']] ?> <?= $budget['period_year'] ?>
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Realisasi Bulanan</a></li>
                            <li class="breadcrumb-item active"><?= $bulan_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash -->
            <?php foreach (['success','error'] as $t): if (isset($_SESSION[$t])): ?>
            <div class="alert alert-<?= $t==='success'?'success':'danger' ?> alert-dismissible fade show">
                <i class="fas fa-<?= $t==='success'?'check':'exclamation' ?>-circle"></i>
                <?= htmlspecialchars($_SESSION[$t]) ?>
                <button class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION[$t]); endif; endforeach; ?>

            <!-- Info Budget -->
            <div class="info-box">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:6px;">
                    <h6 class="mb-0"><i class="fas fa-info-circle text-primary mr-1"></i>Info Anggaran Bulanan</h6>
                    <div>
                        <span class="badge badge-info small"><?= htmlspecialchars($budget['tipe_anggaran'] ?? '') ?></span>
                        <span class="badge badge-<?= $budget['status'] === 'Active' ? 'success' : 'warning' ?> small ml-1">
                            <?= htmlspecialchars($budget['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-grid">
                    <div><span class="lbl">Nama:</span> <span class="val"><?= htmlspecialchars($budget['budget_name']) ?></span></div>
                    <div><span class="lbl">Pembuat:</span> <span class="val"><?= htmlspecialchars($budget['nama_pembuat'] ?? '-') ?></span></div>
                    <div><span class="lbl">Periode:</span> <span class="val"><?= $bulan_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></span></div>
                    <div><span class="lbl">Divisi:</span> <span class="val"><?= htmlspecialchars($budget['nama_divisi'] ?? '-') ?></span></div>
                    <?php if ($budget['notes']): ?>
                    <div style="grid-column:1/-1;"><span class="lbl">Catatan:</span> <span class="val"><?= htmlspecialchars($budget['notes']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Totals bar -->
            <div class="totals-bar">
                <div>
                    <div class="label-sm">Total Alokasi Anggaran</div>
                    <strong class="text-primary">Rp <?= number_format($total_nominal_ajuan, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <div class="label-sm">Total Nominal Realisasi</div>
                    <strong class="text-success">Rp <?= number_format($total_nominal_real, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <div class="label-sm">Selisih (Alokasi − Realisasi)</div>
                    <strong class="<?= $selisih >= 0 ? 'selisih-minus' : 'selisih-plus' ?>">
                        <?= $selisih >= 0 ? '−' : '+' ?> Rp <?= number_format(abs($selisih), 0, ',', '.') ?>
                    </strong>
                </div>
                <div>
                    <div class="label-sm">Jumlah Item</div>
                    <strong><?= count($items) ?> item</strong>
                </div>
            </div>

            <h5 class="mb-3">
                <i class="fas fa-list-alt text-primary"></i> Daftar Rincian Item Anggaran
            </h5>

            <?php if (empty($items)): ?>
                <div class="card"><div class="card-body text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Tidak ada item rincian dalam anggaran ini.</p>
                </div></div>
            <?php else: ?>
                <?php foreach ($per_kategori as $cat_name => $cat_items): ?>
                <div class="category-block">
                    <div class="category-head">
                        <span class="text-secondary"><i class="fas fa-tag mr-1 text-primary"></i><?= htmlspecialchars($cat_name) ?></span>
                        <span class="small font-weight-bold">
                            Total Alokasi: Rp <?= number_format(array_sum(array_column($cat_items, 'total')), 0, ',', '.') ?>
                        </span>
                    </div>
                    <div class="p-3 bg-white">
                    <?php foreach ($cat_items as $it): 
                        $det = $real_details[$it['id_detail']] ?? null;
                        $status_rl = $det['status_realisasi'] ?? null;
                        
                        $meta_parts = [];
                        if ($it['qty'])          $meta_parts[] = $it['qty'] . ' ' . $it['satuan'];
                        if ($it['jumlah_hari'])  $meta_parts[] = $it['jumlah_hari'] . ' hari';
                        if ($it['jumlah_minggu'])$meta_parts[] = $it['jumlah_minggu'] . ' minggu';
                        if ($it['harga_satuan']) $meta_parts[] = 'Rp ' . number_format($it['harga_satuan'], 0, ',', '.');
                    
                    $can_input = in_array($status_rl, [null, 'Draft', 'Ditolak']);
                ?>
                <div class="item-card">
                    <div class="item-head">
                        <div>
                            <div class="item-nama"><?= htmlspecialchars($it['rincian']) ?></div>
                            <div class="item-meta mt-1"><?= implode(' &nbsp;·&nbsp; ', $meta_parts) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="label-sm mb-0">Alokasi Anggaran</div>
                            <div class="font-weight-bold text-primary" style="font-size:15px;">
                                Rp <?= number_format($it['total'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="item-body">
                        <!-- Status indicator -->
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                            <div>
                                <?php if ($status_rl === null): ?>
                                    <span class="badge-status bs-new"><i class="fas fa-pencil-alt"></i> Belum ada realisasi</span>
                                <?php elseif ($status_rl === 'Draft'): ?>
                                    <span class="badge-status bs-draft"><i class="fas fa-edit"></i> Draft tersimpan</span>
                                <?php elseif ($status_rl === 'Menunggu Approval'): ?>
                                    <span class="badge-status bs-wait"><i class="fas fa-clock"></i> Menunggu Persetujuan Asmen</span>
                                <?php elseif ($status_rl === 'Disetujui'): ?>
                                    <span class="badge-status bs-ok"><i class="fas fa-check-double"></i> Realisasi Disetujui</span>
                                <?php elseif ($status_rl === 'Ditolak'): ?>
                                    <span class="badge-status bs-rej"><i class="fas fa-times-circle"></i> Ditolak (Perlu Revisi)</span>
                                    <p class="text-danger small mt-1 mb-0"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($det['catatan_approval']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($det && $det['no_realisasi']): ?>
                                    <span class="small font-weight-bold ml-2 text-muted">#<?= $det['no_realisasi'] ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($can_input): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('formBox_<?= $it['id_detail'] ?>').style.display='block';">
                                    <i class="fas fa-plus"></i> <?= $status_rl ? 'Edit Realisasi' : 'Input Realisasi' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Form box per item -->
                        <?php if ($can_input): ?>
                        <div class="realisasi-form-box" id="formBox_<?= $it['id_detail'] ?>">
                            <form action="save_realisasi.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id_budget" value="<?= $id_budget ?>">
                                <input type="hidden" name="id_detail" value="<?= $it['id_detail'] ?>">
                                <?php if ($det): ?>
                                <input type="hidden" name="id_realisasi" value="<?= $det['id_realisasi'] ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="small font-weight-bold mb-1">Nominal Aktual <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                            <input type="text" name="nominal_realisasi" class="form-control rupiah-input" required
                                                value="<?= isset($det['nominal_realisasi']) ? number_format($det['nominal_realisasi'], 0, ',', '.') : '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold mb-1">Uang Kembali</label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                            <input type="text" name="uang_kembali" class="form-control rupiah-input"
                                                value="<?= isset($det['uang_kembali']) ? number_format($det['uang_kembali'], 0, ',', '.') : '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-5 mb-2">
                                        <label class="small font-weight-bold mb-1">Catatan Tambahan</label>
                                        <input type="text" name="catatan" class="form-control form-control-sm" maxlength="300" 
                                            value="<?= htmlspecialchars($det['catatan'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 mt-1">
                                        <label class="small font-weight-bold mb-1">
                                            Bukti Pendukung (Nota / Transfer) 
                                            <?php if (!$det): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        <input type="file" name="bukti[]" class="form-control form-control-sm bukti-input" data-idx="<?= $it['id_detail'] ?>" accept=".jpg,.jpeg,.png,.pdf" multiple <?= (!$det) ? 'required' : '' ?>>
                                        <small class="text-muted" style="font-size:10.5px;">Max 5 file × 5MB. Jika ingin mengganti file lama, upload yang baru.</small>
                                        <div class="upload-preview" id="prev_<?= $it['id_detail'] ?>">
                                            <?php if ($det && $det['bukti_files']): ?>
                                                <?php foreach (explode(',', $det['bukti_files']) as $bf):
                                                    $bf = trim($bf); if (!$bf) continue;
                                                    $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                                    $ic = in_array($ext,['jpg','jpeg','png']) ? 'fa-image' : 'fa-file-pdf'; ?>
                                                <a href="../uploads/realisasi/<?= htmlspecialchars($bf) ?>" target="_blank" class="bukti-link">
                                                   <i class="fas <?= $ic ?>"></i> <?= htmlspecialchars($bf) ?>
                                                </a>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-3 d-flex justify-content-end" style="gap:8px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('formBox_<?= $it['id_detail'] ?>').style.display='none';">Batal</button>
                                        <button type="submit" name="action" value="draft" class="btn btn-sm btn-info text-white"><i class="fas fa-save"></i> Simpan Draft</button>
                                        <button type="submit" name="action" value="submit" class="btn btn-sm btn-success" onclick="return confirm('Kirim realisasi item ini ke Asmen?');"><i class="fa-solid fa-paper-plane"></i> Kirim ke Asmen</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- View Data Box jika sudah ada data -->
                        <?php if ($det): ?>
                        <div class="realisasi-data-box" <?= ($can_input && false) ? 'style="display:none;"' : '' ?>>
                            <div class="row">
                                <div class="col-md-3">
                                    <span class="lbl"><i class="fas fa-money-check-alt text-success"></i> Nominal Realisasi </span><br>
                                    <strong class="text-success" style="font-size:15px;">Rp <?= number_format($det['nominal_realisasi'], 0, ',', '.') ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <span class="lbl">Uang Kembali</span><br>
                                    <strong>Rp <?= number_format($det['uang_kembali'] ?? 0, 0, ',', '.') ?></strong>
                                </div>
                                <?php if ($det['catatan']): ?>
                                <div class="col-md-6">
                                    <span class="lbl">Catatan</span><br>
                                    <strong><?= htmlspecialchars($det['catatan']) ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($det['bukti_files']): ?>
                                <div class="col-12 mt-2 pt-2" style="border-top:1px dashed #bbf7d0;">
                                    <span class="lbl mb-1 d-block"><i class="fas fa-paperclip"></i> Bukti Lampiran:</span>
                                    <div>
                                        <?php foreach (explode(',', $det['bukti_files']) as $bf):
                                            $bf = trim($bf); if (!$bf) continue;
                                            $ext = strtolower(pathinfo($bf, PATHINFO_EXTENSION));
                                            $ic = in_array($ext,['jpg','jpeg','png']) ? 'fa-image' : 'fa-file-pdf'; ?>
                                        <a href="../uploads/realisasi/<?= htmlspecialchars($bf) ?>" target="_blank" class="bukti-link bg-white">
                                           <i class="fas <?= $ic ?>"></i> <?= htmlspecialchars($bf) ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div> <!-- item-card -->
                <?php endforeach; ?>
                </div> <!-- category body -->
                </div> <!-- category-block -->
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
// Preview file upload
document.querySelectorAll('.bukti-input').forEach(function(inp) {
    inp.addEventListener('change', function() {
        var idx = this.dataset.idx;
        var prev = document.getElementById('prev_' + idx);
        if (!prev) return;
        prev.querySelectorAll('.new-prev').forEach(function(el){ el.remove(); });
        var maxSize = 5 * 1024 * 1024;
        if (this.files.length > 5) { alert('Maksimal 5 file!'); this.value=''; return; }
        Array.from(this.files).forEach(function(file) {
            if (file.size > maxSize) { alert(file.name + ' terlalu besar (maks 5MB)!'); return; }
            var ext = file.name.split('.').pop().toLowerCase();
            var icon = ['jpg','jpeg','png'].includes(ext) ? 'fa-image' : 'fa-file-pdf';
            var el = document.createElement('span');
            el.className = 'prev-item new-prev';
            el.innerHTML = '<i class="fas ' + icon + '"></i> ' + file.name + ' (' + (file.size/1024).toFixed(1) + 'KB)';
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

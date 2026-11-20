<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_akun', 'read');

init_acc_fase3_db($conn);

$filter_kat = trim($_GET['kategori'] ?? '');
$filter_tipe = trim($_GET['tipe_saldo'] ?? '');
$filter_search = trim($_GET['q'] ?? '');

$where_clauses = ["1=1"];
if (!empty($filter_kat)) {
    $where_clauses[] = "kategori_akun = '" . mysqli_real_escape_string($conn, $filter_kat) . "'";
}
if (!empty($filter_tipe)) {
    $where_clauses[] = "tipe_saldo = '" . mysqli_real_escape_string($conn, $filter_tipe) . "'";
}
if (!empty($filter_search)) {
    $q_esc = mysqli_real_escape_string($conn, $filter_search);
    $where_clauses[] = "(kode_akun LIKE '%$q_esc%' OR nama_akun LIKE '%$q_esc%' OR keterangan LIKE '%$q_esc%')";
}
$where_sql = implode(' AND ', $where_clauses);

$list_coa_q = mysqli_query($conn, "SELECT * FROM acc_coa WHERE $where_sql ORDER BY kode_akun ASC");
$all_coa = [];
if ($list_coa_q) {
    while ($r = mysqli_fetch_assoc($list_coa_q)) {
        $all_coa[] = $r;
    }
}

// Statistik akun
$stat_total = count($all_coa);
$q_stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total_all,
    SUM(CASE WHEN kategori_akun IN ('Aset Lancar', 'Aset Tetap') THEN 1 ELSE 0 END) as cnt_aset,
    SUM(CASE WHEN kategori_akun IN ('Liabilitas Lancar', 'Liabilitas Jangka Panjang', 'Ekuitas') THEN 1 ELSE 0 END) as cnt_liab,
    SUM(CASE WHEN kategori_akun IN ('Beban Operasional', 'Beban Non-Operasional') THEN 1 ELSE 0 END) as cnt_beban,
    SUM(CASE WHEN kategori_akun = 'Pendapatan' THEN 1 ELSE 0 END) as cnt_pend
FROM acc_coa");
$stats = mysqli_fetch_assoc($q_stats);

// Dropdown Parent Akun untuk modal
$parents_q = mysqli_query($conn, "SELECT kode_akun, nama_akun, level FROM acc_coa WHERE is_header = 1 OR level < 4 ORDER BY kode_akun ASC");
$parent_options = [];
if ($parents_q) {
    while ($p = mysqli_fetch_assoc($parents_q)) $parent_options[] = $p;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master COA (Chart of Accounts) – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; padding: 18px 20px; transition: transform .15s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.06); }
        .stat-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: .5px; }
        .stat-val { font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        
        .coa-table th { background: #f8fafc; font-size: 13px; font-weight: 700; color: #334155; border-bottom: 2px solid #e2e8f0; }
        .coa-table td { font-size: 13.5px; vertical-align: middle; }
        .level-1 { background: #f1f5f9; font-weight: 800; color: #0f172a; }
        .level-2 { background: #f8fafc; font-weight: 700; color: #1e293b; }
        .level-3 { font-weight: 600; color: #334155; }
        .level-4 { font-weight: 400; color: #475569; }
        .indent-1 { padding-left: 12px !important; }
        .indent-2 { padding-left: 28px !important; }
        .indent-3 { padding-left: 44px !important; }
        .indent-4 { padding-left: 60px !important; }

        .badge-debit { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; }
        .badge-kredit { background-color: #fef3c7; color: #b45309; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; }
        .badge-header { background-color: #ede9fe; color: #6d28d9; font-weight: 700; font-size: 11px; padding: 3px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>

    <div class="page-wrapper">
        <div class="content container-fluid">
            <!-- Breadcrumb -->
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-sitemap text-primary mr-2"></i> Master Bagan Akun (Chart of Accounts - COA)
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Akuntansi</li>
                            <li class="breadcrumb-item active">Master COA</li>
                        </ul>
                    </div>
                    <div class="col-sm-4 text-right">
                        <?php if ($auth->hasPermission('master_akun', 'create')): ?>
                        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" onclick="openModalTambah()">
                            <i class="fas fa-plus mr-1"></i> Tambah Akun Baru
                        </button>
                        <?php endif; ?>
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
                        <div class="stat-label"><i class="fas fa-layer-group mr-1 text-primary"></i> Total Akun COA</div>
                        <div class="stat-val"><?= number_format($stats['total_all'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-wallet mr-1 text-info"></i> Aset / Aktiva</div>
                        <div class="stat-val text-info"><?= number_format($stats['cnt_aset'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-hand-holding-usd mr-1 text-warning"></i> Liabilitas & Ekuitas</div>
                        <div class="stat-val text-warning"><?= number_format($stats['cnt_liab'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-receipt mr-1 text-danger"></i> Beban Operasional</div>
                        <div class="stat-val text-danger"><?= number_format($stats['cnt_beban'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Card -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                <div class="card-body py-3">
                    <form method="GET" action="index.php" class="row align-items-center">
                        <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Filter Kategori</label>
                            <select name="kategori" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">-- Semua Kategori Akun --</option>
                                <?php
                                $kats = ['Aset Lancar', 'Aset Tetap', 'Liabilitas Lancar', 'Liabilitas Jangka Panjang', 'Ekuitas', 'Pendapatan', 'Beban Operasional', 'Beban Non-Operasional'];
                                foreach ($kats as $k) {
                                    $sel = ($filter_kat === $k) ? 'selected' : '';
                                    echo "<option value='$k' $sel>$k</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Tipe Saldo Normal</label>
                            <select name="tipe_saldo" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">-- Semua Saldo --</option>
                                <option value="Debit" <?= ($filter_tipe === 'Debit') ? 'selected' : '' ?>>Debit</option>
                                <option value="Kredit" <?= ($filter_tipe === 'Kredit') ? 'selected' : '' ?>>Kredit</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-10 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Cari Kode / Nama Akun</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="q" class="form-control" placeholder="mis. 1101, BBM, Kas..." value="<?= htmlspecialchars($filter_search) ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1 col-sm-2 text-right">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <a href="index.php" class="btn btn-sm btn-light border" title="Reset Filter"><i class="fas fa-undo"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm" style="border-radius:10px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="card-title font-weight-bold mb-0 text-dark">
                        Daftar Bagan Akun Standar (Total: <?= count($all_coa) ?> Akun)
                    </h5>
                    <span class="badge badge-light border text-muted">Hierarki Level 1 - 4</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 coa-table">
                            <thead>
                                <tr>
                                    <th width="18%">Kode Akun</th>
                                    <th width="32%">Nama Akun</th>
                                    <th width="18%">Kategori Akun</th>
                                    <th width="10%" class="text-center">Saldo Normal</th>
                                    <th width="8%" class="text-center">Tipe</th>
                                    <th width="6%" class="text-center">Status</th>
                                    <th width="8%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_coa)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                            Tidak ada data akun COA yang cocok dengan filter.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_coa as $c): 
                                        $lvl = (int)$c['level'];
                                        $indent_cls = "indent-" . min(4, max(1, $lvl));
                                        $row_cls = "level-" . min(4, max(1, $lvl));
                                    ?>
                                    <tr class="<?= $row_cls ?>">
                                        <td class="<?= $indent_cls ?> font-weight-bold">
                                            <?php if ($lvl === 1): ?>
                                                <i class="fas fa-folder text-warning mr-1"></i>
                                            <?php elseif ($lvl === 2): ?>
                                                <i class="fas fa-folder-minus text-secondary mr-1"></i>
                                            <?php elseif ($lvl === 3): ?>
                                                <i class="fas fa-file-invoice text-info mr-1"></i>
                                            <?php else: ?>
                                                <i class="far fa-circle text-muted mr-1" style="font-size: 8px;"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($c['kode_akun']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($c['nama_akun']) ?>
                                            <?php if (!empty($c['keterangan'])): ?>
                                                <br><small class="text-muted font-italic"><?= htmlspecialchars($c['keterangan']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-secondary small font-weight-bold"><?= htmlspecialchars($c['kategori_akun']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($c['tipe_saldo'] === 'Debit'): ?>
                                                <span class="badge-debit">Debit</span>
                                            <?php else: ?>
                                                <span class="badge-kredit">Kredit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($c['is_header']): ?>
                                                <span class="badge-header">Header</span>
                                            <?php else: ?>
                                                <span class="badge badge-light border text-muted">Postable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($c['status'] === 'Aktif'): ?>
                                                <a href="action.php?action=toggle_status&id=<?= $c['id_coa'] ?>" class="badge badge-success text-white" title="Klik untuk Nonaktifkan">Aktif</a>
                                            <?php else: ?>
                                                <a href="action.php?action=toggle_status&id=<?= $c['id_coa'] ?>" class="badge badge-secondary text-white" title="Klik untuk Aktifkan">Nonaktif</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border py-0 px-2" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right shadow-sm">
                                                    <?php if ($auth->hasPermission('master_akun', 'update')): ?>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick='openModalEdit(<?= json_encode($c) ?>)'>
                                                        <i class="fas fa-edit text-warning mr-2"></i> Edit Akun
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($c['is_header']): ?>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="tambahSubAkun('<?= htmlspecialchars($c['kode_akun']) ?>', '<?= htmlspecialchars($c['kategori_akun']) ?>')">
                                                        <i class="fas fa-plus text-primary mr-2"></i> Tambah Sub-Akun
                                                    </a>
                                                    <?php endif; ?>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if ($auth->hasPermission('master_akun', 'delete')): ?>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?= $c['id_coa'] ?>, '<?= htmlspecialchars($c['kode_akun']) ?> - <?= htmlspecialchars($c['nama_akun']) ?>')">
                                                        <i class="fas fa-trash-alt mr-2"></i> Hapus Akun
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Modal Tambah / Edit COA -->
<div class="modal fade" id="modalCOA" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="action.php" method="POST" class="modal-content" style="border-radius:10px; overflow:hidden;">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id_coa" id="coaId" value="0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalCOATitle">
                    <i class="fas fa-plus-circle mr-1"></i> Tambah Akun COA Baru
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Akun Induk / Parent</label>
                        <select name="parent_kode" id="coaParent" class="form-control select2-coa">
                            <option value="">-- Tanpa Parent (Header Klasifikasi Utama) --</option>
                            <?php foreach ($parent_options as $po): ?>
                                <option value="<?= htmlspecialchars($po['kode_akun']) ?>">
                                    [<?= htmlspecialchars($po['kode_akun']) ?>] <?= htmlspecialchars($po['nama_akun']) ?> (Lv <?= $po['level'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Kode Akun <span class="text-danger">*</span></label>
                        <input type="text" name="kode_akun" id="coaKode" class="form-control font-weight-bold" required placeholder="mis. 1101-11-01 atau 5313">
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold">Nama Akun <span class="text-danger">*</span></label>
                    <input type="text" name="nama_akun" id="coaNama" class="form-control" required placeholder="mis. Kas Operasional Gorontalo, Beban BBM, dll.">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Kategori Akun <span class="text-danger">*</span></label>
                        <select name="kategori_akun" id="coaKategori" class="form-control" required onchange="autoSelectSaldo()">
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Aset Lancar">Aset Lancar</option>
                            <option value="Aset Tetap">Aset Tetap</option>
                            <option value="Liabilitas Lancar">Liabilitas Lancar</option>
                            <option value="Liabilitas Jangka Panjang">Liabilitas Jangka Panjang</option>
                            <option value="Ekuitas">Ekuitas</option>
                            <option value="Pendapatan">Pendapatan</option>
                            <option value="Beban Operasional">Beban Operasional</option>
                            <option value="Beban Non-Operasional">Beban Non-Operasional</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Tipe Saldo Normal <span class="text-danger">*</span></label>
                        <select name="tipe_saldo" id="coaSaldo" class="form-control" required>
                            <option value="Debit">Debit (Aset, Beban)</option>
                            <option value="Kredit">Kredit (Liabilitas, Ekuitas, Pendapatan)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="coaIsHeader" name="is_header" value="1">
                            <label class="custom-control-label font-weight-bold" for="coaIsHeader">
                                Jadikan Akun Header (Hanya untuk grup, tidak bisa diposting transaksi)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Status</label>
                        <select name="status" id="coaStatus" class="form-control">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Keterangan / Catatan Tambahan</label>
                    <textarea name="keterangan" id="coaKeterangan" class="form-control" rows="2" placeholder="Catatan tambahan mengenai penggunaan akun ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Delete Confirmation -->
<div class="modal fade" id="modalDelCOA" tabindex="-1">
    <div class="modal-dialog">
        <form action="action.php" method="POST" class="modal-content" style="border-radius:10px; overflow:hidden;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_coa" id="delCoaId" value="0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-trash-alt mr-1"></i> Konfirmasi Hapus Akun COA</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Apakah Anda yakin ingin menghapus akun COA berikut?</p>
                <div class="alert alert-warning font-weight-bold mb-3" id="delCoaName"></div>
                <small class="text-muted d-block">
                    <i class="fas fa-info-circle mr-1"></i> Akun yang memiliki sub-akun (child) atau sudah pernah digunakan dalam jurnal akuntansi tidak dapat dihapus demi integritas pembukuan.
                </small>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger font-weight-bold"><i class="fas fa-trash-alt mr-1"></i> Hapus Permanen</button>
            </div>
        </form>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2-coa').select2({
            dropdownParent: $('#modalCOA'),
            width: '100%'
        });
    }
});

function autoSelectSaldo() {
    const kat = document.getElementById('coaKategori').value;
    const saldoSel = document.getElementById('coaSaldo');
    if (kat.includes('Liabilitas') || kat.includes('Ekuitas') || kat.includes('Pendapatan')) {
        saldoSel.value = 'Kredit';
    } else {
        saldoSel.value = 'Debit';
    }
}

function openModalTambah() {
    document.getElementById('modalCOATitle').innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Tambah Akun COA Baru';
    document.getElementById('coaId').value = '0';
    document.getElementById('coaKode').value = '';
    document.getElementById('coaNama').value = '';
    document.getElementById('coaKategori').value = '';
    document.getElementById('coaSaldo').value = 'Debit';
    document.getElementById('coaIsHeader').checked = false;
    document.getElementById('coaStatus').value = 'Aktif';
    document.getElementById('coaKeterangan').value = '';
    $('#coaParent').val('').trigger('change');
    $('#modalCOA').modal('show');
}

function openModalEdit(data) {
    document.getElementById('modalCOATitle').innerHTML = '<i class="fas fa-edit mr-1"></i> Edit Akun COA [' + data.kode_akun + ']';
    document.getElementById('coaId').value = data.id_coa;
    document.getElementById('coaKode').value = data.kode_akun;
    document.getElementById('coaNama').value = data.nama_akun;
    document.getElementById('coaKategori').value = data.kategori_akun;
    document.getElementById('coaSaldo').value = data.tipe_saldo;
    document.getElementById('coaIsHeader').checked = (data.is_header == 1);
    document.getElementById('coaStatus').value = data.status;
    document.getElementById('coaKeterangan').value = data.keterangan || '';
    $('#coaParent').val(data.parent_kode || '').trigger('change');
    $('#modalCOA').modal('show');
}

function tambahSubAkun(parentKode, kategori) {
    openModalTambah();
    $('#coaParent').val(parentKode).trigger('change');
    if (kategori) {
        document.getElementById('coaKategori').value = kategori;
        autoSelectSaldo();
    }
}

function confirmDelete(id, name) {
    document.getElementById('delCoaId').value = id;
    document.getElementById('delCoaName').textContent = name;
    $('#modalDelCOA').modal('show');
}
</script>
</body>
</html>

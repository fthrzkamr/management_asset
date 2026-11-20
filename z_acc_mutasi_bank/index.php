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

$user_id = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

// ── Ambil Rekening Bank ────────────────────────────────────────────────────
$rekening_list_q = mysqli_query($conn, "
    SELECT r.*, c.kode_akun, c.nama_akun
    FROM acc_master_rekening r
    LEFT JOIN acc_coa c ON r.id_coa = c.id_coa
    ORDER BY r.nama_bank ASC
");
$all_rekening = [];
$total_all_saldo = 0;
while ($r = mysqli_fetch_assoc($rekening_list_q)) {
    $all_rekening[] = $r;
    $total_all_saldo += (float)$r['saldo_saat_ini'];
}

$id_rekening_filter = isset($_GET['id_rekening']) ? (int)$_GET['id_rekening'] : 0;
$filter_jenis = trim($_GET['jenis'] ?? '');
$filter_start = trim($_GET['start_date'] ?? date('Y-m-01'));
$filter_end   = trim($_GET['end_date'] ?? date('Y-m-t'));

$where = ["1=1"];
if ($id_rekening_filter > 0) {
    $where[] = "m.id_rekening = $id_rekening_filter";
}
if (!empty($filter_jenis)) {
    $where[] = "m.jenis_mutasi = '" . mysqli_real_escape_string($conn, $filter_jenis) . "'";
}
if (!empty($filter_start)) {
    $where[] = "m.tanggal >= '" . mysqli_real_escape_string($conn, $filter_start) . "'";
}
if (!empty($filter_end)) {
    $where[] = "m.tanggal <= '" . mysqli_real_escape_string($conn, $filter_end) . "'";
}
$where_sql = implode(' AND ', $where);

// Ambil Riwayat Mutasi
$mutasi_q = mysqli_query($conn, "
    SELECT m.*, r.nama_bank, r.nomor_rekening, c.kode_akun AS kode_lawan, c.nama_akun AS nama_lawan,
           u.nama_user AS pembuat, jh.no_jurnal
    FROM acc_mutasi_bank m
    JOIN acc_master_rekening r ON m.id_rekening = r.id_rekening
    LEFT JOIN acc_coa c ON m.id_coa_lawan = c.id_coa
    LEFT JOIN user_dfm u ON m.created_by = u.id_user
    LEFT JOIN acc_jurnal_header jh ON m.id_jurnal = jh.id_jurnal
    WHERE $where_sql
    ORDER BY m.tanggal DESC, m.id_mutasi DESC
");

$mutasi_rows = [];
$total_masuk_periode = 0;
$total_keluar_periode = 0;
if ($mutasi_q) {
    while ($m = mysqli_fetch_assoc($mutasi_q)) {
        $mutasi_rows[] = $m;
        if ($m['jenis_mutasi'] === 'Masuk') {
            $total_masuk_periode += (float)$m['nominal'];
        } else {
            $total_keluar_periode += (float)$m['nominal'];
        }
    }
}

// COA untuk dropdown lawan (non-header)
$coa_lawan_q = mysqli_query($conn, "
    SELECT id_coa, kode_akun, nama_akun, kategori_akun
    FROM acc_coa
    WHERE status = 'Aktif' AND is_header = 0
    ORDER BY kode_akun ASC
");
$coa_lawan_list = [];
if ($coa_lawan_q) {
    while ($c = mysqli_fetch_assoc($coa_lawan_q)) $coa_lawan_list[] = $c;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mutasi Rekening Bank – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bank-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; padding: 18px 20px; transition: all .2s ease; cursor: pointer; text-decoration: none !important; display: block; color: inherit; }
        .bank-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.08); }
        .bank-card.active-bank { border: 2px solid #2563eb; background: #eff6ff; }
        .bank-name { font-size: 14px; font-weight: 700; color: #1e293b; }
        .bank-acc { font-size: 13px; color: #64748b; margin-top: 2px; }
        .bank-bal { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 8px; }
        
        .badge-masuk { background-color: #dcfce7; color: #15803d; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; }
        .badge-keluar { background-color: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; }
        .table-mutasi th { background: #f8fafc; font-size: 13px; font-weight: 700; color: #334155; }
        .table-mutasi td { font-size: 13.5px; vertical-align: middle; }
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
                    <div class="col-sm-7">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-university text-primary mr-2"></i> Mutasi Kas & Rekening Bank
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Akuntansi</li>
                            <li class="breadcrumb-item active">Mutasi Bank</li>
                        </ul>
                    </div>
                    <div class="col-sm-5 text-right">
                        <button type="button" class="btn btn-outline-secondary font-weight-bold mr-2 shadow-sm" onclick="openModalRekening()">
                            <i class="fas fa-cog mr-1"></i> Kelola Rekening
                        </button>
                        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" onclick="openModalMutasi()">
                            <i class="fas fa-plus mr-1"></i> Catat Mutasi Baru
                        </button>
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

            <!-- Rekening Cards Grid -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="index.php" class="bank-card <?= ($id_rekening_filter === 0) ? 'active-bank' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="bank-name"><i class="fas fa-vault mr-1 text-primary"></i> Semua Rekening</div>
                            <span class="badge badge-primary"><?= count($all_rekening) ?> Bank</span>
                        </div>
                        <div class="bank-acc">Total Likuiditas Bank</div>
                        <div class="bank-bal text-primary">Rp <?= number_format($total_all_saldo, 0, ',', '.') ?></div>
                    </a>
                </div>
                <?php foreach ($all_rekening as $rek): 
                    $is_sel = ($id_rekening_filter == $rek['id_rekening']);
                ?>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="index.php?id_rekening=<?= $rek['id_rekening'] ?>" class="bank-card <?= $is_sel ? 'active-bank' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="bank-name text-truncate" title="<?= htmlspecialchars($rek['nama_bank']) ?>">
                                <i class="fas fa-university text-info mr-1"></i> <?= htmlspecialchars($rek['nama_bank']) ?>
                            </div>
                            <?php if ($rek['status'] === 'Aktif'): ?>
                                <span class="badge badge-success" style="font-size: 10px;">Aktif</span>
                            <?php endif; ?>
                        </div>
                        <div class="bank-acc"><?= htmlspecialchars($rek['nomor_rekening']) ?> &bull; <?= htmlspecialchars($rek['atas_nama']) ?></div>
                        <div class="bank-bal">Rp <?= number_format((float)$rek['saldo_saat_ini'], 0, ',', '.') ?></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter & Periode Card -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                <div class="card-body py-3">
                    <form method="GET" action="index.php" class="row align-items-center">
                        <input type="hidden" name="id_rekening" value="<?= $id_rekening_filter ?>">
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Rentang Dari</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_start) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Sampai Dengan</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_end) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Jenis Mutasi</label>
                            <select name="jenis" class="form-control form-control-sm">
                                <option value="">-- Semua Mutasi --</option>
                                <option value="Masuk" <?= ($filter_jenis === 'Masuk') ? 'selected' : '' ?>>Mutasi Masuk (Kredit Bank)</option>
                                <option value="Keluar" <?= ($filter_jenis === 'Keluar') ? 'selected' : '' ?>>Mutasi Keluar (Debit Bank)</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 text-right">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary font-weight-bold"><i class="fas fa-filter mr-1"></i> Terapkan Filter</button>
                            <a href="index.php" class="btn btn-sm btn-light border" title="Reset"><i class="fas fa-undo"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Riwayat Mutasi -->
            <div class="card border-0 shadow-sm" style="border-radius:10px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="card-title font-weight-bold mb-0 text-dark">
                            Buku Mutasi Bank (<?= count($mutasi_rows) ?> Transaksi)
                        </h5>
                        <small class="text-muted">
                            Periode: <?= date('d M Y', strtotime($filter_start)) ?> s/d <?= date('d M Y', strtotime($filter_end)) ?>
                        </small>
                    </div>
                    <div class="d-flex" style="gap: 16px;">
                        <span class="badge badge-light border p-2 text-success" style="font-size: 13px;">
                            <i class="fas fa-arrow-down mr-1"></i> Masuk: Rp <?= number_format($total_masuk_periode, 0, ',', '.') ?>
                        </span>
                        <span class="badge badge-light border p-2 text-danger" style="font-size: 13px;">
                            <i class="fas fa-arrow-up mr-1"></i> Keluar: Rp <?= number_format($total_keluar_periode, 0, ',', '.') ?>
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-mutasi">
                            <thead>
                                <tr>
                                    <th width="14%">No Mutasi</th>
                                    <th width="10%">Tanggal</th>
                                    <th width="14%">Bank</th>
                                    <th width="8%" class="text-center">Jenis</th>
                                    <th width="14%">Kategori & Akun Lawan</th>
                                    <th width="20%">Keterangan</th>
                                    <th width="12%" class="text-right">Nominal</th>
                                    <th width="12%" class="text-right">Saldo Akhir</th>
                                    <th width="6%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mutasi_rows)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="fas fa-receipt fa-3x mb-3 text-secondary d-block"></i>
                                            Belum ada catatan mutasi kas / bank pada periode ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mutasi_rows as $m): ?>
                                    <tr>
                                        <td class="font-weight-bold text-dark">
                                            <?= htmlspecialchars($m['no_mutasi']) ?>
                                            <?php if (!empty($m['no_jurnal'])): ?>
                                                <br><a href="../z_acc_jurnal/index.php?q=<?= urlencode($m['no_jurnal']) ?>" class="badge badge-info" title="Lihat Jurnal"><i class="fas fa-book mr-1"></i><?= $m['no_jurnal'] ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($m['tanggal'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($m['nama_bank']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($m['nomor_rekening']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($m['jenis_mutasi'] === 'Masuk'): ?>
                                                <span class="badge-masuk"><i class="fas fa-arrow-down mr-1"></i> Masuk</span>
                                            <?php else: ?>
                                                <span class="badge-keluar"><i class="fas fa-arrow-up mr-1"></i> Keluar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-light border font-weight-bold"><?= htmlspecialchars($m['kategori_mutasi']) ?></span>
                                            <?php if (!empty($m['kode_lawan'])): ?>
                                                <br><small class="text-primary font-weight-bold">[<?= htmlspecialchars($m['kode_lawan']) ?>] <?= htmlspecialchars($m['nama_lawan']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($m['keterangan']) ?>
                                            <?php if (!empty($m['no_referensi'])): ?>
                                                <br><small class="text-muted"><i class="fas fa-tag mr-1"></i> Ref: <?= htmlspecialchars($m['no_referensi']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right font-weight-bold <?= ($m['jenis_mutasi'] === 'Masuk') ? 'text-success' : 'text-danger' ?>">
                                            <?= ($m['jenis_mutasi'] === 'Masuk') ? '+' : '-' ?> Rp <?= number_format((float)$m['nominal'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-right font-weight-bold text-dark">
                                            Rp <?= number_format((float)$m['saldo_setelahnya'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap: 4px;">
                                                <?php if (!empty($m['bukti_file'])): ?>
                                                    <a href="../uploads/mutasi_bank/<?= urlencode($m['bukti_file']) ?>" target="_blank" class="btn btn-sm btn-light border text-info" title="Lihat Bukti Transfer">
                                                        <i class="fas fa-paperclip"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($is_admin): ?>
                                                    <a href="action.php?action=delete_mutasi&id=<?= $m['id_mutasi'] ?>" class="btn btn-sm btn-light border text-danger" title="Batalkan Mutasi" onclick="return confirm('Apakah Anda yakin ingin membatalkan mutasi ini? Saldo bank akan dikembalikan.');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                <?php endif; ?>
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

<!-- Modal Catat Mutasi Baru -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="action.php" method="POST" enctype="multipart/form-data" class="modal-content" style="border-radius:10px; overflow:hidden;">
            <input type="hidden" name="action" value="save_mutasi">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-money-check-alt mr-1"></i> Catat Transaksi Mutasi Kas & Bank
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Pilih Rekening Bank <span class="text-danger">*</span></label>
                        <select name="id_rekening" class="form-control font-weight-bold" required>
                            <?php foreach ($all_rekening as $rk): ?>
                                <option value="<?= $rk['id_rekening'] ?>" <?= ($id_rekening_filter == $rk['id_rekening']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rk['nama_bank']) ?> - <?= htmlspecialchars($rk['nomor_rekening']) ?> (Saldo: Rp <?= number_format((float)$rk['saldo_saat_ini'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label class="small font-weight-bold">Jenis Mutasi <span class="text-danger">*</span></label>
                        <select name="jenis_mutasi" id="mutJenis" class="form-control font-weight-bold" required onchange="toggleMutasiColor()">
                            <option value="Masuk">Masuk (Penerimaan / Setor)</option>
                            <option value="Keluar">Keluar (Pengeluaran / Tarik)</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label class="small font-weight-bold">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Nominal (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Rp</span></div>
                            <input type="text" name="nominal" class="form-control font-weight-bold text-right" placeholder="0" oninput="formatRupiahInput(this)" required style="font-size: 16px;">
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Kategori Mutasi <span class="text-danger">*</span></label>
                        <select name="kategori_mutasi" class="form-control" required>
                            <option value="Setor Kas">Setor Kas Tunai ke Bank</option>
                            <option value="Tarik Kas Tunai">Tarik Kas Tunai dari Bank</option>
                            <option value="Transfer Masuk">Transfer Masuk dari Pusat / Pihak Ketiga</option>
                            <option value="Transfer Keluar">Transfer Keluar ke Cabang / Vendor</option>
                            <option value="Biaya Admin Bank">Biaya Administrasi Bank</option>
                            <option value="Bunga Bank">Pendapatan Bunga Bank</option>
                            <option value="Pembayaran Beban">Pembayaran Beban / Tagihan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="small font-weight-bold">Akun Lawan COA (Untuk Jurnal Otomatis)</label>
                    <select name="id_coa_lawan" id="selCoaLawan" class="form-control select2-mutasi" style="width: 100%;">
                        <option value="">-- Pilih Akun Lawan (Opsional) --</option>
                        <?php foreach ($coa_lawan_list as $cl): ?>
                            <option value="<?= $cl['id_coa'] ?>">
                                [<?= htmlspecialchars($cl['kode_akun']) ?>] <?= htmlspecialchars($cl['nama_akun']) ?> (<?= htmlspecialchars($cl['kategori_akun']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Jika diisi, sistem akan otomatis membukukan transaksi ini ke dalam Jurnal Umum (Double-Entry).</small>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Keterangan / Berita Transfer <span class="text-danger">*</span></label>
                        <input type="text" name="keterangan" class="form-control" required placeholder="mis. Top-up Kas Kecil Gorontalo, Biaya Admin Bulanan, dll.">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">No. Referensi / No. Transaksi Bank</label>
                        <input type="text" name="no_referensi" class="form-control" placeholder="mis. Ref 928371928, No Cek 01928">
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Upload Bukti Transfer / Rekening Koran (JPG, PNG, PDF)</label>
                    <input type="file" name="bukti_file" class="form-control-file border p-2 rounded" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan Mutasi Bank</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Kelola Rekening Bank -->
<div class="modal fade" id="modalRekening" tabindex="-1">
    <div class="modal-dialog">
        <form action="action.php" method="POST" class="modal-content" style="border-radius:10px; overflow:hidden;">
            <input type="hidden" name="action" value="save_rekening">
            <input type="hidden" name="id_rekening" id="rekId" value="0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title font-weight-bold" id="rekModalTitle"><i class="fas fa-university mr-1"></i> Tambah / Edit Rekening Bank</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="small font-weight-bold">Nama Bank <span class="text-danger">*</span></label>
                    <input type="text" name="nama_bank" id="rekNamaBank" class="form-control font-weight-bold" required placeholder="mis. Bank BCA, Bank Mandiri, BRI, dll.">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Nomor Rekening <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_rekening" id="rekNomor" class="form-control font-weight-bold" required placeholder="mis. 8410293847">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Atas Nama Rekening <span class="text-danger">*</span></label>
                    <input type="text" name="atas_nama" id="rekAtasNama" class="form-control" required placeholder="mis. PT DUA FARMA MAHAKARSA">
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Akun COA Bank Terkait</label>
                    <select name="id_coa" id="rekCoa" class="form-control">
                        <option value="">-- Hubungkan dengan Akun COA --</option>
                        <?php 
                        $bank_coa_q = mysqli_query($conn, "SELECT id_coa, kode_akun, nama_akun FROM acc_coa WHERE kode_akun LIKE '1102%' ORDER BY kode_akun ASC");
                        while ($bc = mysqli_fetch_assoc($bank_coa_q)) {
                            echo "<option value='{$bc['id_coa']}'>[{$bc['kode_akun']}] {$bc['nama_akun']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group" id="groupSaldoAwal">
                    <label class="small font-weight-bold">Saldo Awal (Rp)</label>
                    <input type="text" name="saldo_awal" id="rekSaldoAwal" class="form-control font-weight-bold text-right" placeholder="0" oninput="formatRupiahInput(this)">
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Status</label>
                    <select name="status" id="rekStatus" class="form-control">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan Rekening</button>
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
        $('.select2-mutasi').select2({
            dropdownParent: $('#modalMutasi'),
            width: '100%',
            placeholder: '-- Pilih Akun Lawan --',
            allowClear: true
        });
    }
});

function openModalMutasi() {
    $('#modalMutasi').modal('show');
}

function openModalRekening() {
    document.getElementById('rekId').value = '0';
    document.getElementById('rekNamaBank').value = '';
    document.getElementById('rekNomor').value = '';
    document.getElementById('rekAtasNama').value = '';
    document.getElementById('rekSaldoAwal').value = '0';
    document.getElementById('groupSaldoAwal').style.display = 'block';
    $('#modalRekening').modal('show');
}

function formatRupiahInput(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val === '') {
        input.value = '';
        return;
    }
    input.value = new Intl.NumberFormat('id-ID').format(val);
}
</script>
</body>
</html>

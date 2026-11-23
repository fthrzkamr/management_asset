<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

init_ppx_fase2_db($conn);

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$layout_dir = realpath(__DIR__ . '/../layouts');

// Ambil list cabang asset untuk dropdown mapping
$q_asset = mysqli_query($conn, "SELECT id_cabang, nama_cabang FROM master_cabang ORDER BY id_cabang ASC");
$asset_cabangs = [];
if ($q_asset) {
    while ($r = mysqli_fetch_assoc($q_asset)) $asset_cabangs[] = $r;
}

// Ambil semua cabang PPX
$q_ppx = mysqli_query($conn, "
    SELECT pmc.*, mc.nama_cabang AS nama_cabang_asset
    FROM ppx_master_cabang pmc
    LEFT JOIN master_cabang mc ON pmc.id_cabang_asset = mc.id_cabang
    ORDER BY pmc.status ASC, pmc.kode_cabang ASC
");
$ppx_cabangs = [];
if ($q_ppx) {
    while ($r = mysqli_fetch_assoc($q_ppx)) $ppx_cabangs[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Lokasi & Cabang Kas (Accounting) – DFM Asset</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <style>
        .badge-code {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            background: #e0e7ff;
            color: #3730a3;
            letter-spacing: 0.5px;
        }
        .tbl-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <!-- Breadcrumb & Header -->
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1 font-weight-bold">
                            <i class="fas fa-map-marker-alt text-primary mr-2"></i>Master Lokasi & Cabang Kas
                        </h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../z_acc_coa/index.php">Akuntansi & Keuangan</a></li>
                            <li class="breadcrumb-item active">Master Lokasi & Cabang Kas</li>
                        </ul>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalAddCabang">
                            <i class="fas fa-plus mr-1"></i> Tambah Lokasi / Cabang
                        </button>
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

            <!-- Penjelasan Singkat -->
            <div class="alert alert-info d-flex align-items-center mb-4" style="border-left: 4px solid #0284c7; background: #f0f9ff;">
                <i class="fas fa-info-circle fa-xl mr-3 text-info"></i>
                <div style="font-size: 13px;">
                    <strong>Tentang Master Lokasi & Cabang Kas (Accounting & PPX):</strong><br>
                    Master Lokasi & Cabang ini digunakan sebagai referensi kode lokasi kas untuk penomoran resmi Bukti Kas Keluar (BKK) dan Bukti Kas Masuk (BKM)
                    dengan format baku: <code>BKK/KODE-DD-MM/YYYY/MM/URUT</code> (misal: <code>BKK/JKT-21-01/2027/01/0002</code>), serta pemetaan alokasi anggaran kas per cabang.
                </div>
            </div>

            <!-- Tabel Data Cabang -->
            <div class="tbl-card">
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tableCabang">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode Cabang</th>
                                <th width="25%">Nama Cabang PPX</th>
                                <th width="20%">Mapping Cabang Asset</th>
                                <th width="12%">Status</th>
                                <th width="13%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ppx_cabangs as $idx => $c): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge-code"><?= htmlspecialchars($c['kode_cabang']) ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($c['nama_cabang']) ?></strong></td>
                                <td>
                                    <?php if ($c['id_cabang_asset']): ?>
                                        <span class="text-dark font-weight-bold"><?= htmlspecialchars($c['id_cabang_asset']) ?></span>
                                        <small class="text-muted d-block"><?= htmlspecialchars($c['nama_cabang_asset'] ?: '-') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Belum dimapping</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['status'] === 'Aktif'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 px-2"
                                            onclick='editCabang(<?= json_encode($c) ?>)' title="Edit Cabang">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="action.php" method="POST" style="display:inline;" data-confirm="Ubah status cabang ini?">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id_cabang_ppx" value="<?= $c['id_cabang_ppx'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $c['status'] === 'Aktif' ? 'btn-outline-warning' : 'btn-outline-success' ?> p-1 px-2"
                                                title="<?= $c['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas <?= $c['status'] === 'Aktif' ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Tambah Cabang -->
<div class="modal fade" id="modalAddCabang" tabindex="-1">
    <div class="modal-dialog">
        <form action="action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-plus-circle text-primary mr-1"></i> Tambah Lokasi / Cabang Kas</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Kode Lokasi / Cabang (3-5 Huruf Singkatan) <span class="text-danger">*</span></label>
                    <input type="text" name="kode_cabang" class="form-control text-uppercase font-weight-bold" maxlength="10" placeholder="Contoh: JKT, GOR, SBY" required>
                    <small class="text-muted">Digunakan sebagai kode penomoran BKK/BKM.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Nama Lokasi / Cabang Kas <span class="text-danger">*</span></label>
                    <input type="text" name="nama_cabang" class="form-control" placeholder="Contoh: Gorontalo" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Mapping ke Master Cabang Asset</label>
                    <select name="id_cabang_asset" class="form-control">
                        <option value="">-- Pilih Cabang Asset (Opsional) --</option>
                        <?php foreach ($asset_cabangs as $ac): ?>
                            <option value="<?= htmlspecialchars($ac['id_cabang']) ?>">
                                <?= htmlspecialchars($ac['id_cabang']) ?> - <?= htmlspecialchars($ac['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Untuk otomatis menghubungkan user cabang ke kode nomor BKK/BKM ini.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Status</label>
                    <select name="status" class="form-control">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Cabang</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Cabang -->
<div class="modal fade" id="modalEditCabang" tabindex="-1">
    <div class="modal-dialog">
        <form action="action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_cabang_ppx" id="edit_id_cabang_ppx">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit text-info mr-1"></i> Edit Lokasi / Cabang Kas</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Kode Lokasi / Cabang <span class="text-danger">*</span></label>
                    <input type="text" name="kode_cabang" id="edit_kode_cabang" class="form-control text-uppercase font-weight-bold" maxlength="10" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Nama Lokasi / Cabang Kas <span class="text-danger">*</span></label>
                    <input type="text" name="nama_cabang" id="edit_nama_cabang" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Mapping ke Master Cabang Asset</label>
                    <select name="id_cabang_asset" id="edit_id_cabang_asset" class="form-control">
                        <option value="">-- Pilih Cabang Asset (Opsional) --</option>
                        <?php foreach ($asset_cabangs as $ac): ?>
                            <option value="<?= htmlspecialchars($ac['id_cabang']) ?>">
                                <?= htmlspecialchars($ac['id_cabang']) ?> - <?= htmlspecialchars($ac['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tableCabang').DataTable({
        language: {
            search: "Cari Cabang:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ cabang",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: { previous: "Sebelumnya", next: "Berikutnya" }
        }
    });
});

function editCabang(data) {
    $('#edit_id_cabang_ppx').val(data.id_cabang_ppx);
    $('#edit_kode_cabang').val(data.kode_cabang);
    $('#edit_nama_cabang').val(data.nama_cabang);
    $('#edit_id_cabang_asset').val(data.id_cabang_asset || '');
    $('#edit_status').val(data.status);
    $('#modalEditCabang').modal('show');
}
</script>
</body>
</html>

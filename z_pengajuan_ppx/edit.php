<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Ambil no_ppx dari query string
$no_ppx = isset($_GET['no_ppx']) ? mysqli_real_escape_string($conn, $_GET['no_ppx']) : '';

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan.';
    header("Location: dashboard.php");
    exit();
}

// Ambil data PPX
$q = "SELECT pr.*, u.nama_user as nama_pengaju
      FROM ppx_request pr
      LEFT JOIN user_dfm u ON pr.user_id = u.id_user
      WHERE pr.no_ppx = '$no_ppx' LIMIT 1";
$res = mysqli_query($conn, $q);
if (!$res || mysqli_num_rows($res) == 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan.';
    header("Location: dashboard.php");
    exit();
}
$ppx = mysqli_fetch_assoc($res);

// Ambil detail items
$qdet = "SELECT * FROM ppx_request_detail WHERE no_ppx = '$no_ppx' ORDER BY no_ppx_detail";
$rdet = mysqli_query($conn, $qdet);
$details = [];
while ($d = mysqli_fetch_assoc($rdet)) $details[] = $d;

// Permission: pakai hasPermission
// Izinkan edit juga jika status = 'Revisi'
$nonEditableStatuses = ['Done','Finance','Direktur','Manager','Leader'];
$canUpdate = $auth->hasPermission('ppx', 'update');
$canCreate = $auth->hasPermission('ppx', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit pengajuan ini.';
    header("Location: dashboard.php");
    exit();
}

// User hanya punya create: hanya bisa edit milik sendiri
if ($canCreate && !$canUpdate) {
    if ($ppx['user_id'] != $_SESSION['id_user'] && $ppx['status'] !== 'Revisi') {
        $_SESSION['error'] = 'Anda tidak dapat mengedit pengajuan ini.';
        header("Location: dashboard.php");
        exit();
    }
}

// Cek status non-editable
if (in_array($ppx['status'], $nonEditableStatuses) && $ppx['status'] !== 'Revisi' && !$canUpdate) {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit.';
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit PPX - <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .required { color: #dc3545; }
        .detail-item { background:#f8f9fa;border:1px solid #dee2e6;border-radius:5px;padding:12px;margin-bottom:12px;position:relative; }
        .btn-remove-item { position:absolute; top:8px; right:8px; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Revisi Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan PPX</a></li>
                                <li class="breadcrumb-item active">Edit</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <form action="update.php" method="POST" enctype="multipart/form-data" id="editForm">
                    <input type="hidden" name="no_ppx" value="<?= htmlspecialchars($ppx['no_ppx']) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$ppx['user_id'] ?>">

                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            Informasi Pengaju
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Nama PIC <span class="required">*</span></label>
                                    <input type="text" name="nama_pic" class="form-control" value="<?= htmlspecialchars($ppx['nama_pic']) ?>" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Jabatan <span class="required">*</span></label>
                                    <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($ppx['jabatan']) ?>" readonly>
                                    <small class="text-muted">Jabatan tidak dapat diubah</small>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Divisi <span class="required">*</span></label>
                                    <input type="text" name="divisi" class="form-control" value="<?= htmlspecialchars($ppx['divisi']) ?>" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Cabang <span class="required">*</span></label>
                                    <input type="text" name="cabang" class="form-control" value="<?= htmlspecialchars($ppx['cabang']) ?>" required>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Nama Bank</label>
                                    <input type="text" name="nama_bank" class="form-control" value="<?= htmlspecialchars($ppx['nama_bank'] ?? '') ?>" placeholder="Contoh: BCA, Mandiri">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Nama Rekening</label>
                                    <input type="text" name="nama_rek" class="form-control" value="<?= htmlspecialchars($ppx['nama_rek'] ?? '') ?>" placeholder="Nama pemilik rekening">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">No. Rekening</label>
                                    <input type="text" name="no_rek" class="form-control" value="<?= htmlspecialchars($ppx['no_rek'] ?? '') ?>" placeholder="Nomor rekening">
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Tanggal Pengajuan</label>
                                    <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= date('Y-m-d', strtotime($ppx['tanggal_pengajuan'])) ?>" readonly>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="font-weight-bold">Tanggal Dibutuhkan <span class="required">*</span></label>
                                    <input type="date" name="tanggal_dibutuhkan" id="tanggal_dibutuhkan" class="form-control" value="<?= date('Y-m-d', strtotime($ppx['tanggal_dibutuhkan'])) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Items -->
                    <div class="card mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <span>Detail Item PPX</span>
                            <button type="button" class="btn btn-sm btn-success" onclick="addDetailItem()"><i class="fas fa-plus"></i> Tambah Item</button>
                        </div>
                        <div class="card-body" id="detailContainer">
                            <?php
                            $idx = 0;
                            if (count($details) > 0):
                                foreach ($details as $d):
                            ?>
                                <div class="detail-item" data-index="<?= $idx ?>">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeDetailItem(<?= $idx ?>)"><i class="fas fa-times"></i></button>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label>No. Akun <span class="required">*</span></label>
                                            <input type="text" name="detail[<?= $idx ?>][no_akun]" class="form-control" value="<?= htmlspecialchars($d['no_akun']) ?>" required>
                                        </div>
                                        <div class="col-md-8 mb-2">
                                            <label>Rincian Item <span class="required">*</span></label>
                                            <input type="text" name="detail[<?= $idx ?>][rincian]" class="form-control" value="<?= htmlspecialchars($d['rincian']) ?>" required>
                                            <?php if (!empty($d['deskripsi'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($d['deskripsi']) ?></small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-3 mb-2">
                                            <label>Harga Satuan <span class="required">*</span></label>
                                            <input type="text" name="detail[<?= $idx ?>][harga_satuan]" class="form-control harga-input" value="Rp <?= number_format($d['harga_satuan'],0,',','.') ?>" onkeyup="formatRupiah(this); calculateTotal(<?= $idx ?>)" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label>Total Harga</label>
                                            <input type="text" name="detail[<?= $idx ?>][total_harga]" class="form-control total-harga" value="Rp <?= number_format($d['total_harga'],0,',','.') ?>" readonly>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label>Jumlah & Satuan <span class="required">*</span></label>
                                            <div class="input-group">
                                                <input type="number" name="detail[<?= $idx ?>][jumlah]" class="form-control" min="1" value="<?= (int)$d['jumlah'] ?>" onchange="calculateTotal(<?= $idx ?>)" required>
                                                <select name="detail[<?= $idx ?>][satuan]" class="form-control" required>
                                                    <option value="Pcs" <?= $d['satuan']=='Pcs'?'selected':'' ?>>Pcs</option>
                                                    <option value="Unit" <?= $d['satuan']=='Unit'?'selected':'' ?>>Unit</option>
                                                    <option value="Set" <?= $d['satuan']=='Set'?'selected':'' ?>>Set</option>
                                                    <option value="Buah" <?= $d['satuan']=='Buah'?'selected':'' ?>>Buah</option>
                                                    <option value="Paket" <?= $d['satuan']=='Paket'?'selected':'' ?>>Paket</option>
                                                    <option value="Lainnya" <?= ($d['satuan']!='Pcs' && $d['satuan']!='Unit' && $d['satuan']!='Set' && $d['satuan']!='Buah' && $d['satuan']!='Paket')?'selected':'' ?>>Lainnya</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <label>Deskripsi Detail</label>
                                            <textarea name="detail[<?= $idx ?>][deskripsi]" class="form-control" rows="2"><?= htmlspecialchars($d['deskripsi']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                $idx++;
                                endforeach;
                            else:
                                // jika kosong, buat satu item kosong
                            ?>
                                <div class="detail-item" data-index="0">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeDetailItem(0)" style="display:none"><i class="fas fa-times"></i></button>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label>No. Akun <span class="required">*</span></label>
                                            <input type="text" name="detail[0][no_akun]" class="form-control" required>
                                        </div>
                                        <div class="col-md-8 mb-2">
                                            <label>Rincian Item <span class="required">*</span></label>
                                            <input type="text" name="detail[0][rincian]" class="form-control" required>
                                        </div>

                                        <div class="col-md-3 mb-2">
                                            <label>Harga Satuan <span class="required">*</span></label>
                                            <input type="text" name="detail[0][harga_satuan]" class="form-control harga-input" onkeyup="formatRupiah(this); calculateTotal(0)" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label>Total Harga</label>
                                            <input type="text" name="detail[0][total_harga]" class="form-control total-harga" value="Rp 0" readonly>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label>Jumlah & Satuan <span class="required">*</span></label>
                                            <div class="input-group">
                                                <input type="number" name="detail[0][jumlah]" class="form-control" min="1" value="1" onchange="calculateTotal(0)" required>
                                                <select name="detail[0][satuan]" class="form-control" required>
                                                    <option value="Pcs">Pcs</option>
                                                    <option value="Unit">Unit</option>
                                                    <option value="Set">Set</option>
                                                    <option value="Buah">Buah</option>
                                                    <option value="Paket">Paket</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-2">
                                            <label>Deskripsi Detail</label>
                                            <textarea name="detail[0][deskripsi]" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <div class="row">
                                <div class="col-md-6"><strong>Total Nominal PPX</strong></div>
                                <div class="col-md-6 text-right"><h5 id="grandTotal" class="mb-0">Rp <?= number_format((float)($ppx_data['nominal_ppx'] ?? 0), 0, ',', '.') ?></h5></div>
                            </div>
                        </div>
                    </div>

                    <!-- Justifikasi & Lampiran -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">Justifikasi & Lampiran</div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Alasan Pengajuan <span class="required">*</span></label>
                                <textarea name="alasan_pengajuan" class="form-control" rows="4" required><?= htmlspecialchars($ppx['alasan_pengajuan']) ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Tingkat Kepentingan <span class="required">*</span></label>
                                    <select name="tingkat_kepentingan" class="form-control" required>
                                        <option value="Rendah" <?= $ppx['tingkat_kepentingan']=='Rendah'?'selected':'' ?>>Rendah</option>
                                        <option value="Sedang" <?= $ppx['tingkat_kepentingan']=='Sedang'?'selected':'' ?>>Sedang</option>
                                        <option value="Tinggi" <?= $ppx['tingkat_kepentingan']=='Tinggi'?'selected':'' ?>>Tinggi</option>
                                        <option value="Urgent" <?= $ppx['tingkat_kepentingan']=='Urgent'?'selected':'' ?>>Urgent</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-6">
                                    <label>File Lampiran</label>
                                    <?php if (!empty($ppx['upload_file'])): ?>
                                        <div class="mb-2"><a href="../uploads/ppx/<?= htmlspecialchars($ppx['upload_file']) ?>" target="_blank"><?= htmlspecialchars($ppx['upload_file']) ?></a></div>
                                    <?php endif; ?>
                                    <input type="file" name="upload_file" class="form-control-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                </div>
                            </div>

                            <!-- Action: tombol dipindah di sini, tepat di bawah File Lampiran -->
                            <div class="mt-3 text-right">
                                <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    // Sertakan footer (footer.php sudah menyertakan skrip global)
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/footer.php')) {
        include $layout_dir . '/footer.php';
    } else {
        include __DIR__ . '/../layouts/footer.php';
    }

    // Pastikan juga footer_js.php disertakan seperti di dashboard agar semua skrip global dimuat
    if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) {
        include $layout_dir . '/footer_js.php';
    } else {
        // fallback: coba include dari layouts jika ada
        $fallback = __DIR__ . '/../layouts/footer_js.php';
        if (file_exists($fallback)) include $fallback;
    }
    ?>

    <script>
        // prepare initial detailIndex based on existing items
        var detailIndex = <?= max(1, $idx) ?>;

        function formatRupiah(input) {
            var value = input.value.replace(/[^,\d]/g, '').toString();
            var split = value.split(',');
            var sisa = split[0].length % 3;
            var rupiah = split[0].substr(0, sisa);
            var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if (ribuan) rupiah += (sisa ? '.' : '') + ribuan.join('.');
            input.value = rupiah ? 'Rp ' + rupiah : '';
        }

        function calculateTotal(index) {
            var jumlahEl = document.querySelector('[name="detail['+index+'][jumlah]"]');
            var hargaEl = document.querySelector('[name="detail['+index+'][harga_satuan]"]');
            var totalEl = document.querySelector('[name="detail['+index+'][total_harga]"]');

            if (!jumlahEl || !hargaEl || !totalEl) return;
            var jumlah = parseInt(jumlahEl.value) || 0;
            var harga = parseInt(hargaEl.value.replace(/[^0-9]/g,'')) || 0;
            var total = jumlah * harga;
            var formatted = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            totalEl.value = 'Rp ' + (total > 0 ? formatted : '0');
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            var grand = 0;
            document.querySelectorAll('.total-harga').forEach(function(el) {
                var v = parseInt(el.value.replace(/[^0-9]/g,'')) || 0;
                grand += v;
            });
            var f = grand.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            document.getElementById('grandTotal').textContent = 'Rp ' + (grand>0?f:'0');
        }

        function addDetailItem() {
            var container = document.getElementById('detailContainer');
            var idx = detailIndex++;
            var div = document.createElement('div');
            div.className = 'detail-item';
            div.setAttribute('data-index', idx);
            div.innerHTML = `
                <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeDetailItem(${idx})"><i class="fas fa-times"></i></button>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label>No. Akun <span class="required">*</span></label>
                        <input type="text" name="detail[${idx}][no_akun]" class="form-control" required>
                    </div>
                    <div class="col-md-8 mb-2">
                        <label>Rincian Item <span class="required">*</span></label>
                        <input type="text" name="detail[${idx}][rincian]" class="form-control" required>
                    </div>

                    <div class="col-md-3 mb-2">
                        <label>Harga Satuan <span class="required">*</span></label>
                        <input type="text" name="detail[${idx}][harga_satuan]" class="form-control harga-input" onkeyup="formatRupiah(this); calculateTotal(${idx})" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label>Total Harga</label>
                        <input type="text" name="detail[${idx}][total_harga]" class="form-control total-harga" value="Rp 0" readonly>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>Jumlah & Satuan <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="number" name="detail[${idx}][jumlah]" class="form-control" min="1" value="1" onchange="calculateTotal(${idx})" required>
                            <select name="detail[${idx}][satuan]" class="form-control" required>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Set">Set</option>
                                <option value="Buah">Buah</option>
                                <option value="Paket">Paket</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <label>Deskripsi Detail</label>
                        <textarea name="detail[${idx}][deskripsi]" class="form-control" rows="2"></textarea>
                    </div>
                </div>`;
            container.appendChild(div);
            updateRemoveButtons();
        }

        function removeDetailItem(index) {
            var el = document.querySelector('.detail-item[data-index="'+index+'"]');
            if (el) el.remove();
            calculateGrandTotal();
            updateRemoveButtons();
        }

        function updateRemoveButtons() {
            var items = document.querySelectorAll('.detail-item');
            items.forEach(function(it){
                var btn = it.querySelector('.btn-remove-item');
                if (items.length > 1) btn.style.display = 'block'; else btn.style.display = 'none';
            });
        }

        // initial calculations
        document.addEventListener('DOMContentLoaded', function(){
            // calculate totals for existing items
            document.querySelectorAll('.detail-item').forEach(function(it){
                var idx = it.getAttribute('data-index');
                calculateTotal(idx);
            });
            updateRemoveButtons();
        });

        // form validation: tanggal dibutuhkan minimal hari ini
        document.getElementById('editForm').addEventListener('submit', function(e){
            var tanggal = new Date(document.getElementById('tanggal_dibutuhkan').value);
            var today = new Date(); today.setHours(0,0,0,0);
            tanggal.setHours(0,0,0,0);
            if (tanggal < today) {
                e.preventDefault();
                alert('Tanggal dibutuhkan harus hari ini atau lebih.');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { 
    header('Location: ../login.php'); 
    exit(); 
}
$auth->requirePermission('master_vendor', 'update', 'dashboard.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM master_vendor WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$vendor = mysqli_fetch_assoc($res);

if (!$vendor) {
    $_SESSION['error'] = 'Data vendor tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// List default categories
$kategoriOptions = ['ATK', 'Cleaning Service', 'Elektronik', 'Entertaiment', 'Kendaraan', 'Perawatan Gedung', 'Pest Control', 'Teknisi', 'Ekspedisi', 'Percetakan'];
$q_kat = mysqli_query($conn, "SELECT DISTINCT kategori_vendor FROM master_vendor WHERE kategori_vendor IS NOT NULL AND kategori_vendor != ''");
if ($q_kat) {
    while ($rk = mysqli_fetch_assoc($q_kat)) {
        if (!in_array($rk['kategori_vendor'], $kategoriOptions)) {
            $kategoriOptions[] = $rk['kategori_vendor'];
        }
    }
}
sort($kategoriOptions);
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Edit Vendor</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Master Vendor</a></li>
                                <li class="breadcrumb-item active">Edit Vendor</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="vendor_update.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?= $vendor['id'] ?>">
                                    <input type="hidden" name="existing_file_mou" id="existing_file_mou" value="<?= htmlspecialchars($vendor['file_mou'] ?? '') ?>">

                                    <div class="row">
                                        <!-- BAGIAN 1: INFORMASI UTAMA VENDOR -->
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Informasi Utama Vendor</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Kode / ID Vendor <span class="text-danger">*</span></label>
                                                <input type="text" name="kode_vendor" class="form-control" value="<?= htmlspecialchars($vendor['kode_vendor'] ?? '') ?>" placeholder="Contoh: PC-VND-001" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Nama Vendor / Perusahaan <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_vendor" class="form-control" value="<?= htmlspecialchars($vendor['nama_vendor']) ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Status Hukum <span class="text-danger">*</span></label>
                                                <select name="status_hukum" class="form-control select2" required>
                                                    <option value="">-- Pilih Status Hukum --</option>
                                                    <?php 
                                                    $hukumOpts = ['PT', 'CV', 'Perorangan', 'Yayasan', 'Koperasi', 'Lainnya'];
                                                    foreach ($hukumOpts as $h): 
                                                    ?>
                                                        <option value="<?= $h ?>" <?= ($vendor['status_hukum'] === $h) ? 'selected' : '' ?>><?= $h ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Kategori Vendor <span class="text-danger">*</span></label>
                                                <select name="kategori_vendor" class="form-control select2" required>
                                                    <option value="">-- Pilih Kategori Vendor --</option>
                                                    <?php foreach ($kategoriOptions as $opt): ?>
                                                        <option value="<?= htmlspecialchars($opt) ?>" <?= ($vendor['kategori_vendor'] === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Peruntukan Vendor <span class="text-danger">*</span></label>
                                                <input type="text" name="peruntukan_vendor" class="form-control" value="<?= htmlspecialchars($vendor['peruntukan_vendor'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <div class="form-group">
                                                <label>Area Layanan <span class="text-danger">*</span></label>
                                                <select name="area" class="form-control select2" required>
                                                    <option value="">-- Pilih Area --</option>
                                                    <?php 
                                                    $areaOpts = ['HO', 'HO dan Cabang', 'Cabang', 'Semua Area'];
                                                    foreach ($areaOpts as $a): 
                                                    ?>
                                                        <option value="<?= $a ?>" <?= ($vendor['area'] === $a) ? 'selected' : '' ?>><?= $a ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <div class="form-group">
                                                <label>Status Vendor <span class="text-danger">*</span></label>
                                                <select name="status_vendor" class="form-control select2" required>
                                                    <option value="Aktif" <?= ($vendor['status_vendor'] === 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="Non-Aktif" <?= ($vendor['status_vendor'] === 'Non-Aktif') ? 'selected' : '' ?>>Non-Aktif</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- BAGIAN 2: KONTAK & LOKASI -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Kontak & Lokasi</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Nama PIC Tim GA <span class="text-danger">*</span></label>
                                                <input type="text" name="pic_ga" class="form-control" value="<?= htmlspecialchars($vendor['pic_ga'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Contact Vendor / WhatsApp <span class="text-danger">*</span></label>
                                                <input type="text" name="kontak_vendor" class="form-control" value="<?= htmlspecialchars($vendor['kontak_vendor'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Email Vendor <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($vendor['email'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Alamat Lengkap <span class="text-danger">*</span></label>
                                                <textarea name="alamat" class="form-control" rows="2" required><?= htmlspecialchars($vendor['alamat'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <!-- BAGIAN 3: LEGALITAS & FINANSIAL -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Legalitas, Perpajakan & Perbankan</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>NPWP <span class="text-danger">*</span></label>
                                                <input type="text" name="npwp" class="form-control" value="<?= htmlspecialchars($vendor['npwp'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Status PKP <span class="text-danger">*</span></label>
                                                <select name="status_pkp" class="form-control select2" required>
                                                    <option value="Non PKP" <?= ($vendor['status_pkp'] === 'Non PKP') ? 'selected' : '' ?>>Non PKP</option>
                                                    <option value="PKP" <?= ($vendor['status_pkp'] === 'PKP') ? 'selected' : '' ?>>PKP</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>MoU (Memorandum of Understanding) <span class="text-danger">*</span></label>
                                                <select name="status_mou" id="status_mou" class="form-control select2" required>
                                                    <option value="Ada" <?= ($vendor['status_mou'] === 'Ada') ? 'selected' : '' ?>>Ada</option>
                                                    <option value="Tidak Ada" <?= ($vendor['status_mou'] === 'Tidak Ada') ? 'selected' : '' ?>>Tidak Ada</option>
                                                    <option value="Belum Ada" <?= ($vendor['status_mou'] === 'Belum Ada' || $vendor['status_mou'] === 'Blm ada') ? 'selected' : '' ?>>Belum Ada</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Soft File Dokumen MoU <span class="text-danger" id="file_mou_required" style="display:none;">*</span> <small class="text-muted" id="lbl_mou_opt">(Jika Status MoU: Ada)</small></label>
                                                <?php if (!empty($vendor['file_mou'])): ?>
                                                    <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                                        <span class="small font-weight-bold text-dark"><i class="fas fa-file-alt text-primary mr-1"></i> File Tersimpan: <?= htmlspecialchars($vendor['file_mou']) ?></span>
                                                        <a href="../uploads/vendor_mou/<?= htmlspecialchars($vendor['file_mou']) ?>" target="_blank" class="btn btn-xs btn-outline-primary">
                                                            <i class="fas fa-external-link-alt mr-1"></i> Buka File
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="custom-file">
                                                    <input type="file" name="file_mou" id="file_mou" class="custom-file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                    <label class="custom-file-label" for="file_mou"><?= !empty($vendor['file_mou']) ? 'Ganti soft file MoU...' : 'Pilih soft file MoU (PDF, Word, Gambar)...' ?></label>
                                                </div>
                                                <small class="text-muted"><?= !empty($vendor['file_mou']) ? 'Biarkan kosong jika tidak ingin mengubah file MoU.' : 'Format: PDF, Word (DOC/DOCX), JPG, PNG (Maks 10MB)' ?></small>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Lead Time Pengiriman / Pengerjaan <span class="text-danger">*</span></label>
                                                <input type="text" name="lead_time" class="form-control" value="<?= htmlspecialchars($vendor['lead_time'] ?? '') ?>" required />
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Nama Bank <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_bank" class="form-control" value="<?= htmlspecialchars($vendor['nama_bank'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>No. Rekening <span class="text-danger">*</span></label>
                                                <input type="text" name="no_rekening" class="form-control" value="<?= htmlspecialchars($vendor['no_rekening'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Atas Nama Rekening <span class="text-danger">*</span></label>
                                                <input type="text" name="atas_nama_rekening" class="form-control" value="<?= htmlspecialchars($vendor['atas_nama_rekening'] ?? '') ?>" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Metode Pembayaran <span class="text-danger">*</span></label>
                                                <input type="text" name="metode_pembayaran" class="form-control" value="<?= htmlspecialchars($vendor['metode_pembayaran'] ?? '') ?>" required />
                                            </div>
                                        </div>

                                        <!-- BAGIAN 4: KETERANGAN & CATATAN EVALUASI (OPSIONAL) -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Keterangan & Catatan Performa <small class="text-muted font-italic" style="font-size:0.75rem; text-transform:none;">(Tidak Wajib Diisi)</small></span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Keterangan Tambahan <small class="text-muted font-italic">(Opsional)</small></label>
                                                <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($vendor['keterangan'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Catatan Performa / Review <small class="text-muted font-italic">(Opsional)</small></label>
                                                <textarea name="review_performa" class="form-control" rows="3"><?= htmlspecialchars($vendor['review_performa'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <div class="col-12 text-right mt-4">
                                            <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });

            // Update label custom-file-input saat file dipilih
            $('#file_mou').on('change', function() {
                const fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName || 'Pilih soft file MoU...');
            });

            // Cek status MoU dan kelengkapan file MoU
            const hasExistingMou = <?= !empty($vendor['file_mou']) ? 'true' : 'false' ?>;
            function checkMouStatus() {
                const mou = $('#status_mou').val();
                if (mou === 'Ada') {
                    $('#file_mou_required').show();
                    if (!hasExistingMou) {
                        $('#lbl_mou_opt').html('<span class="text-danger font-weight-bold">(Wajib diunggah saat ada MoU)</span>');
                        $('#file_mou').attr('required', true);
                    } else {
                        $('#lbl_mou_opt').html('<span class="text-success font-weight-bold">(File sudah tersimpan, unggah jika ingin mengganti)</span>');
                        $('#file_mou').removeAttr('required');
                    }
                } else {
                    $('#file_mou_required').hide();
                    $('#lbl_mou_opt').html('(Jika Status MoU: Ada)');
                    $('#file_mou').removeAttr('required');
                }
            }
            $('#status_mou').on('change', checkMouStatus);
            checkMouStatus();
        });
    </script>
</body>
</html>

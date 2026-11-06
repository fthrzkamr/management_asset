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
                                <form action="vendor_update.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $vendor['id'] ?>">

                                    <div class="row">
                                        <!-- BAGIAN 1: INFORMASI UTAMA VENDOR -->
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Informasi Utama Vendor</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Kode / ID Vendor</label>
                                                <input type="text" name="kode_vendor" class="form-control" value="<?= htmlspecialchars($vendor['kode_vendor'] ?? '') ?>" placeholder="Contoh: PC-VND-001" />
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
                                                <label>Status Hukum</label>
                                                <select name="status_hukum" class="form-control select2">
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
                                                <label>Kategori Vendor</label>
                                                <select name="kategori_vendor" class="form-control select2">
                                                    <option value="">-- Pilih Kategori Vendor --</option>
                                                    <?php foreach ($kategoriOptions as $opt): ?>
                                                        <option value="<?= htmlspecialchars($opt) ?>" <?= ($vendor['kategori_vendor'] === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Peruntukan Vendor</label>
                                                <input type="text" name="peruntukan_vendor" class="form-control" value="<?= htmlspecialchars($vendor['peruntukan_vendor'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <div class="form-group">
                                                <label>Area Layanan</label>
                                                <select name="area" class="form-control select2">
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
                                                <label>Status Vendor</label>
                                                <select name="status_vendor" class="form-control select2">
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
                                                <label>Nama PIC Tim GA</label>
                                                <input type="text" name="pic_ga" class="form-control" value="<?= htmlspecialchars($vendor['pic_ga'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Contact Vendor / WhatsApp</label>
                                                <input type="text" name="kontak_vendor" class="form-control" value="<?= htmlspecialchars($vendor['kontak_vendor'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Email Vendor</label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($vendor['email'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Alamat Lengkap</label>
                                                <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($vendor['alamat'] ?? '') ?></textarea>
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
                                                <label>NPWP</label>
                                                <input type="text" name="npwp" class="form-control" value="<?= htmlspecialchars($vendor['npwp'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Status PKP</label>
                                                <select name="status_pkp" class="form-control select2">
                                                    <option value="Non PKP" <?= ($vendor['status_pkp'] === 'Non PKP') ? 'selected' : '' ?>>Non PKP</option>
                                                    <option value="PKP" <?= ($vendor['status_pkp'] === 'PKP') ? 'selected' : '' ?>>PKP</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>MoU (Memorandum of Understanding)</label>
                                                <select name="status_mou" class="form-control select2">
                                                    <option value="Ada" <?= ($vendor['status_mou'] === 'Ada') ? 'selected' : '' ?>>Ada</option>
                                                    <option value="Tidak Ada" <?= ($vendor['status_mou'] === 'Tidak Ada') ? 'selected' : '' ?>>Tidak Ada</option>
                                                    <option value="Belum Ada" <?= ($vendor['status_mou'] === 'Belum Ada' || $vendor['status_mou'] === 'Blm ada') ? 'selected' : '' ?>>Belum Ada</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Nama Bank</label>
                                                <input type="text" name="nama_bank" class="form-control" value="<?= htmlspecialchars($vendor['nama_bank'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>No. Rekening</label>
                                                <input type="text" name="no_rekening" class="form-control" value="<?= htmlspecialchars($vendor['no_rekening'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Atas Nama Rekening</label>
                                                <input type="text" name="atas_nama_rekening" class="form-control" value="<?= htmlspecialchars($vendor['atas_nama_rekening'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Metode Pembayaran</label>
                                                <input type="text" name="metode_pembayaran" class="form-control" value="<?= htmlspecialchars($vendor['metode_pembayaran'] ?? '') ?>" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Lead Time Pengiriman / Pengerjaan</label>
                                                <input type="text" name="lead_time" class="form-control" value="<?= htmlspecialchars($vendor['lead_time'] ?? '') ?>" />
                                            </div>
                                        </div>

                                        <!-- BAGIAN 4: KETERANGAN & CATATAN EVALUASI -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Keterangan & Catatan Performa</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Keterangan Tambahan</label>
                                                <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($vendor['keterangan'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Catatan Performa / Review</label>
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
        });
    </script>
</body>
</html>

<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Get pengajuan ID
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'ID pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = mysqli_real_escape_string($conn, $_GET['id']);

// Get pengajuan data
$query = "SELECT 
    ar.*,
    la.lokasi_asset,
    u.nama_user as nama_pengaju,
    d.nama_divisi
FROM asset_request ar
LEFT JOIN lokasi_asset la ON ar.lokasi_penggunaan = la.id_lokasi
LEFT JOIN user_dfm u ON ar.user_id = u.id_user
LEFT JOIN divisi d ON ar.divisi = d.id_divisi
WHERE ar.no_pengajuan = '$no_pengajuan'";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$pengajuan = mysqli_fetch_assoc($result);

// Cek permission - harus punya izin update atau create (edit pengajuan sendiri)
$canUpdate = $auth->hasPermission('pengajuan_asset', 'update');
$canCreate = $auth->hasPermission('pengajuan_asset', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit pengajuan!';
    header("Location: dashboard.php");
    exit();
}

// Yang hanya punya 'create' (pengaju biasa) hanya bisa edit milik sendiri dan status Pending
if ($canCreate && !$canUpdate) {
    if ($pengajuan['user_id'] != $_SESSION['id_user'] || $pengajuan['status'] != 'Pending') {
        $_SESSION['error'] = 'Anda tidak dapat mengedit pengajuan ini!';
        header("Location: dashboard.php");
        exit();
    }
}

// Yang punya 'update' (HR/Admin) tapi status bukan Pending
if ($canUpdate && $pengajuan['status'] != 'Pending') {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit!';
    header("Location: dashboard.php");
    exit();
}


// Get kategori asset untuk dropdown
$kategori_query = "SELECT * FROM kategori_asset ORDER BY kategori_asset ASC";
$kategori_result = mysqli_query($conn, $kategori_query);

// Get lokasi asset untuk dropdown  
$lokasi_query = "SELECT * FROM lokasi_asset ORDER BY lokasi_asset ASC";
$lokasi_result = mysqli_query($conn, $lokasi_query);

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Pengajuan Asset</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title">Edit Pengajuan Asset</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan Asset</a></li>
                                <li class="breadcrumb-item active">Edit</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Form Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit fa-sm"></i> Form Edit Pengajuan Asset
                                </h5>
                            </div>
                            <div class="card-body">

                                <!-- Info Pengajuan -->
                                <div class="alert alert-secondary">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>No. Pengajuan:</strong><br>
                                            <span class="text-info"><?= htmlspecialchars($pengajuan['no_pengajuan']) ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Status:</strong><br>
                                            <span class="badge badge-warning"><?= htmlspecialchars($pengajuan['status']) ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Tanggal:</strong><br>
                                            <?= date('d/m/Y', strtotime($pengajuan['created_at'])) ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Pengaju:</strong><br>
                                            <?= htmlspecialchars($pengajuan['nama_pengaju']) ?>
                                        </div>
                                    </div>
                                </div>

                                <form action="process_edit.php" method="POST" enctype="multipart/form-data" id="editForm">
                                    <input type="hidden" name="no_pengajuan" value="<?= htmlspecialchars($pengajuan['no_pengajuan']) ?>">

                                    <div class="row">
                                        <!-- Kategori Asset -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Kategori Asset <span class="text-danger">*</span></label>
                                                <select class="form-control" name="kategori_asset" required>
                                                    <option value="">Pilih Kategori</option>
                                                    <?php
                                                    mysqli_data_seek($kategori_result, 0);
                                                    while ($kategori = mysqli_fetch_assoc($kategori_result)):
                                                        $is_selected = false;
                                                        if (is_numeric($pengajuan['kategori_asset']) && $kategori['id_kategori'] == $pengajuan['kategori_asset']) {
                                                            $is_selected = true;
                                                        } elseif ($kategori['kategori_asset'] == $pengajuan['kategori_asset']) {
                                                            $is_selected = true;
                                                        }
                                                        $option_value = is_numeric($pengajuan['kategori_asset']) ? $kategori['id_kategori'] : $kategori['kategori_asset'];
                                                    ?>
                                                        <option value="<?= htmlspecialchars($option_value) ?>" <?= $is_selected ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($kategori['kategori_asset']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Jenis Pengadaan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Jenis Pengadaan <span class="text-danger">*</span></label>
                                                <select class="form-control" name="jenis_pengadaan" required>
                                                    <option value="">Pilih Jenis</option>
                                                    <option value="Penambahan" <?= $pengajuan['jenis_pengadaan'] == 'Penambahan' ? 'selected' : '' ?>>Penambahan</option>
                                                    <option value="Penggantian" <?= $pengajuan['jenis_pengadaan'] == 'Penggantian' ? 'selected' : '' ?>>Penggantian</option>
                                                    <option value="Upgrade" <?= $pengajuan['jenis_pengadaan'] == 'Upgrade' ? 'selected' : '' ?>>Upgrade</option>
                                                    <option value="Maintenance" <?= $pengajuan['jenis_pengadaan'] == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Tingkat Kepentingan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Tingkat Kepentingan <span class="text-danger">*</span></label>
                                                <select class="form-control" name="tingkat_kepentingan" required>
                                                    <option value="">Pilih Tingkat</option>
                                                    <option value="Rendah" <?= $pengajuan['tingkat_kepentingan'] == 'Rendah' ? 'selected' : '' ?>>Rendah</option>
                                                    <option value="Sedang" <?= $pengajuan['tingkat_kepentingan'] == 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                                                    <option value="Tinggi" <?= $pengajuan['tingkat_kepentingan'] == 'Tinggi' ? 'selected' : '' ?>>Tinggi</option>
                                                    <option value="Urgent" <?= $pengajuan['tingkat_kepentingan'] == 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- PIC -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>PIC <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="nama_pic"
                                                    value="<?= htmlspecialchars($pengajuan['nama_pic']) ?>" required>
                                            </div>
                                        </div>

                                        <!-- Jabatan (readonly, sudah tersimpan) -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Jabatan <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="jabatan"
                                                    value="<?= htmlspecialchars($pengajuan['jabatan']) ?>" readonly>
                                                <small class="text-muted">Jabatan tidak dapat diubah</small>
                                            </div>
                                        </div>

                                         <!-- Divisi (readonly, sudah tersimpan) -->
                                         <div class="col-md-4">
                                             <div class="form-group">
                                                 <label>Divisi <span class="text-danger">*</span></label>
                                                 <input type="text" class="form-control"
                                                     value="<?= htmlspecialchars($pengajuan['nama_divisi'] ?? $pengajuan['divisi']) ?>" readonly>
                                                 <input type="hidden" name="divisi" value="<?= htmlspecialchars($pengajuan['divisi']) ?>">
                                                 <small class="text-muted">Divisi tidak dapat diubah</small>
                                             </div>
                                         </div>
                                    </div>

                                    <div class="row">
                                        <!-- Deskripsi Asset -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Deskripsi Asset <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="deskripsi_asset" rows="3" required><?= htmlspecialchars($pengajuan['deskripsi_asset']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Spesifikasi -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Spesifikasi Merek/Model</label>
                                                <textarea class="form-control" name="spesifikasi_merek" rows="3"><?= htmlspecialchars($pengajuan['spesifikasi_merek']) ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Alasan -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Alasan Pengajuan <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="alasan_pengajuan" rows="3" required><?= htmlspecialchars($pengajuan['alasan_pengajuan']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Lokasi -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Lokasi Penggunaan <span class="text-danger">*</span></label>
                                                <select class="form-control" name="lokasi_penggunaan" required>
                                                    <option value="">Pilih Lokasi</option>
                                                    <?php while ($lokasi = mysqli_fetch_assoc($lokasi_result)): ?>
                                                        <option value="<?= $lokasi['id_lokasi'] ?>"
                                                            <?= $lokasi['id_lokasi'] == $pengajuan['lokasi_penggunaan'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($lokasi['lokasi_asset']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Detail Lokasi -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Detail Lokasi</label>
                                                <input type="text" class="form-control" name="detail_lokasi"
                                                    value="<?= htmlspecialchars($pengajuan['detail_lokasi']) ?>">
                                            </div>
                                        </div>

                                        <!-- Tanggal Dibutuhkan -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Tanggal Dibutuhkan <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="tanggal_dibutuhkan"
                                                    value="<?= date('Y-m-d', strtotime($pengajuan['tanggal_dibutuhkan'])) ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Jumlah -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Jumlah <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="jumlah"
                                                    value="<?= htmlspecialchars($pengajuan['jumlah']) ?>" min="1" required>
                                            </div>
                                        </div>

                                        <!-- Satuan -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Satuan <span class="text-danger">*</span></label>
                                                <select class="form-control" name="satuan" required>
                                                    <option value="">Pilih Satuan</option>
                                                    <?php
                                                    $satuan_options = ['unit', 'set', 'buah', 'pcs', 'license', 'paket'];
                                                    foreach ($satuan_options as $satuan_opt): ?>
                                                        <option value="<?= $satuan_opt ?>" <?= $satuan_opt == $pengajuan['satuan'] ? 'selected' : '' ?>>
                                                            <?= $satuan_opt ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Estimasi Harga -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Estimasi Harga</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">Rp</span>
                                                    </div>
                                                    <input type="text" class="form-control" name="estimasi_harga"
                                                        value="<?= number_format($pengajuan['estimasi_harga'], 0, ',', '.') ?>"
                                                        onkeyup="formatRupiah(this)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- File -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>File Lampiran</label>
                                                <?php if (!empty($pengajuan['file_attachment'])): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">File saat ini: </small>
                                                        <a href="../uploads/pengajuan/<?= htmlspecialchars($pengajuan['file_attachment']) ?>"
                                                            target="_blank" class="text-primary">
                                                            <?= htmlspecialchars($pengajuan['file_attachment']) ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control-file" name="file_attachment"
                                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                <small class="text-muted">Format: PDF, DOC, DOCX, JPG, PNG. Max: 5MB</small>
                                            </div>
                                        </div>

                                        <!-- Catatan -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Catatan Tambahan</label>
                                                <textarea class="form-control" name="catatan_tambahan" rows="4"><?= htmlspecialchars($pengajuan['catatan_tambahan']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="form-group text-right">
                                        <a href="dashboard.php" class="btn btn-secondary mr-2">
                                            <i class="fas fa-times fa-sm"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="fas fa-save fa-sm"></i> Update Pengajuan
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('../layouts/footer.php'); ?>

            </div>
        </div>
    </div>


    <script>
        $(document).ready(function() {
            // Set minimum date
            var today = new Date();
            var tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            var minDate = tomorrow.toISOString().split('T')[0];
            $('input[name="tanggal_dibutuhkan"]').attr('min', minDate);

            // Form validation
            $('#editForm').on('submit', function(e) {
                var tanggalDibutuhkan = new Date($('input[name="tanggal_dibutuhkan"]').val());
                var today = new Date();

                if (tanggalDibutuhkan <= today) {
                    e.preventDefault();
                    alert('Tanggal dibutuhkan harus lebih dari hari ini!');
                    return false;
                }

                $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin fa-sm"></i> Updating...');
            });
        });

        // Format rupiah
        function formatRupiah(element) {
            var value = element.value.replace(/\D/g, '');
            var formatted = new Intl.NumberFormat('id-ID').format(value);
            element.value = formatted;
        }

        // File validation
        $('input[name="file_attachment"]').on('change', function() {
            const file = this.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];

            if (file) {
                if (file.size > maxSize) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB.');
                    this.value = '';
                    return;
                }

                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung!');
                    this.value = '';
                    return;
                }
            }
        });
    </script>

</body>

</html>
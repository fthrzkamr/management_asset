<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Get pengajuan ID (support baik parameter no_pengajuan maupun id)
$param = trim($_GET['no_pengajuan'] ?? $_GET['id'] ?? '');
if (empty($param)) {
    $_SESSION['error'] = 'ID pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Auto-ensure tabel renovation_request memiliki kolom id (PRIMARY KEY AUTO_INCREMENT)
$chk_id = @mysqli_query($conn, "SHOW COLUMNS FROM renovation_request LIKE 'id'");
if ($chk_id && mysqli_num_rows($chk_id) === 0) {
    $chk_pri = @mysqli_query($conn, "SHOW KEYS FROM renovation_request WHERE Key_name = 'PRIMARY'");
    if ($chk_pri && mysqli_num_rows($chk_pri) > 0) {
        @mysqli_query($conn, "ALTER TABLE renovation_request ADD COLUMN id INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD UNIQUE KEY (id)");
    } else {
        @mysqli_query($conn, "ALTER TABLE renovation_request ADD COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
    }
}
$chk_idx = @mysqli_query($conn, "SHOW INDEX FROM renovation_request WHERE Column_name = 'no_pengajuan'");
if ($chk_idx && mysqli_num_rows($chk_idx) === 0) {
    @mysqli_query($conn, "ALTER TABLE renovation_request ADD UNIQUE KEY uq_no_pengajuan (no_pengajuan)");
}

$esc_param = mysqli_real_escape_string($conn, $param);
$is_num = is_numeric($param);
$where_find = $is_num ? "(rr.id = '$esc_param' OR rr.no_pengajuan = '$esc_param')" : "rr.no_pengajuan = '$esc_param'";

// Get pengajuan data
$query = "SELECT rr.*,
    u.nama_user as nama_pengaju
FROM renovation_request rr
LEFT JOIN user_dfm u ON rr.user_id = u.id_user
WHERE $where_find";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    // Fallback direct query sederhana jika JOIN bermasalah di database
    $fb_where = $is_num ? "(id = '$esc_param' OR no_pengajuan = '$esc_param')" : "no_pengajuan = '$esc_param'";
    $fallback = mysqli_query($conn, "SELECT * FROM renovation_request WHERE $fb_where");
    if ($fallback && mysqli_num_rows($fallback) > 0) {
        $pengajuan = mysqli_fetch_assoc($fallback);
    } else {
        $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
        header("Location: dashboard.php");
        exit();
    }
} else {
    $pengajuan = mysqli_fetch_assoc($result);
}

$no_pengajuan = $pengajuan['no_pengajuan'];

// Cek permission berdasarkan jenis akses
$isSuperAdmin = $auth->isSuperAdmin();
$canUpdate = $auth->hasPermission('pengajuan_renovasi', 'update') || $isSuperAdmin;
$canCreate = $auth->hasPermission('pengajuan_renovasi', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit pengajuan!';
    header("Location: dashboard.php");
    exit();
}

// User yang hanya punya 'create' hanya bisa edit milik sendiri dan status Pending
if (!$canUpdate && $canCreate) {
    if ($pengajuan['user_id'] != $_SESSION['id_user'] || $pengajuan['status'] != 'Pending') {
        $_SESSION['error'] = 'Anda tidak dapat mengedit pengajuan ini!';
        header("Location: dashboard.php");
        exit();
    }
}

// User dengan 'update' atau superadmin hanya bisa edit yang status Pending
if ($canUpdate && $pengajuan['status'] != 'Pending') {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit!';
    header("Location: dashboard.php");
    exit();
}

// Daftar jabatan
$jabatan_options = [
    'Manager',
    'Supervisor', 
    'HRD',
    'Admin',
    'Koordinator',
    'Team Leader',
    'Assistant Manager',
    'Executive',
    'Officer'
];

// Daftar kantor cabang
$kantor_cabang_options = [
    'Jakarta T1',
    'Jakarta T6',
    'Cabang Medan',
    'Bekasi',
    'Jakarta',
    'Bali',
    'Surabaya',
    'Banjarmasin',
    'Depo Malang',
    'Depo Cibinong'
];

// Daftar jenis pekerjaan
$jenis_pekerjaan_options = [
    'Penambahan Fasilitas',
    'Perbaikan',
    'Perubahan Tata Ruang'
];

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
    <title>Edit Pengajuan Renovasi</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .required {
            color: red;
        }

        .form-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            background: #f8f9fc;
        }

        .form-section h6 {
            margin-bottom: 15px;
            color: #5a5c69;
            font-weight: bold;
        }

        .photo-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin: 5px;
        }

        .photo-container {
            position: relative;
            display: inline-block;
        }

        .remove-photo {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
        }

        .btn-submit {
            background: #4e73df;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-size: 14px;
        }

        .readonly-field {
            background-color: #e9ecef !important;
        }
    </style>
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
                            <h3 class="page-title">Edit Pengajuan Renovasi</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan Renovasi</a></li>
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
                                    <i class="fas fa-edit fa-sm"></i> Form Edit Pengajuan Renovasi
                                </h5>
                            </div>
                            <div class="card-body">
                                
                                <!-- Info Pengajuan -->
                                <div class="alert alert-info">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>No. Pengajuan:</strong><br>
                                            <span><?= htmlspecialchars($pengajuan['no_pengajuan']) ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Status:</strong><br>
                                            <span class="badge badge-warning"><?= htmlspecialchars($pengajuan['status']) ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Tanggal:</strong><br>
                                            <?= date('d/m/Y', strtotime($pengajuan['tanggal_pengajuan'])) ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Pengaju:</strong><br>
                                            <?= htmlspecialchars($pengajuan['nama_pengaju']) ?>
                                        </div>
                                    </div>
                                </div>

                                <form action="process_edit.php" method="POST" enctype="multipart/form-data" id="renovasiForm">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($pengajuan['id'] ?? '') ?>">
                                    <input type="hidden" name="no_pengajuan" value="<?= htmlspecialchars($pengajuan['no_pengajuan']) ?>">
                                    
                                    <!-- Informasi Dasar -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-info-circle fa-sm"></i> Informasi Dasar</h6>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Nama PIC <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm readonly-field" name="nama_pic"
                                                        value="<?= htmlspecialchars($pengajuan['nama_pic']) ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Jabatan <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm readonly-field" name="jabatan"
                                                        value="<?= htmlspecialchars($pengajuan['jabatan']) ?>" readonly>
                                                    <small class="text-muted">Jabatan tidak dapat diubah</small>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Kantor Cabang yang Mengajukan <span class="required">*</span>
                                                    </label>
                                                    <select class="form-control form-control-sm" name="kantor_cabang" required>
                                                        <option value="">Pilih Kantor Cabang</option>
                                                        <?php foreach ($kantor_cabang_options as $kantor): ?>
                                                            <option value="<?= htmlspecialchars($kantor) ?>"
                                                                    <?= $kantor == $pengajuan['kantor_cabang'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($kantor) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="Lainnya" <?= !in_array($pengajuan['kantor_cabang'], $kantor_cabang_options) && !empty($pengajuan['kantor_cabang']) ? 'selected' : '' ?>>Lainnya</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2"
                                                        name="kantor_cabang_lainnya" placeholder="Kantor cabang lainnya"
                                                        value="<?= !in_array($pengajuan['kantor_cabang'], $kantor_cabang_options) && !empty($pengajuan['kantor_cabang']) ? htmlspecialchars($pengajuan['kantor_cabang']) : '' ?>"
                                                        style="display: <?= !in_array($pengajuan['kantor_cabang'], $kantor_cabang_options) && !empty($pengajuan['kantor_cabang']) ? 'block' : 'none' ?>;">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Area Renovasi <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" name="area_renovasi"
                                                        value="<?= htmlspecialchars($pengajuan['area_renovasi']) ?>"
                                                        placeholder="Contoh: Area Office, Bagian Belakang" required>
                                                    <small class="text-muted">Sebutkan area spesifik yang akan direnovasi</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detail Renovasi -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-tools fa-sm"></i> Detail Renovasi</h6>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Jenis Pekerjaan Renovasi <span class="required">*</span>
                                                    </label>
                                                    <select class="form-control form-control-sm" name="jenis_pekerjaan" required>
                                                        <option value="">Pilih Jenis Pekerjaan</option>
                                                        <?php foreach ($jenis_pekerjaan_options as $jenis): ?>
                                                            <option value="<?= htmlspecialchars($jenis) ?>"
                                                                    <?= $jenis == $pengajuan['jenis_pekerjaan'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($jenis) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="Lainnya" <?= !in_array($pengajuan['jenis_pekerjaan'], $jenis_pekerjaan_options) && !empty($pengajuan['jenis_pekerjaan']) ? 'selected' : '' ?>>Lainnya</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2"
                                                        name="jenis_pekerjaan_lainnya" placeholder="Jenis pekerjaan lainnya"
                                                        value="<?= !in_array($pengajuan['jenis_pekerjaan'], $jenis_pekerjaan_options) && !empty($pengajuan['jenis_pekerjaan']) ? htmlspecialchars($pengajuan['jenis_pekerjaan']) : '' ?>"
                                                        style="display: <?= !in_array($pengajuan['jenis_pekerjaan'], $jenis_pekerjaan_options) && !empty($pengajuan['jenis_pekerjaan']) ? 'block' : 'none' ?>;">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Prioritas <span class="required">*</span>
                                                    </label>
                                                    <select class="form-control form-control-sm" name="prioritas" required>
                                                        <option value="">Pilih Prioritas</option>
                                                        <option value="Rendah" <?= $pengajuan['prioritas'] == 'Rendah' ? 'selected' : '' ?>>Rendah - Tidak Mendesak</option>
                                                        <option value="Sedang" <?= $pengajuan['prioritas'] == 'Sedang' ? 'selected' : '' ?>>Sedang - Perlu Segera</option>
                                                        <option value="Tinggi" <?= $pengajuan['prioritas'] == 'Tinggi' ? 'selected' : '' ?>>Tinggi - Sangat Penting</option>
                                                        <option value="Urgent" <?= $pengajuan['prioritas'] == 'Urgent' ? 'selected' : '' ?>>Urgent - Darurat</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Alasan dan Tujuan Renovasi <span class="required">*</span>
                                                    </label>
                                                    <textarea class="form-control form-control-sm" name="alasan_renovasi"
                                                        rows="4" required
                                                        placeholder="Jelaskan detail alasan renovasi, tujuan yang ingin dicapai, dan manfaat yang diharapkan..."><?= htmlspecialchars($pengajuan['alasan_renovasi']) ?></textarea>
                                                    <small class="text-muted">Berikan penjelasan lengkap untuk memudahkan proses persetujuan</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Foto Area -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-camera fa-sm"></i> Foto Area (Maksimal 5 Foto)</h6>

                                        <?php if (!empty($pengajuan['foto_area'])): ?>
                                            <div class="mb-3">
                                                <label class="small font-weight-bold text-muted">Foto Saat Ini:</label>
                                                <div class="current-photos">
                                                    <?php 
                                                    $foto_array = json_decode($pengajuan['foto_area'], true);
                                                    if (is_array($foto_array)):
                                                        foreach ($foto_array as $index => $foto): ?>
                                                            <div class="photo-container">
                                                                <img src="../uploads/renovasi/<?= htmlspecialchars($foto) ?>" 
                                                                     class="photo-preview" alt="Current Photo <?= $index + 1 ?>">
                                                                <div class="text-center">
                                                                    <small>Foto <?= $index + 1 ?></small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach;
                                                    endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <label class="small font-weight-bold">Upload Foto Area Baru (Opsional)</label>
                                            <input type="file" class="form-control-file" name="foto_area[]"
                                                accept="image/*" multiple>
                                            <small class="text-muted">
                                                Format: JPG, JPEG, PNG • Maksimal 5 foto • Ukuran maksimal 2MB per foto<br>
                                                <strong>Catatan:</strong> Foto baru akan mengganti foto yang sudah ada
                                            </small>
                                        </div>

                                        <!-- Preview Container -->
                                        <div id="photoPreview" class="mt-3"></div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn btn-secondary mr-2">
                                            <i class="fas fa-times fa-xs"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-info" id="submitBtn">
                                            <i class="fas fa-save fa-xs"></i> Update Pengajuan
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include('../layouts/footer.php'); ?>

    <script>
        // Handle dropdown "Lainnya"
        document.querySelector('select[name="jabatan"]').addEventListener('change', function() {
            const lainnyaInput = document.querySelector('input[name="jabatan_lainnya"]');
            if (this.value === 'Lainnya') {
                lainnyaInput.style.display = 'block';
                lainnyaInput.required = true;
            } else {
                lainnyaInput.style.display = 'none';
                lainnyaInput.required = false;
                lainnyaInput.value = '';
            }
        });

        document.querySelector('select[name="kantor_cabang"]').addEventListener('change', function() {
            const lainnyaInput = document.querySelector('input[name="kantor_cabang_lainnya"]');
            if (this.value === 'Lainnya') {
                lainnyaInput.style.display = 'block';
                lainnyaInput.required = true;
            } else {
                lainnyaInput.style.display = 'none';
                lainnyaInput.required = false;
                lainnyaInput.value = '';
            }
        });

        document.querySelector('select[name="jenis_pekerjaan"]').addEventListener('change', function() {
            const lainnyaInput = document.querySelector('input[name="jenis_pekerjaan_lainnya"]');
            if (this.value === 'Lainnya') {
                lainnyaInput.style.display = 'block';
                lainnyaInput.required = true;
            } else {
                lainnyaInput.style.display = 'none';
                lainnyaInput.required = false;
                lainnyaInput.value = '';
            }
        });

        // Handle photo preview
        document.querySelector('input[name="foto_area[]"]').addEventListener('change', function() {
            const files = this.files;
            const maxFiles = 5;
            const maxSize = 2 * 1024 * 1024; // 2MB
            const previewContainer = document.getElementById('photoPreview');

            if (files.length > maxFiles) {
                alert(`Maksimal ${maxFiles} foto yang dapat diupload!`);
                this.value = '';
                return;
            }

            previewContainer.innerHTML = '';

            for (let i = 0; i < files.length; i++) {
                const file = files[i];

                if (file.size > maxSize) {
                    alert(`Foto "${file.name}" terlalu besar! Maksimal 2MB per foto.`);
                    this.value = '';
                    previewContainer.innerHTML = '';
                    return;
                }

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const photoContainer = document.createElement('div');
                        photoContainer.className = 'photo-container';
                        photoContainer.innerHTML = `
                            <img src="${e.target.result}" class="photo-preview" alt="New Photo ${i+1}">
                            <button type="button" class="remove-photo" onclick="removePhoto(this, ${i})">×</button>
                            <div class="text-center">
                                <small>Foto Baru ${i+1}</small>
                            </div>
                        `;
                        previewContainer.appendChild(photoContainer);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        // Function to remove photo
        function removePhoto(button, index) {
            button.parentElement.remove();

            // Reset file input if no photos left
            if (document.getElementById('photoPreview').children.length === 0) {
                document.querySelector('input[name="foto_area[]"]').value = '';
            }
        }

        // Form validation
        document.getElementById('renovasiForm').addEventListener('submit', function(e) {
            // Disable submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin fa-xs"></i> Updating...';

            // Validasi foto
            const files = document.querySelector('input[name="foto_area[]"]').files;
            if (files.length > 5) {
                e.preventDefault();
                alert('Maksimal 5 foto yang dapat diupload!');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save fa-xs"></i> Update Pengajuan';
                return false;
            }
        });

        // Validate file types
        document.querySelector('input[name="foto_area[]"]').addEventListener('change', function() {
            const files = this.files;
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

            for (let i = 0; i < files.length; i++) {
                if (!allowedTypes.includes(files[i].type)) {
                    alert('Format file tidak didukung! Gunakan JPG, JPEG, atau PNG.');
                    this.value = '';
                    document.getElementById('photoPreview').innerHTML = '';
                    return;
                }
            }
        });
    </script>

</body>

</html>
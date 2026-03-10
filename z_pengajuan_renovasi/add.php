<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\add.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - harus punya izin create
$auth->requirePermission('pengajuan_renovasi', 'create');


$user_query = "SELECT nama_user, jabatan FROM user_dfm WHERE id_user = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $_SESSION['id_user']);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_data = mysqli_fetch_assoc($user_result);
$nama_user = $user_data['nama_user'] ?? '';
$jabatan_user = $user_data['jabatan'] ?? '-';
mysqli_stmt_close($user_stmt);

// Generate nomor pengajuan dengan random number
function generateNoPengajuan($conn) {
    $prefix = "RNV";
    $date = date('Ymd');
    
    // Generate 4 digit random number untuk memastikan uniqueness
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Gabungkan: RNV + YYYYMMDD + 4 digit random
    $no_pengajuan = $prefix . '-' . $date . '-' . $random;
    
    // Double check apakah sudah ada di database (sangat kecil kemungkinannya)
    $check_query = "SELECT no_pengajuan FROM renovation_request WHERE no_pengajuan = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    // Jika masih konflik (sangat jarang), generate ulang dengan mikrodetik
    if (mysqli_num_rows($check_result) > 0) {
        $microtime = substr(str_replace('.', '', microtime(true)), -4);
        $no_pengajuan = $prefix . $date . $microtime;
    }
    
    mysqli_stmt_close($check_stmt);
    return $no_pengajuan;
}

$no_pengajuan = generateNoPengajuan($conn);

// Daftar jabatan
$jabatan_options = [
    'Manager',
    'Supervisor', 
    'Koordinator',
    'HRD',
    'Assistant Manager',
    'Executive',
    'Officer'
];

// Daftar kantor cabang
$kantor_cabang_options = [
    'Puri T1',
    'Puri T6',
    'Cabang Medan',
    'Bekasi',
    'Puri',
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Pengajuan Renovasi</title>
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

        .btn-submit:hover {
            background: #2e59d9;
            color: white;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #545b62;
            color: white;
            text-decoration: none;
        }

        .no-pengajuan-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            color: #1976d2;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
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
                            <h3 class="page-title">Tambah Pengajuan Renovasi</h3>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left fa-sm"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus fa-sm"></i> Form Pengajuan Renovasi
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="add_insert.php" method="POST" enctype="multipart/form-data" id="renovasiForm">

                                    <!-- Informasi Dasar -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-info-circle fa-sm"></i> Informasi Dasar</h6>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">No. Pengajuan</label>
                                                    <input type="text" class="form-control form-control-sm" name="no_pengajuan"
                                                        value="<?= $no_pengajuan ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Nama PIC <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm readonly-field" name="nama_pic"
                                                        value="<?= htmlspecialchars($nama_user) ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Jabatan <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm readonly-field" name="jabatan"
                                                        value="<?= htmlspecialchars($jabatan_user) ?>" readonly>
                                                    <small class="text-muted">Jabatan diambil otomatis dari profil user</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Kantor Cabang yang Mengajukan <span class="required">*</span>
                                                    </label>
                                                    <select class="form-control form-control-sm" name="kantor_cabang" required>
                                                        <option value="">Pilih Kantor Cabang</option>
                                                        <?php foreach ($kantor_cabang_options as $kantor): ?>
                                                            <option value="<?= htmlspecialchars($kantor) ?>">
                                                                <?= htmlspecialchars($kantor) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="Lainnya">Lainnya</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2"
                                                        name="kantor_cabang_lainnya" placeholder="Kantor cabang lainnya"
                                                        style="display: none;">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">
                                                        Area Renovasi <span class="required">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" name="area_renovasi"
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
                                                            <option value="<?= htmlspecialchars($jenis) ?>">
                                                                <?= htmlspecialchars($jenis) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="Lainnya">Lainnya</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2"
                                                        name="jenis_pekerjaan_lainnya" placeholder="Jenis pekerjaan lainnya"
                                                        style="display: none;">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold" >
                                                        Prioritas <span class="required">*</span>
                                                    </label>
                                                    <select class="form-control form-control-sm" name="prioritas" required>
                                                        <option value=""selected>Pilih Prioritas</option>
                                                        <option value="Rendah">Rendah - Tidak Mendesak</option>
                                                        <option value="Sedang">Sedang - Perlu Segera</option>
                                                        <option value="Tinggi">Tinggi - Sangat Penting</option>
                                                        <option value="Urgent">Urgent - Darurat</option>
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
                                                        placeholder="Jelaskan detail alasan renovasi, tujuan yang ingin dicapai, dan manfaat yang diharapkan..."></textarea>
                                                    <small class="text-muted">Berikan penjelasan lengkap untuk memudahkan proses persetujuan</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Foto Area -->
                                    <div class="form-section">
                                        <h6><i class="fas fa-camera fa-sm"></i> Foto Area (Maksimal 5 Foto)</h6>

                                        <div class="form-group">
                                            <label class="small font-weight-bold">Upload Foto Area yang Akan Direnovasi</label>
                                            <input type="file" class="form-control-file" name="foto_area[]"
                                                accept="image/*" multiple>
                                            <small class="text-muted">
                                                Format: JPG, JPEG, PNG • Maksimal 5 foto • Ukuran maksimal 2MB per foto
                                            </small>
                                        </div>

                                        <!-- Preview Container -->
                                        <div id="photoPreview" class="mt-3"></div>
                                    </div>

                                    <!-- Submit Buttons -->
                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn-cancel mr-2">
                                            <i class="fas fa-times fa-xs"></i> Batal
                                        </a>
                                        <button type="submit" class="btn-submit" id="submitBtn">
                                            <i class="fas fa-save fa-xs"></i> Simpan Pengajuan
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
        // Handle dropdown "Lainnya"
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
                            <img src="${e.target.result}" class="photo-preview" alt="Preview ${i+1}">
                            <button type="button" class="remove-photo" onclick="removePhoto(this, ${i})">×</button>
                            <div class="text-center">
                                <small>Foto ${i+1}</small>
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
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin fa-xs"></i> Menyimpan...';

            // Validasi foto
            const files = document.querySelector('input[name="foto_area[]"]').files;
            if (files.length > 5) {
                e.preventDefault();
                alert('Maksimal 5 foto yang dapat diupload!');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save fa-xs"></i> Simpan Pengajuan';
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
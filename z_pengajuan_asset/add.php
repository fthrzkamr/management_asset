<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\add.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

// Cek session login

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - harus punya izin create
$auth->requirePermission('pengajuan_asset', 'create');


// PERBAIKAN: Hanya ambil kategori dan lokasi dari database
$qKategori = mysqli_query($conn, "SELECT id_kategori, kategori_asset FROM kategori_asset ORDER BY kategori_asset ASC");
$qLokasi = mysqli_query($conn, "SELECT id_lokasi, lokasi_asset FROM lokasi_asset ORDER BY lokasi_asset ASC");

// Ambil data user dengan JOIN ke tabel divisi dan departemen
$user_id = $_SESSION['id_user'];
$user_query = "SELECT u.*, d.nama_divisi, dp.nama_departemen 
              FROM user_dfm u 
              LEFT JOIN divisi d ON u.divisi = d.id_divisi
              LEFT JOIN departemen dp ON u.departemen = dp.id_departemen
              WHERE u.id_user = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$userData = mysqli_fetch_assoc($user_result);
$jabatan_user = $userData['jabatan'] ?? '-';

?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<head>

    <style>
        .required {
            color: #dc3545;
        }

        .form-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .priority-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-rendah {
            background: #d4edda;
            color: #155724;
        }

        .priority-sedang {
            background: #fff3cd;
            color: #856404;
        }

        .priority-tinggi {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-urgent {
            background: #721c24;
            color: white;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title h4 h3-md">Tambah Pengajuan Asset Baru</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Pengajuan Asset</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Pengajuan</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body px-3 px-md-4">
                                <form action="add_insert.php" method="POST" enctype="multipart/form-data" id="formPengajuan">

                                    <!-- SECTION 1: Data Pengaju -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-user"></i> Data Pengaju
                                        </h5>
                                        <div class="row">
                                            <!-- Nama PIC -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama PIC <span class="required">*</span></label>
                                                    <input type="text" name="nama_pic" class="form-control"
                                                        value="<?= htmlspecialchars($_SESSION['nama_user']) ?>"
                                                        placeholder="Nama Person In Charge" readonly>
                                                </div>
                                            </div>

                                            <!-- Jabatan (otomatis dari user_dfm) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jabatan <span class="required">*</span></label>
                                                    <input type="text" name="jabatan" class="form-control"
                                                        value="<?= htmlspecialchars($jabatan_user) ?>" readonly>
                                                    <small class="text-muted">Jabatan diambil otomatis dari profil user</small>
                                                </div>
                                            </div>

                                            <!-- Divisi dari user profile (dengan JOIN) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Divisi <span class="required">*</span></label>
                                                    <input type="text" class="form-control" 
                                                        value="<?= htmlspecialchars($userData['nama_divisi'] ?? 'Tidak ada divisi') ?>" 
                                                        readonly>
                                                    <input type="hidden" name="divisi" value="<?= $userData['divisi'] ?>">
                                                    <small class="text-muted">Divisi diambil otomatis dari profil user</small>
                                                </div>
                                            </div>

                                            <!-- Nomor Pengajuan (Auto) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nomor Pengajuan</label>
                                                    <input type="text" name="no_pengajuan" class="form-control"
                                                        value="REQ-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                                                        readonly>
                                                    <small class="text-muted">Otomatis dibuat sistem</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 2: Detail Kebutuhan Asset -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-box"></i> Detail Kebutuhan Asset
                                        </h5>
                                        <div class="row">
                                            <!-- Kategori dari database -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Kategori Asset <span class="required">*</span></label>
                                                    <select name="kategori_asset" class="form-control" required>
                                                        <option value="">-- Pilih Kategori Asset --</option>
                                                        <?php
                                                        mysqli_data_seek($qKategori, 0);
                                                        while ($kategori = mysqli_fetch_assoc($qKategori)):
                                                        ?>
                                                            <option value="<?= htmlspecialchars($kategori['id_kategori']) ?>">
                                                                <?= htmlspecialchars($kategori['kategori_asset']) ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Jenis Pengadaan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jenis Pengadaan <span class="required">*</span></label>
                                                    <select name="jenis_pengadaan" class="form-control" required>
                                                        <option value="">-- Pilih Jenis Pengadaan --</option>
                                                        <option value="Penambahan">Penambahan</option>
                                                        <option value="Penggantian">Penggantian</option>
                                                        <option value="Upgrade">Upgrade</option>
                                                        <option value="Maintenance">Maintenance</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Deskripsi Asset -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Deskripsi Asset Secara Spesifik <span class="required">*</span></label>
                                                    <textarea name="deskripsi_asset" class="form-control" rows="4"
                                                        placeholder="Berikan deskripsi detail tentang asset yang dibutuhkan..." required></textarea>
                                                </div>
                                            </div>

                                            <!-- Spesifikasi Merek/Model -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Spesifikasi Merek atau Model Tertentu</label>
                                                    <textarea name="spesifikasi_merek" class="form-control" rows="3"
                                                        placeholder="Apakah ada merek atau model tertentu yang diinginkan? Sebutkan alasannya..."></textarea>
                                                    <small class="text-muted">Kosongkan jika tidak ada preferensi khusus</small>
                                                </div>
                                            </div>

                                            <!-- Estimasi Harga -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Estimasi Harga (Jika ada)</label>
                                                    <input type="text" name="estimasi_harga" class="form-control"
                                                        placeholder="Contoh: Rp 5.000.000" oninput="formatRupiah(this)">
                                                    <small class="text-muted">Kosongkan jika tidak mengetahui harga</small>
                                                </div>
                                            </div>

                                            <!-- Jumlah -->
                                            <div class="col-12 col-md-3 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jumlah <span class="required">*</span></label>
                                                    <input type="number" name="jumlah" class="form-control"
                                                        value="1" min="1" required>
                                                </div>
                                            </div>

                                            <!-- Satuan -->
                                            <div class="col-12 col-md-3 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Satuan</label>
                                                    <select name="satuan" class="form-control">
                                                        <option value="unit">Unit</option>
                                                        <option value="set">Set</option>
                                                        <option value="buah">Buah</option>
                                                        <option value="pcs">Pcs</option>
                                                        <option value="license">License</option>
                                                        <option value="paket">Paket</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 3: Kebutuhan & Justifikasi -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-clipboard-list"></i> Kebutuhan & Justifikasi
                                        </h5>
                                        <div class="row">
                                            <!-- Alasan/Kebutuhan Pengajuan -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Alasan / Kebutuhan Pengajuan <span class="required">*</span></label>
                                                    <textarea name="alasan_pengajuan" class="form-control" rows="4"
                                                        placeholder="Jelaskan mengapa asset ini dibutuhkan..." required></textarea>
                                                </div>
                                            </div>

                                            <!-- Tingkat Kepentingan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Prioritas Pengajuan <span class="required">*</span></label>
                                                    <select name="tingkat_kepentingan" class="form-control" required onchange="updatePriorityBadge()">
                                                        <option value="">-- Pilih Prioritas Pengajuan --</option>
                                                        <option value="Rendah">Rendah - Bisa ditunda</option>
                                                        <option value="Sedang">Sedang - Diperlukan dalam 1-3 bulan</option>
                                                        <option value="Tinggi">Tinggi - Diperlukan segera</option>
                                                        <option value="Urgent">Urgent - Sangat mendesak</option>
                                                    </select>
                                                    <div id="priorityBadge" class="mt-2"></div>
                                                </div>
                                            </div>

                                            <!-- Tanggal Dibutuhkan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tanggal Dibutuhkan <span class="required">*</span></label>
                                                    <input type="date" name="tanggal_dibutuhkan" class="form-control"
                                                        min="<?= date('Y-m-d') ?>" required>
                                                </div>
                                            </div>

                                            <!-- Lokasi dari database -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Pilih Cabang <span class="required">*</span></label>
                                                    <select name="lokasi_penggunaan" class="form-control" required>
                                                        <option value="">-- Pilih Cabang --</option>
                                                        <?php
                                                        mysqli_data_seek($qLokasi, 0);
                                                        while ($lokasi = mysqli_fetch_assoc($qLokasi)):
                                                        ?>
                                                            <option value="<?= htmlspecialchars($lokasi['id_lokasi']) ?>">
                                                                <?= htmlspecialchars($lokasi['lokasi_asset']) ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Detail Lokasi -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Detail Lokasi</label>
                                                    <input type="text" name="detail_lokasi" class="form-control"
                                                        placeholder="Contoh: Lantai 2, Ruang IT">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 4: File Attachment -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-paperclip"></i> Lampiran Pendukung
                                        </h5>
                                        <div class="row">
                                            <!-- File Upload -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Upload File Pendukung</label>
                                                    <input type="file" name="file_attachment" class="form-control"
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                                    <small class="text-muted">
                                                        Format: PDF, DOC, XLS, JPG, PNG. Maksimal 5MB.
                                                        <br>Bisa berupa katalog produk, spesifikasi, atau dokumen pendukung lainnya.
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Catatan Tambahan -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Catatan Tambahan</label>
                                                    <textarea name="catatan_tambahan" class="form-control" rows="3"
                                                        placeholder="Informasi tambahan yang perlu diketahui..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden fields untuk user tracking -->
                                    <input type="hidden" name="user_id" value="<?= $_SESSION['id_user'] ?>">
                                    <input type="hidden" name="tanggal_pengajuan" value="<?= date('Y-m-d') ?>">

                                    <!-- SECTION 5: Submit Buttons -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex flex-column flex-sm-row justify-content-end">
                                                <a href="dashboard.php" class="btn btn-secondary mb-2 mb-sm-0 mr-sm-2">
                                                    <i class="fas fa-times"></i> Batal
                                                </a>
                                                <button type="submit" name="submit_pengajuan" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Submit Pengajuan
                                                </button>
                                            </div>
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
        // Format Rupiah
        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, "").toString();
            let split = value.split(",");
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? "." : "";
                rupiah += separator + ribuan.join(".");
            }

            rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
            input.value = rupiah ? "Rp " + rupiah : "";
        }

        // Update Priority Badge
        function updatePriorityBadge() {
            const select = document.querySelector('select[name="tingkat_kepentingan"]');
            const badge = document.getElementById('priorityBadge');
            const value = select.value;

            let badgeClass = '';
            let text = '';

            switch (value) {
                case 'Rendah':
                    badgeClass = 'priority-rendah';
                    text = '🟢 RENDAH - Bisa ditunda';
                    break;
                case 'Sedang':
                    badgeClass = 'priority-sedang';
                    text = '🟡 SEDANG - Diperlukan dalam 1-3 bulan';
                    break;
                case 'Tinggi':
                    badgeClass = 'priority-tinggi';
                    text = '🟠 TINGGI - Diperlukan segera';
                    break;
                case 'Urgent':
                    badgeClass = 'priority-urgent';
                    text = '🔴 URGENT - Sangat mendesak';
                    break;
                default:
                    badge.innerHTML = '';
                    return;
            }

            badge.innerHTML = `<span class="priority-badge ${badgeClass}">${text}</span>`;
        }

        // PERBAIKAN: Set minimum date to today (hari ini), bukan tomorrow
        document.querySelector('input[name="tanggal_dibutuhkan"]').min = new Date().toISOString().split('T')[0];

        // Form validation
        document.getElementById('formPengajuan').addEventListener('submit', function(e) {
            const estimasi = document.querySelector('input[name="estimasi_harga"]').value;
            const tanggal = new Date(document.querySelector('input[name="tanggal_dibutuhkan"]').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Set ke awal hari
            tanggal.setHours(0, 0, 0, 0); // Set ke awal hari

            // PERBAIKAN: Validate date - boleh hari ini atau setelahnya
            if (tanggal < today) {
                e.preventDefault();
                alert('Tanggal dibutuhkan tidak boleh kurang dari hari ini!');
                return false;
            }
        });
    </script>
</body>

</html>
<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\service_complete.php
include('../dist/config/config.php');

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// Cek ID service
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php?error=id_not_found");
    exit();
}

$service_id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil data service
$query = mysqli_query($conn, "
    SELECT s.*, a.nama_aset, a.merk, a.kode_aset, a.lokasi
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset = a.kode_aset
    WHERE s.id_service = '$service_id' AND s.status_service = 'Service'
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) == 0) {
    header("Location: dashboard.php?error=service_not_found");
    exit();
}

$service = mysqli_fetch_assoc($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selesaikan Service - DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Selesaikan Service</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Service Asset</a>
                                </li>
                                <li class="breadcrumb-item active">Selesaikan Service</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-check-circle"></i> Form Penyelesaian Service
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- PERBAIKAN: Form diarahkan ke service_complete_process.php -->
                                <form method="POST" action="service_complete_process.php"
                                    enctype="multipart/form-data" id="formSelesai">

                                    <!-- Hidden field untuk ID service -->
                                    <input type="hidden" name="id_service" value="<?= $service['id_service'] ?>">

                                    <div class="row">
                                        <!-- Info Service -->
                                        <div class="col-md-12">
                                            <div class="alert alert-info">
                                                <h6><i class="fas fa-info-circle"></i> Informasi Service</h6>
                                                <table class="table table-borderless table-sm mb-0">
                                                    <tr>
                                                        <td width="30%">No. Service</td>
                                                        <td>: <strong><?= htmlspecialchars($service['no_service']) ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Asset</td>
                                                        <td>: <strong><?= htmlspecialchars($service['nama_aset']) ?></strong> (<?= htmlspecialchars($service['kode_aset']) ?>)</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Jenis Service</td>
                                                        <td>: <strong><?= htmlspecialchars($service['jenis_service']) ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Tanggal Mulai</td>
                                                        <td>: <strong><?= date('d-m-Y', strtotime($service['tanggal_service'])) ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Lokasi</td>
                                                        <td>: <strong><?= htmlspecialchars($service['lokasi']) ?></strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Tanggal Selesai -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tanggal_selesai">Tanggal Selesai <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_selesai" id="tanggal_selesai"
                                                    class="form-control" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>

                                        <!-- Biaya Service -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="biaya_service">Biaya Service <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">Rp</span>
                                                    </div>
                                                    <input type="text" name="biaya_service" id="biaya_service"
                                                        class="form-control currency-input" placeholder="0" required>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PERBAIKAN: Upload Foto Nota -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="foto_nota">
                                                    <i class="fas fa-receipt"></i> Upload Foto Nota/Struk
                                                </label>
                                                <input type="file" name="foto_nota" id="foto_nota"
                                                    class="form-control"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                                <small class="form-text text-muted">
                                                    Format: JPG, JPEG, PNG, GIF, WEBP. Maksimal 5MB (Opsional)
                                                </small>

                                                <!-- Preview foto nota -->
                                                <div id="notaPreview" style="margin-top: 10px; display: none;">
                                                    <img id="previewNota" src=""
                                                        style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Keterangan -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="keterangan">Keterangan Tambahan</label>
                                                <textarea name="keterangan" id="keterangan" class="form-control" rows="3"
                                                    placeholder="Keterangan tambahan (opsional)"></textarea>
                                            </div>
                                        </div>

                                        <!-- Submit -->
                                        <div class="col-md-12 text-right">
                                            <a href="dashboard.php" class="btn btn-secondary mr-2">
                                                <i class="fas fa-times"></i> Batal
                                            </a>
                                            <button type="submit" name="submit" class="btn btn-success">
                                                <i class="fas fa-check-circle"></i> Selesaikan Service
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Info Panel -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Perhatian</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Pastikan service sudah benar-benar selesai
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Isi biaya service dengan akurat
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Upload foto nota jika diperlukan
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Jelaskan tindakan yang dilakukan
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success"></i>
                                        Setelah diselesaikan, status tidak dapat diubah
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="card mt-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-clock"></i> Durasi Service</h6>
                            </div>
                            <div class="card-body text-center">
                                <?php
                                $durasi = (strtotime(date('Y-m-d')) - strtotime($service['tanggal_service'])) / (60 * 60 * 24);
                                $durasi_class = $durasi > 7 ? 'text-danger' : ($durasi > 3 ? 'text-warning' : 'text-success');
                                ?>
                                <h3 class="<?= $durasi_class ?>"><?= (int)$durasi ?></h3>
                                <p class="text-muted mb-0">hari</p>
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
            // Format currency input
            $('.currency-input').on('input', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                this.value = formatCurrency(value);
            });

            // Preview foto nota
            $('#foto_nota').change(function(event) {
                const file = event.target.files[0];
                const preview = $('#previewNota');
                const previewDiv = $('#notaPreview');

                if (file) {
                    // Validasi ukuran file
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 5MB.');
                        this.value = '';
                        previewDiv.hide();
                        return;
                    }

                    // Validasi tipe file
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan: JPG, JPEG, PNG, GIF, atau WEBP');
                        this.value = '';
                        previewDiv.hide();
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.attr('src', e.target.result);
                        previewDiv.show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewDiv.hide();
                }
            });

            // Validasi form
            $('#formSelesai').on('submit', function(e) {
                const biaya = $('#biaya_service').val().replace(/[^0-9]/g, '');
                const tindakan = $('#tindakan').val();

                if (!biaya || parseInt(biaya) <= 0) {
                    e.preventDefault();
                    alert('Harap masukkan biaya service yang valid!');
                    $('#biaya_service').focus();
                    return false;
                }

                if (!tindakan.trim()) {
                    e.preventDefault();
                    alert('Harap jelaskan tindakan yang dilakukan!');
                    $('#tindakan').focus();
                    return false;
                }

                // Konfirmasi
                const confirmMsg = `Apakah Anda yakin ingin menyelesaikan service ini?\n\n`;

                if (!confirm(confirmMsg + detailMsg + biayaMsg + 'Status tidak dapat diubah setelah diselesaikan.')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Set minimum date
            document.getElementById('tanggal_selesai').min = '<?= $service['tanggal_service'] ?>';
        });

        // Function untuk format currency
        function formatCurrency(value) {
            return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    </script>
</body>

</html>
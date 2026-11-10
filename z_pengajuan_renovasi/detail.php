<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\detail.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek parameter no_pengajuan atau id
$param = trim($_GET['no_pengajuan'] ?? $_GET['id'] ?? '');
if (empty($param)) {
    $_SESSION['error'] = 'Nomor pengajuan tidak valid!';
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

// Cek dan tambahkan kolom rejected_by jika belum ada di database server
$chk_col = mysqli_query($conn, "SHOW COLUMNS FROM renovation_request LIKE 'rejected_by'");
$has_rejected_by = ($chk_col && mysqli_num_rows($chk_col) > 0);
if (!$has_rejected_by) {
    @mysqli_query($conn, "ALTER TABLE renovation_request ADD COLUMN rejected_by INT(11) NULL AFTER approved_at, ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by");
    $chk_col2 = mysqli_query($conn, "SHOW COLUMNS FROM renovation_request LIKE 'rejected_by'");
    $has_rejected_by = ($chk_col2 && mysqli_num_rows($chk_col2) > 0);
}

// Tentukan apakah query menggunakan id (numeric) atau no_pengajuan (string)
$is_num = is_numeric($param);
$where_cond = $is_num ? "(rr.id = ? OR rr.no_pengajuan = ?)" : "rr.no_pengajuan = ?";

// Query dinamis yang aman dari error kolom tidak ditemukan
if ($has_rejected_by) {
    $query = "SELECT rr.*,
                     ua.nama_user as nama_approver,
                     ur.nama_user as nama_rejector
              FROM renovation_request rr
              LEFT JOIN user_dfm ua ON rr.approved_by = ua.id_user
              LEFT JOIN user_dfm ur ON rr.rejected_by = ur.id_user
              WHERE $where_cond";
} else {
    $query = "SELECT rr.*,
                     ua.nama_user as nama_approver,
                     NULL as nama_rejector
              FROM renovation_request rr
              LEFT JOIN user_dfm ua ON rr.approved_by = ua.id_user
              WHERE $where_cond";
}

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    // Fallback query paling sederhana jika prepare gagal
    $safe_where = $is_num ? "(id = ? OR no_pengajuan = ?)" : "no_pengajuan = ?";
    $safe_query = "SELECT *, NULL as nama_approver, NULL as nama_rejector FROM renovation_request WHERE $safe_where";
    $stmt = mysqli_prepare($conn, $safe_query);
}

$data = null;
if ($stmt) {
    if ($is_num) {
        mysqli_stmt_bind_param($stmt, "ss", $param, $param);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $param);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

// Jika data tetap tidak ada lewat prepare, coba direct query
if (!$data) {
    $esc = mysqli_real_escape_string($conn, $param);
    $direct_where = $is_num ? "(id = '$esc' OR no_pengajuan = '$esc')" : "no_pengajuan = '$esc'";
    $direct = mysqli_query($conn, "SELECT * FROM renovation_request WHERE $direct_where");
    if ($direct && mysqli_num_rows($direct) > 0) {
        $data = mysqli_fetch_assoc($direct);
    }
}

if (!$data) {
    $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = $data['no_pengajuan'];

// DEBUG: Cek data foto
echo "<!-- DEBUG: foto_area = " . htmlspecialchars($data['foto_area'] ?? 'NULL') . " -->";

// Parse foto dari JSON
$foto_array = [];
if (!empty($data['foto_area'])) {
    $foto_array = json_decode($data['foto_area'], true) ?: [];
    // DEBUG: Cek hasil parsing
    echo "<!-- DEBUG: foto_array count = " . count($foto_array) . " -->";
}

// Function untuk status badge
function getStatusBadge($status)
{
    switch ($status) {
        case 'Pending':
            return '<span class="badge badge-warning">Pending</span>';
        case 'Approved':
        case 'Disetujui':
            return '<span class="badge badge-success">Disetujui</span>';
        case 'Rejected':
        case 'Ditolak':
            return '<span class="badge badge-danger">Ditolak</span>';
        case 'In Progress':
            return '<span class="badge badge-info">In Progress</span>';
        case 'Completed':
        case 'Selesai':
            return '<span class="badge badge-primary">Selesai</span>';
        default:
            return '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

// Function untuk prioritas badge
function getPriorityBadge($prioritas)
{
    switch ($prioritas) {
        case 'Rendah':
            return '<span class="badge badge-light text-dark">Rendah</span>';
        case 'Sedang':
            return '<span class="badge badge-warning">Sedang</span>';
        case 'Tinggi':
            return '<span class="badge badge-danger">Tinggi</span>';
        case 'Urgent':
            return '<span class="badge badge-danger">Urgent</span>';
        default:
            return '<span class="badge badge-secondary">' . htmlspecialchars($prioritas) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Pengajuan Renovasi</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .foto-renovasi {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .foto-renovasi:hover {
            transform: scale(1.05);
            border-color: #4e73df;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .no-photo {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            color: #6c757d;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
        }

        /* Modal untuk foto */
        .modal-foto {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-foto-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            max-height: 80%;
            object-fit: contain;
            margin-top: 5%;
        }

        .modal-foto-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-foto-close:hover {
            color: #bbb;
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
                            <h3 class="page-title">Detail Pengajuan Renovasi</h3>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left fa-sm"></i> Kembali
                            </a>
                            <button onclick="window.print()" class="btn btn-info btn-sm">
                                <i class="fas fa-print fa-sm"></i> Print
                            </button>
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

                <!-- Detail Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-info-circle fa-sm"></i>
                                            <?= htmlspecialchars($data['no_pengajuan']) ?>
                                        </h5>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <?= getStatusBadge($data['status']) ?>
                                        <?= getPriorityBadge($data['prioritas']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Informasi Dasar -->
                                    <div class="col-lg-6">
                                        <h6 class="text-muted mb-3">Informasi Dasar</h6>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">No. Pengajuan:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['no_pengajuan']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Nama PIC:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['nama_pic']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Jabatan:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['jabatan']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Kantor Cabang:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['kantor_cabang']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Area Renovasi:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['area_renovasi']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detail Renovasi -->
                                    <div class="col-lg-6">
                                        <h6 class="text-muted mb-3">Detail Renovasi</h6>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Jenis Pekerjaan:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= htmlspecialchars($data['jenis_pekerjaan']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Prioritas:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= getPriorityBadge($data['prioritas']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Status:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= getStatusBadge($data['status']) ?></p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Tanggal Pengajuan:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= date('d F Y, H:i', strtotime($data['tanggal_pengajuan'])) ?> WIB</p>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label font-weight-bold">Dibuat Pada:</label>
                                            <div class="col-sm-8">
                                                <p class="form-control-plaintext"><?= date('d F Y, H:i', strtotime($data['created_at'])) ?> WIB</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($data['status'] === 'Ditolak'): ?>
                                    <hr class="my-4">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-danger mb-0">
                                                <h6 class="font-weight-bold mb-2"><i class="fas fa-times-circle mr-1"></i> Informasi Penolakan</h6>
                                                <p class="mb-1"><strong>Ditolak oleh:</strong> <?= htmlspecialchars($data['nama_rejector'] ?? $data['nama_approver'] ?? 'Verifikator') ?></p>
                                                <?php if (!empty($data['rejected_at']) || !empty($data['approved_at'])): ?>
                                                    <p class="mb-1"><strong>Tanggal Penolakan:</strong> <?= date('d F Y, H:i', strtotime($data['rejected_at'] ?? $data['approved_at'])) ?> WIB</p>
                                                <?php endif; ?>
                                                <?php if (!empty($data['catatan_approval'])): ?>
                                                    <p class="mb-0"><strong>Alasan Penolakan:</strong> <?= nl2br(htmlspecialchars($data['catatan_approval'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($data['status'] === 'Disetujui'): ?>
                                    <hr class="my-4">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-success mb-0">
                                                <h6 class="font-weight-bold mb-2"><i class="fas fa-check-circle mr-1"></i> Informasi Persetujuan</h6>
                                                <p class="mb-1"><strong>Disetujui oleh:</strong> <?= htmlspecialchars($data['nama_approver'] ?? 'Verifikator') ?></p>
                                                <?php if (!empty($data['approved_at'])): ?>
                                                    <p class="mb-1"><strong>Tanggal Persetujuan:</strong> <?= date('d F Y, H:i', strtotime($data['approved_at'])) ?> WIB</p>
                                                <?php endif; ?>
                                                <?php if (!empty($data['catatan_approval'])): ?>
                                                    <p class="mb-0"><strong>Catatan:</strong> <?= nl2br(htmlspecialchars($data['catatan_approval'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <hr class="my-4">

                                <!-- Alasan Renovasi -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="text-muted mb-3">Alasan dan Tujuan Renovasi</h6>
                                        <div class="form-group">
                                            <div class="border p-3 rounded bg-light">
                                                <?= nl2br(htmlspecialchars($data['alasan_renovasi'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Foto Area -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="text-muted mb-3">Foto Area Renovasi</h6>

                                        <?php if (!empty($foto_array) && count($foto_array) > 0): ?>
                                            <div class="row">
                                                <?php foreach ($foto_array as $index => $foto): ?>
                                                    <div class="col-md-3 col-sm-4 col-6 mb-3">
                                                        <div class="text-center">
                                                            <?php if (file_exists("../uploads/renovasi/" . $foto)): ?>
                                                                <img src="../uploads/renovasi/<?= htmlspecialchars($foto) ?>"
                                                                    class="foto-renovasi"
                                                                    alt="Foto <?= $index + 1 ?>"
                                                                    onclick="openModal(this.src)"
                                                                    title="Klik untuk memperbesar">
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Foto <?= $index + 1 ?></small>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="foto-renovasi d-flex align-items-center justify-content-center"
                                                                    style="background: #f8f9fa; border: 2px dashed #dee2e6;">
                                                                    <div class="text-center text-muted">
                                                                        <i class="fas fa-image fa-2x mb-2"></i>
                                                                        <br><small>File tidak ditemukan</small>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="no-photo">
                                                <i class="fas fa-camera fa-3x mb-3"></i>
                                                <h5>Tidak ada foto</h5>
                                                <p class="text-muted">Belum ada foto area renovasi yang diupload</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk foto -->
    <div id="fotoModal" class="modal-foto">
        <span class="modal-foto-close" onclick="closeModal()">&times;</span>
        <img class="modal-foto-content" id="modalImg">
    </div>

    <script>
        // Function untuk membuka modal foto
        function openModal(src) {
            document.getElementById('fotoModal').style.display = 'block';
            document.getElementById('modalImg').src = src;
        }

        // Function untuk menutup modal foto
        function closeModal() {
            document.getElementById('fotoModal').style.display = 'none';
        }

        // Tutup modal ketika klik di luar foto
        window.onclick = function(event) {
            const modal = document.getElementById('fotoModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Tutup modal dengan tombol ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Print function
        window.onbeforeprint = function() {
            // Hide buttons saat print
            document.querySelectorAll('.btn').forEach(function(btn) {
                btn.style.display = 'none';
            });
        }

        window.onafterprint = function() {
            // Show buttons setelah print
            document.querySelectorAll('.btn').forEach(function(btn) {
                btn.style.display = 'inline-block';
            });
        }
    </script>

</body>

</html>
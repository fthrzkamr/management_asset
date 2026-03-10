<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\delete.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Anda harus login terlebih dahulu!';
    header("Location: ../login.php");
    exit();
}

// Cek permission
$auth->requirePermission('pengajuan', 'delete');

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'ID pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$no_ppx = $_GET['id'];

// Ambil data pengajuan dari tabel ppx_request
$query = "SELECT * FROM ppx_request WHERE no_ppx = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $no_ppx);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengajuan = mysqli_fetch_assoc($result);

if (!$pengajuan) {
    $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// ATURAN DELETE BERDASARKAN ROLE
$can_delete = false;
$error_message = '';

if ($auth->isSuperAdmin()) {
    // Super Admin bisa delete semua status
    $can_delete = true;
} else {
    // Karyawan dan Staff - cek kepemilikan dan status
    $user_id = $_SESSION['id_user'];
    
    // Cek apakah pengajuan milik user ini (untuk karyawan)
    if ($auth->isKaryawan() && $pengajuan['user_id'] != $user_id) {
        $error_message = 'Anda hanya bisa menghapus pengajuan milik Anda sendiri!';
    } else {
        // Untuk PPX, hanya pengajuan dengan status "Revisi" yang dapat dihapus oleh user biasa
        if (strtolower(trim($pengajuan['status'])) == 'revisi') {
            $can_delete = true;
        } else {
            $status_text = [
                'Leader' => 'Leader',
                'Manager' => 'Manager',
                'Finance' => 'Finance',
                'Direktur' => 'Direktur',
                'Done' => 'Selesai',
                'Revisi' => 'Revisi'
            ];
            $error_message = 'Pengajuan yang berstatus "' . ($status_text[$pengajuan['status']] ?? $pengajuan['status']) . '" tidak dapat dihapus!';
        }
    }
}

// Jika tidak bisa delete, redirect dengan error
if (!$can_delete) {
    $_SESSION['error'] = $error_message;
    header("Location: dashboard.php");
    exit();
}

// Proses delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    
    mysqli_begin_transaction($conn);
    
    try {
        // Hapus detail ppx terlebih dahulu
        $delete_detail = "DELETE FROM ppx_request_detail WHERE no_ppx = ?";
        $stmt_detail = mysqli_prepare($conn, $delete_detail);
        mysqli_stmt_bind_param($stmt_detail, "s", $no_ppx);
        mysqli_stmt_execute($stmt_detail);
        
        // Hapus ppx_request utama
        $delete_ppx = "DELETE FROM ppx_request WHERE no_ppx = ?";
        $stmt_ppx = mysqli_prepare($conn, $delete_ppx);
        mysqli_stmt_bind_param($stmt_ppx, "s", $no_ppx);
        mysqli_stmt_execute($stmt_ppx);
        
        // (Catatan) Log activity akan dilakukan setelah commit agar kegagalan logging
        // tidak membatalkan penghapusan utama.
        
        mysqli_commit($conn);
        
        // Coba log aktivitas secara terpisah; jika tabel tidak ada atau gagal, abaikan error.
        try {
            // Pastikan tabel activity_log ada di database saat ini
            $tbl_check = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'activity_log'");
            if ($tbl_check) {
                $tbl_row = mysqli_fetch_assoc($tbl_check);
                if (!empty($tbl_row['cnt']) && (int)$tbl_row['cnt'] > 0) {
                    $description = "Menghapus pengajuan PPX: " . $no_ppx;
                    if ($stmt_log = mysqli_prepare($conn, "INSERT INTO activity_log (id_user, activity, description, created_at) VALUES (?, 'DELETE', ?, NOW())")) {
                        mysqli_stmt_bind_param($stmt_log, "is", $_SESSION['id_user'], $description);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    }
                }
            }
        } catch (Throwable $logEx) {
            // Jangan ganggu flow utama jika logging gagal; catat saja
            error_log('activity_log insert failed: ' . $logEx->getMessage());
        }
        
        $_SESSION['success'] = 'Pengajuan berhasil dihapus!';
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = 'Gagal menghapus pengajuan: ' . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hapus Pengajuan PPX</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title"><i class="fas fa-trash-alt text-danger fa-sm"></i> Hapus Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan PPX</a></li>
                                <li class="breadcrumb-item active">Hapus</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle fa-sm"></i> Konfirmasi Penghapusan</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle fa-sm"></i>
                                    <strong>Peringatan!</strong> Tindakan ini tidak dapat dibatalkan. Semua data terkait pengajuan ini akan dihapus secara permanen.
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="font-weight-bold">No. PPX</td>
                                                <td>: <?= htmlspecialchars($pengajuan['no_ppx']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-bold">Tanggal</td>
                                                <td>: <?= date('d/m/Y', strtotime($pengajuan['tanggal_pengajuan'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-bold">Status</td>
                                                <td>:
                                                    <?php
                                                    $status_class = [
                                                        'Leader' => 'primary',
                                                        'Manager' => 'info',
                                                        'Finance' => 'warning',
                                                        'Direktur' => 'secondary',
                                                        'Done' => 'success',
                                                        'Revisi' => 'danger'
                                                    ];
                                                    $status_text = [
                                                        'Leader' => 'Leader',
                                                        'Manager' => 'Manager',
                                                        'Finance' => 'Finance',
                                                        'Direktur' => 'Direktur',
                                                        'Done' => 'Selesai',
                                                        'Revisi' => 'Revisi'
                                                    ];
                                                    $st = $pengajuan['status'];
                                                    ?>
                                                    <span class="badge badge-<?= $status_class[$st] ?? 'secondary' ?>">
                                                        <?= $status_text[$st] ?? htmlspecialchars($st) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <td class="font-weight-bold">Nama PIC</td>
                                                <td>: <?= htmlspecialchars($pengajuan['nama_pic'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-weight-bold">Tingkat Kepentingan</td>
                                                <td>: <?= htmlspecialchars($pengajuan['tingkat_kepentingan'] ?? '') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <?php if ($auth->isSuperAdmin()): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle fa-sm"></i>
                                        <strong>Super Admin:</strong> Anda dapat menghapus pengajuan dengan status apapun.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle fa-sm"></i>
                                        <strong>Info:</strong> Anda hanya dapat menghapus pengajuan dengan status "Revisi".
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="confirm_checkbox" required>
                                            <label class="custom-control-label" for="confirm_checkbox">
                                                Saya yakin ingin menghapus pengajuan ini dan memahami bahwa tindakan ini tidak dapat dibatalkan.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left fa-xs"></i> Kembali</a>
                                        <button type="submit" name="confirm_delete" class="btn btn-danger ml-2" id="delete_btn" disabled>
                                            <i class="fas fa-trash-alt fa-xs"></i> Hapus Pengajuan
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
        document.getElementById('confirm_checkbox').addEventListener('change', function() {
            document.getElementById('delete_btn').disabled = !this.checked;
        });
    </script>

</body>
</html>
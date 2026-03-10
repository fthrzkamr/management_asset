<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine base path for includes
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

// Create auth instance
$auth = new AuthHelper($conn);

// Check if user is logged in and has permission
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = "Anda harus login terlebih dahulu!";
    header("Location: ../login.php");
    exit();
}
if ($auth->hasPermission('inventory_atk_master', 'delete') || $auth->hasPermission('pt_bst_inventory', 'update')) {
    // Access granted
} else {
    $auth->requirePermission('inventory_atk_master', 'delete'); 
}

// Get id_barang
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID Barang tidak valid!";
    header("Location: inventory_list.php");
    exit();
}

$id_barang = intval($_GET['id']);

// Get barang data (Join with stock)
$sql = "SELECT b.*, COALESCE(s.quantity, 0) as stok_aktual 
        FROM inventory_atk b
        LEFT JOIN inventory_stok s ON b.id_barang = s.id_barang
        WHERE b.id_barang = $id_barang";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Barang tidak ditemukan!";
    header("Location: inventory_list.php");
    exit();
}

$barang = mysqli_fetch_assoc($result);
$nama_barang = $barang['nama_barang'];

// No direct delete here, let the user confirm below
// (Removing the direct delete at the top of file that was causing logic issues)


// Check if item is used in transactions
$check_pengambilan = "SELECT COUNT(*) as count FROM atk_pengambilan_detail WHERE id_barang = $id_barang";
$check_masuk = "SELECT COUNT(*) as count FROM atk_barang_masuk WHERE id_barang = $id_barang";
$check_keluar = "SELECT COUNT(*) as count FROM atk_barang_keluar WHERE id_barang = $id_barang";

$pengambilan_count = mysqli_fetch_assoc(mysqli_query($conn, $check_pengambilan))['count'];
$masuk_count = mysqli_fetch_assoc(mysqli_query($conn, $check_masuk))['count'];
$keluar_count = mysqli_fetch_assoc(mysqli_query($conn, $check_keluar))['count'];

$has_transactions = ($pengambilan_count > 0 || $masuk_count > 0 || $keluar_count > 0);

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    if ($has_transactions) {
        // If has transactions, only set status to Nonaktif
        $update_sql = "UPDATE inventory_atk SET status = 'Nonaktif' WHERE id_barang = $id_barang";
        if (mysqli_query($conn, $update_sql)) {
            // Log activity
            $log_sql = "INSERT INTO atk_activity_log (activity_type, reference_id, reference_table, description, user_id, ip_address) 
                        VALUES ('stok_update', $id_barang, 'inventory_atk', 'Nonaktifkan barang: {$barang['nama_barang']}', '{$_SESSION['id_user']}', '{$_SERVER['REMOTE_ADDR']}')";
            mysqli_query($conn, $log_sql);
            
            $_SESSION['success_message'] = "Barang berhasil dinonaktifkan (tidak bisa dihapus karena memiliki riwayat transaksi)";
        } else {
            $_SESSION['error_message'] = "Gagal menonaktifkan barang: " . mysqli_error($conn);
        }
    } else {
        // If no transactions, delete permanently
        $delete_sql = "DELETE FROM inventory_atk WHERE id_barang = $id_barang";
        if (mysqli_query($conn, $delete_sql)) {
            // Log activity
            $log_sql = "INSERT INTO atk_activity_log (activity_type, reference_id, reference_table, description, user_id, ip_address) 
                        VALUES ('stok_update', $id_barang, 'inventory_atk', 'Hapus barang: {$barang['nama_barang']}', '{$_SESSION['id_user']}', '{$_SERVER['REMOTE_ADDR']}')";
            mysqli_query($conn, $log_sql);
            
            $_SESSION['success_message'] = "Barang berhasil dihapus!";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus barang: " . mysqli_error($conn);
        }
    }
    
    header("Location: inventory_list.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Barang ATK - DFM</title>
    
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/dua.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Hapus Barang ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="inventory_list.php">Inventory ATK</a></li>
                                <li class="breadcrumb-item active">Hapus Barang</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Confirmation -->
                <div class="row">
                    <div class="col-lg-6 mx-auto">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Konfirmasi Hapus
                                </h4>
                            </div>
                            <div class="card-body">
                                <h5>Detail Barang:</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <td width="30%"><strong>Kode Barang</strong></td>
                                        <td><?= htmlspecialchars($barang['kode_barang']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama Barang</strong></td>
                                        <td><?= htmlspecialchars($barang['nama_barang']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kategori</strong></td>
                                        <td><?= htmlspecialchars($barang['kategori']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stok Saat Ini</strong></td>
                                        <td><?= $barang['stok_aktual'] ?> <?= $barang['satuan'] ?? '' ?></td>
                                    </tr>
                                </table>

                                <?php if ($has_transactions): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Perhatian:</strong>
                                    <p class="mb-1">Barang ini memiliki riwayat transaksi:</p>
                                    <ul class="mb-0">
                                        <?php if ($pengambilan_count > 0): ?>
                                        <li>Digunakan dalam <?= $pengambilan_count ?> pengajuan</li>
                                        <?php endif; ?>
                                        <?php if ($masuk_count > 0): ?>
                                        <li>Tercatat dalam <?= $masuk_count ?> barang masuk</li>
                                        <?php endif; ?>
                                        <?php if ($keluar_count > 0): ?>
                                        <li>Tercatat dalam <?= $keluar_count ?> barang keluar</li>
                                        <?php endif; ?>
                                    </ul>
                                    <p class="mt-2 mb-0">
                                        <strong>Barang tidak dapat dihapus permanent.</strong><br>
                                        Sistem akan mengubah status barang menjadi <strong>Nonaktif</strong>.
                                    </p>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Peringatan:</strong>
                                    <p class="mb-0">
                                        Barang ini akan dihapus <strong>secara permanen</strong>.<br>
                                        Tindakan ini tidak dapat dibatalkan!
                                    </p>
                                </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="form-group mb-0 text-center mt-4">
                                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> 
                                            <?= $has_transactions ? 'Nonaktifkan Barang' : 'Ya, Hapus Barang' ?>
                                        </button>
                                        <a href="inventory_list.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Batal
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../layouts/footer.php'; ?>

    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>

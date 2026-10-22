<?php
session_start();
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}
$auth->requirePermission('inventory_atk_stok', 'update');

$id_user = $_SESSION['id_user'];
$id_barang = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil info barang
$query = "SELECT a.*, COALESCE(s.quantity, 0) as current_qty 
          FROM inventory_atk a 
          LEFT JOIN inventory_stok s ON a.id_barang = s.id_barang 
          WHERE a.id_barang = $id_barang";
$result = mysqli_query($conn, $query);
$barang = mysqli_fetch_assoc($result);

if (!$barang) {
    die("Barang tidak ditemukan di master inventory.");
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qty_masuk'])) {
    $qty = intval($_POST['qty_masuk']);
    $tgl = mysqli_real_escape_string($conn, $_POST['tanggal_masuk']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan']);
    $no_ref = "ADD-" . date('Ymd-His') . "-" . rand(10, 99);

    if ($qty <= 0) {
        $error = "Kuantitas harus lebih dari 0!";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // 1. Simpan ke atk_barang_masuk
            $sql_masuk = "INSERT INTO atk_barang_masuk (nomor_masuk, tanggal_masuk, id_barang, qty_masuk, created_by, keterangan) 
                          VALUES ('$no_ref', '$tgl', $id_barang, $qty, '$id_user', '$catatan')";
            if (!mysqli_query($conn, $sql_masuk)) throw new Exception(mysqli_error($conn));

            // 2. Pastikan row di inventory_stok ada
            $check_stok = mysqli_query($conn, "SELECT id_barang FROM inventory_stok WHERE id_barang = $id_barang");
            if (mysqli_num_rows($check_stok) == 0) {
                mysqli_query($conn, "INSERT INTO inventory_stok (id_barang, quantity, created_by) VALUES ($id_barang, 0, '$id_user')");
            }
            
            // 3. Update quantity
            $sql_update = "UPDATE inventory_stok SET quantity = quantity + $qty WHERE id_barang = $id_barang";
            if (!mysqli_query($conn, $sql_update)) throw new Exception(mysqli_error($conn));

            // 4. Log Activity
            $act = "Menambah Stok ATK: " . $barang['nama_barang'] . " sebanyak " . $qty . " " . $barang['satuan'] . ". Ref: " . $no_ref;
            mysqli_query($conn, "INSERT INTO atk_activity_log (id_user, activity) VALUES ('$id_user', '$act')");

            mysqli_commit($conn);
            header("Location: stok_gudang.php?success=update");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Tambah Stok - Aplikasi Asset Management</title>
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../layouts/navbar.php'; ?>
        
        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Tambah Kuantitas Stok</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="stok_gudang.php">Stok Gudang</a></li>
                                <li class="breadcrumb-item active">Tambah Manual</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 offset-md-3">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0 text-white"><i class="fas fa-plus-circle"></i> Form Tambah Stok</h5>
                            </div>
                            <div class="card-body">
                                <?php if($error): ?>
                                    <div class="alert alert-danger"><?= $error ?></div>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <div class="row">
                                        <div class="col-6"><strong>Nama Barang:</strong></div>
                                        <div class="col-6 text-right"><?= htmlspecialchars($barang['nama_barang']) ?></div>
                                    </div>
                                    <div class="row mt-1">
                                        <div class="col-6"><strong>Stok Saat Ini:</strong></div>
                                        <div class="col-6 text-right"><span class="badge badge-dark"><?= $barang['current_qty'] ?> <?= htmlspecialchars($barang['satuan']) ?></span></div>
                                    </div>
                                </div>

                                <form method="POST">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Tanggal Masuk</label>
                                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="font-weight-bold">Jumlah Tambahan (Qty)</label>
                                        <div class="input-group">
                                            <input type="number" name="qty_masuk" class="form-control form-control-lg" placeholder="Contoh: 10" min="1" required autofocus>
                                            <div class="input-group-append">
                                                <span class="input-group-text"><?= htmlspecialchars($barang['satuan']) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="font-weight-bold">Catatan (Opsional)</label>
                                        <textarea name="catatan" class="form-control" rows="2" placeholder="Keterangan tambahan..."></textarea>
                                    </div>

                                    <div class="mt-4 text-right">
                                        <a href="stok_gudang.php" class="btn btn-light"><i class="fas fa-times"></i> Batal</a>
                                        <button type="submit" class="btn btn-success px-4 ml-1">
                                            <i class="fas fa-save"></i> Simpan Stok
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
    
    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

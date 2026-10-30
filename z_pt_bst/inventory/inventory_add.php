<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst_inventory', 'create');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode = mysqli_real_escape_string($conn, $_POST['kode_barang']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    mysqli_begin_transaction($conn);
    try {
        $q_insert = "INSERT INTO bst_inventory (kode_barang, nama_barang, kategori, satuan, keterangan) 
                     VALUES ('$kode', '$nama', '$kategori', '$satuan', '$keterangan')";
        mysqli_query($conn, $q_insert);
        $id_barang = mysqli_insert_id($conn);
        
        $q_stok = "INSERT INTO bst_stok (id_barang_bst, quantity) VALUES ($id_barang, 0)";
        mysqli_query($conn, $q_stok);
        
        mysqli_commit($conn);
        echo "<script>alert('Barang BST berhasil ditambahkan!'); window.location.href='dashboard.php';</script>";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Gagal: " . $e->getMessage() . "'); history.back();</script>";
    }
    exit;
}

// Auto generate kode barang
$q_code = mysqli_query($conn, "SELECT MAX(id_barang_bst) as max_id FROM bst_inventory");
$r_code = mysqli_fetch_assoc($q_code);
$next_id = ($r_code['max_id'] ?? 0) + 1;
$auto_kode = "BST-ITM-" . str_pad($next_id, 4, "0", STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Tambah Barang BST - Aplikasi Asset Management</title>

</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row">
                        <div class="col">
                            <h3 class="page-title">Tambah Barang PT BST</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Inventory BST</a></li>
                                <li class="breadcrumb-item active">Tambah</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Kode Barang</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="kode_barang" value="<?= $auto_kode ?>" required readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Nama Barang <span class="text-danger">*</span></label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="nama_barang" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Kategori</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="kategori" placeholder="Cth: Kertas, Alat Tulis, dll">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Satuan <span class="text-danger">*</span></label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="satuan" placeholder="Cth: Rim, Pcs, Box" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Keterangan</label>
                                        <div class="col-md-9">
                                            <textarea class="form-control" name="keterangan" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                                        <button type="submit" class="btn btn-primary">Simpan Barang</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php include __DIR__ . '/../../layouts/footer.php'; ?>
        </div>
    </div>
    
    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>

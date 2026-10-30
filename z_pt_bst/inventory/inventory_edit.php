<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst_inventory', 'update');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id == 0) {
    echo "<script>alert('Akses ilegal!'); window.location.href='dashboard.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    $q_update = "UPDATE bst_inventory SET 
                 nama_barang='$nama', kategori='$kategori', satuan='$satuan', keterangan='$keterangan' 
                 WHERE id_barang_bst=$id";
                 
    if (mysqli_query($conn, $q_update)) {
        echo "<script>alert('Barang BST berhasil diperbarui!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal: " . mysqli_error($conn) . "'); history.back();</script>";
    }
    exit;
}

$q_data = mysqli_query($conn, "SELECT * FROM bst_inventory WHERE id_barang_bst = $id");
$data = mysqli_fetch_assoc($q_data);
if(!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Edit Barang BST - Aplikasi Asset Management</title>

</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row">
                        <div class="col">
                            <h3 class="page-title">Edit Master Barang PT BST</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Inventory BST</a></li>
                                <li class="breadcrumb-item active">Edit</li>
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
                                            <input type="text" class="form-control" name="kode_barang" value="<?= htmlspecialchars($data['kode_barang']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Nama Barang <span class="text-danger">*</span></label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="nama_barang" value="<?= htmlspecialchars($data['nama_barang']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Kategori</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="kategori" value="<?= htmlspecialchars($data['kategori'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Satuan <span class="text-danger">*</span></label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" name="satuan" value="<?= htmlspecialchars($data['satuan']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-form-label col-md-3">Keterangan</label>
                                        <div class="col-md-9">
                                            <textarea class="form-control" name="keterangan" rows="3"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                                        <button type="submit" class="btn btn-success">Simpan Perubahan</button>
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

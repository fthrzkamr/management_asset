<?php
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
if ($auth->hasPermission('inventory_atk_master', 'update') || $auth->hasPermission('pt_bst_inventory', 'update')) {
    // Access granted
} else {
    $auth->requirePermission('inventory_atk_master', 'update'); 
}

$page_title = "Edit Master Barang";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID tidak valid!";
    header("Location: inventory_list.php"); exit();
}

$id_barang = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM inventory_atk WHERE id_barang = $id_barang");

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error_message'] = "Barang tidak ditemukan!";
    header("Location: inventory_list.php"); exit();
}
$barang = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_barang = mysqli_real_escape_string($conn, trim($_POST['nama_barang']));
    $kategori    = mysqli_real_escape_string($conn, $_POST['kategori']);
    $satuan      = mysqli_real_escape_string($conn, $_POST['satuan']);

    if (mysqli_query($conn, "UPDATE inventory_atk SET nama_barang='$nama_barang', satuan='$satuan', kategori='$kategori' WHERE id_barang=$id_barang")) {
        $_SESSION['success_message'] = "Barang <strong>$nama_barang</strong> berhasil diperbarui.";
        header("Location: inventory_list.php"); exit();
    } else {
        $_SESSION['error_message'] = "Gagal memperbarui: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include_once('../layouts/navbar.php'); ?>
<div class="page-wrapper">
<div class="content container-fluid">

    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title"><?= $page_title ?></h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="inventory_list.php">Master Barang</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Master Barang</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Kode Barang</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars($barang['kode_barang']) ?>" readonly>
                            <small class="form-text text-muted">Kode barang tidak dapat diubah.</small>
                        </div>
                        <div class="form-group">
                            <label>Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" name="nama_barang" class="form-control"
                                   value="<?= htmlspecialchars($_POST['nama_barang'] ?? $barang['nama_barang']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Satuan <span class="text-danger">*</span></label>
                            <select name="satuan" class="form-control" required>
                                <?php foreach (['Pcs','Rim','Box','Lusin','Pak','Botol','Lembar','Roll','Set','Buah'] as $sat): ?>
                                    <option value="<?= $sat ?>" <?= (($_POST['satuan'] ?? $barang['satuan']) == $sat) ? 'selected' : '' ?>><?= $sat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kategori <span class="text-danger">*</span></label>
                            <select name="kategori" class="form-control" required>
                                <?php foreach (['ATK', 'Konsumsi', 'Elektronik', 'Lainnya'] as $kat): ?>
                                    <option value="<?= $kat ?>" <?= (($_POST['kategori'] ?? $barang['kategori']) == $kat) ? 'selected' : '' ?>><?= $kat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Perbarui</button>
                            <a href="inventory_list.php" class="btn btn-secondary ml-2"><i class="fas fa-arrow-left"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include_once('../layouts/footer.php'); ?>
</body>
</html>

<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_cabang', 'create');

// Auto-generate ID Cabang
$query = "SELECT id_cabang FROM master_cabang ORDER BY id_cabang DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $last_id = $row['id_cabang'];
    // Extract number from CAB001
    $num = (int)substr($last_id, 3);
    $new_num = $num + 1;
    $new_id = 'CAB' . str_pad($new_num, 3, '0', STR_PAD_LEFT);
} else {
    $new_id = 'CAB001';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Cabang - DFM Asset</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Tambah Cabang</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Master Data</a></li>
                                <li class="breadcrumb-item active">Tambah Cabang</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="fas fa-building"></i> Form Tambah Cabang</h5>
                            </div>
                            <div class="card-body">
                                <form action="cabang_insert.php" method="POST">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">ID Cabang <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="id_cabang" class="form-control bg-light" 
                                                   value="<?= $new_id ?>" readonly required>
                                            <small class="text-muted">ID otomatis dibuat oleh sistem</small>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Cabang <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="nama_cabang" class="form-control" 
                                                   placeholder="Contoh: Jakarta Pusat" required maxlength="100">
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <div class="col-sm-9 offset-sm-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Simpan
                                            </button>
                                            <a href="dashboard.php" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left"></i> Kembali
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../layouts/footer.php'; ?>
    </div>
</body>
</html>

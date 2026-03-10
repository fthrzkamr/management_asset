<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_cabang', 'update');

$id_cabang = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id_cabang)) {
    header("Location: dashboard.php");
    exit();
}

// Get data cabang
$sql = "SELECT * FROM master_cabang WHERE id_cabang = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $id_cabang);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cabang = mysqli_fetch_assoc($result);

if (!$cabang) {
    header("Location: dashboard.php?error=notfound");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Cabang - DFM Asset</title>
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
                            <h3 class="page-title">Edit Cabang</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Master Data</a></li>
                                <li class="breadcrumb-item active">Edit Cabang</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Form Edit Cabang</h5>
                            </div>
                            <div class="card-body">
                                <form action="cabang_update.php" method="POST">
                                    <input type="hidden" name="id_cabang_old" value="<?= htmlspecialchars($cabang['id_cabang']) ?>">
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">ID Cabang <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="id_cabang" class="form-control bg-light" 
                                                   value="<?= htmlspecialchars($cabang['id_cabang']) ?>"
                                                   readonly required>
                                            <small class="text-muted">ID tidak dapat diubah</small>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Nama Cabang <span class="text-danger">*</span></label>
                                        <div class="col-sm-9">
                                            <input type="text" name="nama_cabang" class="form-control" 
                                                   value="<?= htmlspecialchars($cabang['nama_cabang']) ?>"
                                                   required maxlength="100">
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <div class="col-sm-9 offset-sm-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Update
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

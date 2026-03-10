<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_kategori_asset', 'create');
?>
<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Tambah Kategori</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Kategori</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="katge_insert.php" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Informasi Kategori Asset</span>
                                            </h5>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>Nama Kategori Asset</label>
                                                <input type="text" name="kategori_asset" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>Sub Kategori</label>
                                                <input type="text" name="sub_kategori" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>Kode Kategori Asset</label>
                                                <input type="number" name="kode_kategori" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button name="submit" type="submit" class="btn btn-primary">
                                                Submit
                                            </button>
                                            <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
</body>

</html>
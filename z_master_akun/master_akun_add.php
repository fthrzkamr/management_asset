<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header('Location: ../login.php'); exit(); }
$auth->requirePermission('master_akun', 'create', 'dashboard.php');


// Generate next id_ma like MAX0001, MAX0002 ...
$result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(id_ma,4) AS UNSIGNED)) AS max_num FROM master_akun");
$row = mysqli_fetch_assoc($result);
$nextNum = ($row && $row['max_num']) ? intval($row['max_num']) + 1 : 1;
$newId = sprintf('MAX%04d', $nextNum);
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
                            <h3 class="page-title">Tambah Akun</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Akun</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="master_akun_insert.php" method="POST">
                                    <input type="hidden" name="id_ma" value="<?php echo $newId; ?>">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Informasi Akun</span>
                                            </h5>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>ID Akun</label>
                                                <input type="text" class="form-control" value="<?php echo $newId; ?>" readonly />
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>No Akun</label>
                                                <input type="text" name="no_akun" class="form-control" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>Nama Akun</label>
                                                <input type="text" name="nama_akun" class="form-control" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group">
                                                <label>Kategori Akun <span class="text-danger">*</span></label>
                                                <input type="text" name="kategori_akun" class="form-control" placeholder="Contoh: Asset, Operasional, Maintenance" required />
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
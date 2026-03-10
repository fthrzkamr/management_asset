<?php
include '../dist/config/config.php'; // sesuaikan path jika beda
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_divisi', 'update');
if (!isset($_GET['id'])) {
    // redirect jika tidak ada id
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'];
$query = "SELECT * FROM divisi WHERE id_divisi = '$id'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "Data tidak ditemukan!";
    exit;
}

$data = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>

    <body>
        <div class="main-wrapper">
            <?php include '../layouts/navbar.php'; ?>

            <div class="page-wrapper">
                <div class="content container-fluid">
                    <div class="page-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h3 class="page-title">Edit Divisi</h3>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item">
                                        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active">Edit Divisi</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-body">
                                    <form action="divisi_update.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="id_divisi" value="<?= $data['id_divisi'] ?>">
                                        <div class="row">
                                            <div class="col-12">
                                                <h5 class="form-title">
                                                    <span>Informasi Divisi</span>
                                                </h5>
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <div class="form-group">
                                                    <label>Nama Divisi</label>
                                                    <input type="text" name="nama_divisi" value="<?php echo $data['nama_divisi'] ?>" class="form-control" />
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div>
                                                    <button name="submit" type="submit" class="btn btn-info">Submit</button>
                                                    <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
                                                </div>
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
</body>

</html>
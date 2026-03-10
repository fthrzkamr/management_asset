<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\export_filter.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$auth->requirePermission('pengajuan', 'read');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Export Excel - Filter</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">
                                <i class="fas fa-file-excel text-success"></i>
                                Export Excel Pengajuan Asset
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Export Excel</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-filter fa-sm"></i> Filter Export Data
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="export_excel_filtered.php" method="GET">

                                    <div class="row">
                                        <!-- Tanggal Mulai -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="start_date">Tanggal Mulai</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date"
                                                    value="<?= date('Y-m-01') ?>">
                                            </div>
                                        </div>

                                        <!-- Tanggal Akhir -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="end_date">Tanggal Akhir</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date"
                                                    value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Status -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="">Semua Status</option>
                                                    <option value="Pending">Pending</option>
                                                    <option value="Disetujui">Disetujui</option>
                                                    <option value="Ditolak">Ditolak</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Kategori -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kategori">Kategori Asset</label>
                                                <select class="form-control" id="kategori" name="kategori">
                                                    <option value="">Semua Kategori</option>
                                                    <?php
                                                    $kategori_query = "SELECT DISTINCT kategori_asset FROM kategori_asset ORDER BY kategori_asset";
                                                    $kategori_result = mysqli_query($conn, $kategori_query);
                                                    while ($kategori = mysqli_fetch_assoc($kategori_result)):
                                                    ?>
                                                        <option value="<?= htmlspecialchars($kategori['kategori_asset']) ?>">
                                                            <?= htmlspecialchars($kategori['kategori_asset']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Buttons -->
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <a href="dashboard.php" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left fa-sm"></i> Kembali
                                            </a>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-file-excel fa-sm"></i> Export Excel
                                            </button>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/plugins/jquery/jquery.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>
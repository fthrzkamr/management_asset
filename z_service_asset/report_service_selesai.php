<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\report_service_selesai.php
include('../dist/config/config.php');

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// Filter tanggal
$tanggal_dari = $_GET['dari'] ?? date('Y-m-01'); // Awal bulan ini
$tanggal_sampai = $_GET['sampai'] ?? date('Y-m-d'); // Hari ini

$where_tanggal = "";
if ($tanggal_dari && $tanggal_sampai) {
    $where_tanggal = "AND DATE(s.tanggal_selesai) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
}

// Query untuk service selesai dengan filter
$query = mysqli_query($conn, "
    SELECT 
        s.id_service,
        s.no_service,
        s.kode_aset,
        s.tanggal_service,
        s.tanggal_selesai,
        s.jenis_service,
        s.biaya_service,
        s.tindakan,
        a.nama_aset,
        a.merk,
        a.kategori_aset,
        a.lokasi,
        DATEDIFF(s.tanggal_selesai, s.tanggal_service) as lama_service
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset = a.kode_aset
    WHERE s.status_service = 'Selesai' $where_tanggal
    ORDER BY s.tanggal_selesai DESC
");

// Hitung total biaya
$total_biaya_query = mysqli_query($conn, "
    SELECT SUM(biaya_service) as total 
    FROM service_asset s
    WHERE s.status_service = 'Selesai' $where_tanggal
");
$total_biaya = mysqli_fetch_assoc($total_biaya_query)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Service Selesai - DFM</title>
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
                            <h3 class="page-title">Report Service Selesai</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Service Asset</a>
                                </li>
                                <li class="breadcrumb-item active">Report Service Selesai</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group">
                                <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" class="form-control" name="dari" value="<?= $tanggal_dari ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" class="form-control" name="sampai" value="<?= $tanggal_sampai ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="report_service_selesai.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6>Total Service Selesai</h6>
                                        <h3><?= mysqli_num_rows($query) ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6>Total Biaya Semua Service</h6>
                                        <h3>Rp <?= number_format($total_biaya, 0, ',', '.') ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-check-circle text-success"></i>
                            Service Selesai (<?= date('d/m/Y', strtotime($tanggal_dari)) ?> - <?= date('d/m/Y', strtotime($tanggal_sampai)) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($query) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="serviceTable">
                                    <thead class="bg-success text-white">
                                        <tr>
                                            <th>No</th>
                                            <th>No. Service</th>
                                            <th>Asset</th>
                                            <th>Jenis Service</th>
                                            <th>Tanggal Service</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Lama Service</th>
                                            <th>Biaya</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        while ($row = mysqli_fetch_assoc($query)):
                                        ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <span class="badge badge-dark"><?= htmlspecialchars($row['no_service']) ?></span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['nama_aset']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($row['kode_aset']) ?> |
                                                        <?= htmlspecialchars($row['merk']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?= htmlspecialchars($row['jenis_service']) ?></span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_service'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_selesai'])) ?></td>
                                                <td>
                                                    <span class="badge badge-secondary">
                                                        <?= $row['lama_service'] ?> hari
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-success">
                                                        Rp <?= number_format($row['biaya_service'], 0, ',', '.') ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <th colspan="7" class="text-right">Total Biaya:</th>
                                            <th class="text-success">
                                                Rp <?= number_format($total_biaya, 0, ',', '.') ?>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak Ada Data Service Selesai</h5>
                                <p class="text-muted">Pada periode yang dipilih</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>



</body>

</html>
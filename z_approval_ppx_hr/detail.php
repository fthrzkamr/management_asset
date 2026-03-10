<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_hr\detail.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$auth->requirePermission('ppx', 'process');

if (!isset($_GET['no_ppx'])) {
    header("Location: dashboard.php");
    exit();
}

$no_ppx = mysqli_real_escape_string($conn, $_GET['no_ppx']);

// Get PPX data
$query = "SELECT pr.*, 
          d.nama_divisi,
          dep.nama_departemen,
          u.nama_user as nama_pengaju
          FROM ppx_request pr
          LEFT JOIN divisi d ON pr.divisi = d.id_divisi
          LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
          LEFT JOIN user_dfm u ON pr.user_id = u.id_user
          WHERE pr.no_ppx = '$no_ppx'";
          
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx = mysqli_fetch_assoc($result);

// Get items
$items_query = "SELECT * FROM ppx_request_detail WHERE no_ppx = '$no_ppx' ORDER BY no_ppx_detail";
$items_result = mysqli_query($conn, $items_query);

// Get realisasi if exists
$realisasi_query = "SELECT * FROM ppx_realisasi WHERE no_ppx = '$no_ppx' ORDER BY tanggal_realisasi DESC";
$realisasi_result = mysqli_query($conn, $realisasi_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
    
    <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    
    <?php include '../layouts/navbar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Detail PPX</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Detail PPX</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Informasi PPX: <?= htmlspecialchars($ppx['no_ppx']) ?></h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200">No PPX</th>
                                        <td><?= htmlspecialchars($ppx['no_ppx']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Pengajuan</th>
                                        <td><?= date('d/m/Y', strtotime($ppx['tanggal_pengajuan'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Nama PIC</th>
                                        <td><?= htmlspecialchars($ppx['nama_pic']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Jabatan</th>
                                        <td><?= htmlspecialchars($ppx['jabatan']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Divisi</th>
                                        <td><?= htmlspecialchars($ppx['nama_divisi']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Cabang</th>
                                        <td><?= htmlspecialchars($ppx['cabang']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Alasan Pengajuan</th>
                                        <td><?= nl2br(htmlspecialchars($ppx['alasan_pengajuan'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge badge-<?= $ppx['status'] === 'Selesai' ? 'success' : 'warning' ?>">
                                                <?= htmlspecialchars($ppx['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>

                                <h4 class="mt-4">Detail Item</h4>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>No Akun</th>
                                            <th>Rincian</th>
                                            <th>Jumlah</th>
                                            <th>Satuan</th>
                                            <th>Harga Satuan</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        $grand_total = 0;
                                        while ($item = mysqli_fetch_assoc($items_result)): 
                                            $grand_total += $item['total_harga'];
                                        ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($item['no_akun']) ?></td>
                                                <td><?= htmlspecialchars($item['rincian']) ?></td>
                                                <td><?= $item['jumlah'] ?></td>
                                                <td><?= htmlspecialchars($item['satuan']) ?></td>
                                                <td>Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                                <td>Rp <?= number_format($item['total_harga'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <tr>
                                            <th colspan="6" class="text-right">Grand Total:</th>
                                            <th>Rp <?= number_format($grand_total, 0, ',', '.') ?></th>
                                        </tr>
                                    </tbody>
                                </table>

                                <?php if (mysqli_num_rows($realisasi_result) > 0): ?>
                                    <h4 class="mt-4">Realisasi</h4>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Nominal</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($real = mysqli_fetch_assoc($realisasi_result)): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($real['tanggal_realisasi'])) ?></td>
                                                    <td>Rp <?= number_format($real['nominal_realisasi'], 0, ',', '.') ?></td>
                                                    <td><?= htmlspecialchars($real['keterangan']) ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                                <a href="print_laporan.php?no_ppx=<?= urlencode($no_ppx) ?>" 
                                   class="btn btn-primary" target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../layouts/footer.php'; ?>
</div>

<script src="../assets/plugins/jquery/jquery.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
</body>
</html>

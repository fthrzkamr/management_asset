<?php
require '../dist/config/config.php';
require '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->hasPermission('inventory_atk_stok', 'read') && !$auth->hasPermission('inventory_atk_master', 'read')) {
    $auth->requirePermission('inventory_atk_stok', 'read');
}

$id_barang = isset($_GET['id_barang']) ? intval($_GET['id_barang']) : 0;

// Ambil info barang
$q_barang = mysqli_query($conn, "
    SELECT i.*, COALESCE(s.quantity, 0) as current_stok 
    FROM inventory_atk i 
    LEFT JOIN inventory_stok s ON i.id_barang = s.id_barang 
    WHERE i.id_barang = $id_barang
");
$barang = mysqli_fetch_assoc($q_barang);

if (!$barang) {
    echo "<script>alert('Barang tidak ditemukan!'); window.location.href='stok_dashboard.php';</script>";
    exit;
}

// Logika query union history 
// 1. Barang Masuk (dari penerimaan PT BST)
// 2. Barang Keluar (dari atk_pengambilan_detail yg Approved)
$query_history = "
    SELECT 
        'Masuk (PO PT BST)' as transaksi,
        m.tanggal_masuk as tanggal,
        m.qty_masuk as qty_in,
        0 as qty_out,
        m.nomor_masuk as nomor_Referensi
    FROM atk_barang_masuk m
    WHERE m.id_barang = $id_barang

    UNION ALL

    SELECT 
        'Keluar (Distribusi)' as transaksi,
        p.tanggal_pengambilan as tanggal,
        0 as qty_in,
        d.qty_diminta as qty_out,
        p.nomor_pengambilan as nomor_Referensi
    FROM atk_pengambilan_detail d
    JOIN atk_pengambilan p ON d.id_pengambilan = p.id_pengambilan
    WHERE d.id_barang = $id_barang AND p.status = 'Approved'

    ORDER BY tanggal DESC
";
$r_history = mysqli_query($conn, $query_history);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>History Barang ATK - Aplikasi Asset Management</title>
    <?php include '../layouts/head.php'; ?>
    <link rel="stylesheet" href="../assets/plugins/datatables/datatables.min.css">
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Riwayat Pergerakan Barang</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="stok_dashboard.php">Kelola Stok</a></li>
                                <li class="breadcrumb-item active">History Stok</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-white mb-1">Informasi Barang</h6>
                                <h4><?= htmlspecialchars($barang['nama_barang']) ?></h4>
                                <p class="mb-0">
                                    <span class="badge bg-white text-primary mr-2"><?= htmlspecialchars($barang['kode_barang']) ?></span>
                                    <i class="fas fa-boxes"></i> Stok Terkini: <strong><?= $barang['current_stok'] ?> <?= htmlspecialchars($barang['satuan']) ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0"><i class="fas fa-exchange-alt mr-2 text-info"></i> Tabel Log (Masuk & Keluar)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover datatable">
                                        <thead>
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Tanggal</th>
                                                <th>Jenis Transaksi</th>
                                                <th>No Ref. Dokumen</th>
                                                <th class="text-success text-center">Masuk (In)</th>
                                                <th class="text-danger text-center">Keluar (Out)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while($h = mysqli_fetch_assoc($r_history)): 
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($h['tanggal'])) ?></td>
                                                <td>
                                                    <?php if($h['qty_in'] > 0): ?>
                                                        <span class="badge badge-success"><?= $h['transaksi'] ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><?= $h['transaksi'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><code><?= htmlspecialchars($h['nomor_Referensi']) ?></code></small></td>
                                                <td class="text-center">
                                                    <?= $h['qty_in'] > 0 ? "<span class='font-weight-bold text-success'>+{$h['qty_in']}</span>" : "-" ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $h['qty_out'] > 0 ? "<span class='font-weight-bold text-danger'>-{$h['qty_out']}</span>" : "-" ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php include '../layouts/footer.php'; ?>
        </div>
    </div>
    
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <script src="../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>

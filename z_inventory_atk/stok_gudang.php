<?php
session_start();
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}
$auth->requirePermission('inventory_atk_stok', 'read');

$id_user = $_SESSION['id_user'];

// Ambil data gabungan antara master barang (inventory_atk) dengan saldo real (inventory_stok)
// Menggunakan LEFT JOIN agar barang yang belum ada riwayat pergerakannya (quantity NULL/kosong) tetap muncul dengan angka 0
$query = "SELECT 
            a.id_barang,
            a.kode_barang,
            a.nama_barang,
            a.kategori,
            a.satuan,
            COALESCE(s.quantity, 0) as quantity
          FROM inventory_atk a
          LEFT JOIN inventory_stok s ON a.id_barang = s.id_barang
          ORDER BY a.nama_barang ASC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Stok Gudang ATK - Aplikasi Asset Management</title>
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../../assets/plugins/fontawesome/css/all.min.css">
    <!-- Mengganti Datatables basic dengan Bootstrap 4 styling -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: transparent !important;
            border: none !important;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../layouts/navbar.php'; ?>
        
        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Stok Gudang ATK Riil</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Stok Tersedia</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Total Ketersediaan ATK Anda</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="stokTable" class="table table-bordered table-striped table-hover mt-3 w-100">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Kode SKU</th>
                                                <th>Nama / Item</th>
                                                <th>Kategori</th>
                                                <th class="text-center">Kuantitas Sisa</th>
                                                <th class="text-center">Aksi (Histori)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1; 
                                            while($row = mysqli_fetch_assoc($result)): 
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><span class="badge badge-secondary"><?= htmlspecialchars($row['kode_barang']) ?></span></td>
                                                <td><strong><?= htmlspecialchars($row['nama_barang']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['kategori']) ?></td>
                                                <td class="text-center">
                                                    <?php 
                                                        $badge_color = ($row['quantity'] <= 5) ? 'badge-danger' : 'badge-success';
                                                        if($row['quantity'] == 0) $badge_color = 'badge-secondary';
                                                    ?>
                                                    <span class="badge py-2 px-3 <?= $badge_color ?>" style="font-size: 14px;">
                                                        <?= $row['quantity'] ?> <?= htmlspecialchars($row['satuan']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="stok_history.php?id=<?= $row['id_barang'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-history"></i> Lihat Detail Jejak
                                                    </a>
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
                
                <?php include __DIR__ . '/../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <!-- Ganti JS datatables standar dengan kombinasi library bootstrap 4 -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="../../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        $('#stokTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
            },
            "pageLength": 25
        });
    });
    </script>
</body>
</html>

<?php
session_start();
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_inventory', 'read');

$id_barang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info barang
$q_barang = mysqli_query($conn, "SELECT a.*, COALESCE(s.quantity, 0) as sisa 
                          FROM inventory_atk a 
                          LEFT JOIN bst_stok s ON a.id_barang = s.id_barang 
                          WHERE a.id_barang = $id_barang");
$barang = mysqli_fetch_assoc($q_barang);
if (!$barang) { die("Data barang tidak ditemukan."); }

// UNION ALL Query untuk Histori Stok BST
$query_history = "
    -- 1. Masuk: Dari Pembelian/Restock PT BST
    SELECT 
        'Masuk (Restock)' as tipe_transaksi,
        h.tanggal_po as tanggal,
        h.nomor_po as referensi,
        d.qty as qty_in,
        0 as qty_out,
        CONCAT('Pembelian (Restock) dr: ', COALESCE(h.nama_supplier, '-')) as keterangan
    FROM bst_pembelian_detail d
    JOIN bst_pembelian h ON d.id_pembelian = h.id_pembelian
    WHERE d.id_barang = $id_barang AND h.status = 'Selesai'

    UNION ALL

    -- 2. Keluar: Disebarkan ke Gudang ATK DF Group
    SELECT 
        'Keluar (Kirim Keluar)' as tipe_transaksi,
        h.tanggal_po as tanggal,
        h.nomor_po as referensi,
        0 as qty_in,
        pd.qty_dikirim as qty_out,
        'Pengiriman ke Gudang Utama ATK' as keterangan
    FROM bst_po_detail pd
    JOIN bst_po h ON pd.id_po = h.id_po
    WHERE pd.id_barang_bst = $id_barang AND h.status IN ('Dikirim', 'Selesai')

    ORDER BY tanggal ASC
";
$result_history = mysqli_query($conn, $query_history);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Kartu Stok BST: <?= htmlspecialchars($barang['nama_barang']) ?> - DFM</title>
    <link rel="icon" href="../../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
</head>
<body>
    <div class="main-wrapper">
        <?php include_once('../../layouts/navbar.php'); ?>
        
        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title"><i class="fas fa-history text-info"></i> Kartu Stok Inventori BST</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Inventory BST</a></li>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($barang['nama_barang']) ?></li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-5">
                        <div class="card shadow-sm border-info">
                            <div class="card-body">
                                <p class="text-muted mb-1">Nama Barang:</p>
                                <h5 class="mb-2"><?= htmlspecialchars($barang['nama_barang']) ?></h5>
                                <p class="text-muted mb-0 small">Kode: <?= htmlspecialchars($barang['kode_barang']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-success text-center">
                            <div class="card-body">
                                <p class="text-muted mb-1">Saldo Stok Saat Ini:</p>
                                <h3 class="mb-0 font-weight-bold text-success"><?= number_format($barang['sisa']) ?> <?= htmlspecialchars($barang['satuan'] ?? 'Pcs') ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Rincian Debet & Kredit Stok</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="tblHistory">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%" class="text-center">No</th>
                                        <th width="15%">Tanggal</th>
                                        <th width="20%">No Referensi</th>
                                        <th width="15%">Transaksi</th>
                                        <th width="10%" class="text-center">Masuk</th>
                                        <th width="10%" class="text-center">Keluar</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; while($row = mysqli_fetch_assoc($result_history)): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><strong><?= htmlspecialchars($row['referensi']) ?></strong></td>
                                        <td><?= $row['tipe_transaksi'] ?></td>
                                        <td class="text-center text-success font-weight-bold"><?= $row['qty_in'] > 0 ? '+'.$row['qty_in'] : '-' ?></td>
                                        <td class="text-center text-danger font-weight-bold"><?= $row['qty_out'] > 0 ? '-'.$row['qty_out'] : '-' ?></td>
                                        <td><small><?= htmlspecialchars($row['keterangan']) ?></small></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php include_once('../../layouts/footer.php'); ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#tblHistory').DataTable({
            ordering: false,
            pageLength: 50,
            language: { url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" }
        });
    });
    </script>
</body>
</html>

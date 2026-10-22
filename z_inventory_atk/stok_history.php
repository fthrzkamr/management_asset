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

// Pastikan ID barang tersedia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Barang tidak valid!'); window.location.href='stok_gudang.php';</script>";
    exit;
}

$id_barang = (int)$_GET['id'];

// Ambil Informasi Detail Barang
$q_barang = mysqli_query($conn, "SELECT a.*, COALESCE(s.quantity, 0) as sisa_stok 
                                FROM inventory_atk a 
                                LEFT JOIN inventory_stok s ON a.id_barang = s.id_barang 
                                WHERE a.id_barang = $id_barang");
if (!$q_barang || mysqli_num_rows($q_barang) == 0) {
    echo "<script>alert('Barang tidak ditemukan dalam master ATK!'); window.location.href='stok_gudang.php';</script>";
    exit;
}
$barang = mysqli_fetch_assoc($q_barang);

// Fallback jika satuan belum terdefinisi di database (mencegah error offset)
if (!isset($barang['satuan']) || empty(trim($barang['satuan']))) {
    $barang['satuan'] = 'Unit';
}

// Ambil Histori Pergerakan (Gabungan In dan Out) dengan UNION ALL
$query_history = "
    SELECT 
        CASE 
            WHEN nomor_masuk LIKE 'ADD-%' THEN 'Masuk (Manual)'
            ELSE 'Masuk (Dari PO)'
        END as tipe_transaksi,
        tanggal_masuk as tanggal,
        nomor_masuk as nomor_referensi,
        qty_masuk as qty_in,
        0 as qty_out,
        COALESCE(keterangan, 
            CASE 
                WHEN nomor_masuk LIKE 'ADD-%' THEN 'Tambah Stok Manual / Koreksi'
                ELSE 'Penerimaan Barang dr BST'
            END
        ) as keterangan
    FROM atk_barang_masuk 
    WHERE id_barang = $id_barang

    UNION ALL

    SELECT 
        'Keluar (Distribusi)' as tipe_transaksi,
        tanggal_keluar as tanggal,
        nomor_keluar as nomor_referensi,
        0 as qty_in,
        qty_keluar as qty_out,
        CONCAT('Diserahkan ke: ', nama_penerima) as keterangan
    FROM atk_barang_keluar
    WHERE id_barang = $id_barang

    ORDER BY tanggal ASC
";

$result_history = mysqli_query($conn, $query_history);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Histori Stok: <?= htmlspecialchars($barang['nama_barang']) ?> - Aplikasi Asset Management</title>
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
                            <h3 class="page-title">Kartu Stok (History) Barang</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="stok_gudang.php">Stok Gudang ATK</a></li>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($barang['kode_barang']) ?></li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="stok_gudang.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-5">
                        <div class="card shadow-sm h-100 border-info">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title text-white mb-0">Identitas Barang ATK</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="35%" class="text-muted">Kode SKU</td>
                                        <td><strong><?= htmlspecialchars($barang['kode_barang']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Nama Singkat</td>
                                        <td><?= htmlspecialchars($barang['nama_barang']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Kategori Utama</td>
                                        <td><?= htmlspecialchars($barang['kategori']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100 border-success">
                            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                                <h6 class="text-muted mb-2">Sisa Ketersediaan Fisik</h6>
                                <?php $color = $barang['sisa_stok'] <= 0 ? 'text-danger' : 'text-success'; ?>
                                <h2 class="display-4 font-weight-bold <?= $color ?> mb-0">
                                    <?= $barang['sisa_stok'] ?>
                                </h2>
                                <span class="badge badge-light mt-2 border"><?= htmlspecialchars($barang['satuan']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0"><i class="fas fa-random text-primary mr-2"></i> Rincian Pergerakan Masuk & Keluar</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="historyTable" class="table table-bordered table-striped table-hover w-100">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="15%">Tanggal Trx</th>
                                                <th>Nomor Dokumen</th>
                                                <th>Jenis Transaksi</th>
                                                <th class="text-center text-success">Masuk (In)</th>
                                                <th class="text-center text-danger">Keluar (Out)</th>
                                                <th>Keterangan Tambahan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1; 
                                            // Variable dummy saldo berjalan (Running Balance) belum diimplementasi sempurna, 
                                            // kita cukup perlihatkan In/Out-nya dulu sesuai request sejarah UNION
                                            while($row = mysqli_fetch_assoc($result_history)): 
                                                $bg_row = ($row['qty_out'] > 0) ? 'bg-light' : '';
                                            ?>
                                            <tr class="<?= $bg_row ?>">
                                                <td><?= $no++ ?></td>
                                                <td><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?></td>
                                                <td><strong><?= htmlspecialchars($row['nomor_referensi']) ?></strong></td>
                                                <td>
                                                    <?php if($row['qty_in'] > 0): ?>
                                                        <span class="badge badge-success"><?= $row['tipe_transaksi'] ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><?= $row['tipe_transaksi'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center font-weight-bold text-success">
                                                    <?= $row['qty_in'] > 0 ? '+ '.$row['qty_in'] : '-' ?>
                                                </td>
                                                <td class="text-center font-weight-bold text-danger">
                                                    <?= $row['qty_out'] > 0 ? '- '.$row['qty_out'] : '-' ?>
                                                </td>
                                                <td><small><?= htmlspecialchars($row['keterangan']) ?></small></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <?php if(mysqli_num_rows($result_history) == 0): ?>
                                        <tfoot>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted font-italic py-4">Belum ada riwayat pergerakan sama sekali untuk barang ini.</td>
                                            </tr>
                                        </tfoot>
                                        <?php endif; ?>
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
        $('#historyTable').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
            },
            "pageLength": 50,
            "ordering": false // matikan sort agar tabel berjalan urut waktu secara murni dari query DB
        });
    });
    </script>
</body>
</html>

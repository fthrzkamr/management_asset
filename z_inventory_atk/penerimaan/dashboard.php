<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('inventory_atk_penerimaan', 'read');

$page_title = "Penerimaan PO & Riwayat";

// Ambil semua daftar PO yang diajukan oleh ATK (kita tampilkan semua agar jadi history juga)
$query = "SELECT po.*, 
          (SELECT COUNT(*) FROM bst_po_detail WHERE id_po = po.id_po) as total_item 
          FROM bst_po po
          WHERE po.nomor_po LIKE 'PO/ATK/%'
          ORDER BY po.id_po DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?= $page_title ?> - Aplikasi Asset Management</title>

    <link rel="stylesheet" href="../../assets/plugins/datatables/datatables.min.css">
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title"><?= $page_title ?></h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../inventory_list.php">Inventory ATK</a></li>
                                <li class="breadcrumb-item active">Penerimaan & Riwayat PO</li>
                            </ul>
                        </div>
                        <?php if($auth->hasPermission('inventory_atk_po', 'create')): ?>
                        <div class="col-auto">
                            <a href="../po_pengajuan/dashboard.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Ajukan PO Baru
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card card-table">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Daftar Riwayat PO Pengadaan</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="tblATK">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Nomor PO</th>
                                                <th>Supplier</th>
                                                <th>Tanggal Order</th>
                                                <th>Jumlah Macam Barang</th>
                                                <th>Status</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while($row = mysqli_fetch_assoc($result)): 
                                                $status = $row['status'];
                                                $badge_class = 'badge-warning'; // Pending
                                                if($status == 'Diproses') $badge_class = 'badge-info';
                                                if($status == 'Dikirim') $badge_class = 'badge-primary';
                                                if($status == 'Selesai') $badge_class = 'badge-success';
                                                if($status == 'Ditolak') $badge_class = 'badge-danger';
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><strong><a href="po_detail.php?id=<?= $row['id_po'] ?>"><?= htmlspecialchars($row['nomor_po']) ?></a></strong></td>
                                                <td><?= htmlspecialchars($row['nama_supplier'] ?? 'PT BST') ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_po'])) ?></td>
                                                <td><?= $row['total_item'] ?? 0 ?> Item</td>
                                                <td><span class="badge <?= $badge_class ?>" style="font-size: 13px;"><?= $status ?></span></td>
                                                <td class="text-right">
                                                    <?php if($status == 'Dikirim' && $auth->hasPermission('inventory_atk', 'process')): ?>
                                                        <a href="po_detail.php?id=<?= $row['id_po'] ?>" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check-double"></i> Aksi: Cek & Terima
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="po_detail.php?id=<?= $row['id_po'] ?>" class="btn btn-sm btn-white text-primary">
                                                            <i class="fas fa-eye"></i> Detail Lengkap
                                                        </a>
                                                    <?php endif; ?>
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

                <?php include __DIR__ . '/../../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tblATK').DataTable({
                "language": {
                    "emptyTable": "Belum ada riwayat pengajuan PO."
                }
            });
        });
    </script>
</body>
</html>

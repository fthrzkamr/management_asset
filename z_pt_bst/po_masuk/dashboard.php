<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst_po_masuk', 'read');

$page_title = "Daftar PO Masuk";

// Ambil semua daftar PO dari tabel bst_po
$query = "SELECT po.*, 
          (SELECT SUM(qty_pesan) FROM bst_po_detail WHERE id_po = po.id_po) as total_item 
          FROM bst_po po
          ORDER BY po.created_at DESC";
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
                                <li class="breadcrumb-item"><a href="../inventory/dashboard.php">PT BST</a></li>
                                <li class="breadcrumb-item active">PO Masuk dari ATK</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card card-table">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="tblPOMasuk">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Nomor PO</th>
                                                <th>Tanggal Order</th>
                                                <th>Jumlah Item</th>
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
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_po'])) ?></td>
                                                <td><?= $row['total_item'] ?? 0 ?> Item Barang</td>
                                                <td><span class="badge <?= $badge_class ?>" style="font-size: 13px;"><?= $status ?></span></td>
                                                <td class="text-right">
                                                    <a href="po_detail.php?id=<?= $row['id_po'] ?>" class="btn btn-sm btn-white text-primary">
                                                        <i class="fas fa-eye"></i> Detail
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

                <?php include __DIR__ . '/../../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tblPOMasuk').DataTable({
                "language": {
                    "emptyTable": "Belum ada PO MASUK dari ATK."
                }
            });
        });
    </script>
</body>
</html>

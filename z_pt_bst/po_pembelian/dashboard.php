<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_po_pembelian', 'read');

$page_title = "PO Pembelian (Restock PT BST)";

// Ambil daftar PO Pembelian
$query = "SELECT p.*, u.nama_user as pembuat,
          (SELECT SUM(qty * harga_satuan) FROM bst_pembelian_detail WHERE id_pembelian = p.id_pembelian) as total_harga 
          FROM bst_pembelian p 
          LEFT JOIN user_dfm u ON p.created_by = u.id_user 
          ORDER BY p.id_pembelian DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - DFM</title>
    <link rel="icon" href="../../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
</head>
<body>
<?php include_once('../../layouts/navbar.php'); ?>
<div class="page-wrapper">
<div class="content container-fluid">

    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title"><i class="fas fa-shopping-cart text-primary"></i> <?= $page_title ?></h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">PO Pembelian BST</li>
                </ul>
            </div>
            <?php if ($auth->hasPermission('pt_bst_po_pembelian', 'create')): ?>
            <div class="col-auto">
                <a href="add.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus"></i> Buat PO Pembelian
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="tblPo">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>No PO</th>
                            <th>Tanggal PO</th>
                            <th>Supplier</th>
                            <th class="text-right">Total OMV (Rp)</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="font-weight-bold text-primary"><?= htmlspecialchars($row['nomor_po']) ?></td>
                            <td><?= date('d-m-Y', strtotime($row['tanggal_po'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                            <td class="text-right text-success font-weight-bold"><?= number_format($row['total_harga'],0,',','.') ?></td>
                            <td>
                                <?php if($row['status'] == 'Selesai'): ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Selesai (Stok Masuk)</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['pembuat'] ?? 'Sistem') ?></td>
                            <td class="text-center">
                                <a href="detail.php?id=<?= $row['id_pembelian'] ?>" class="btn btn-sm btn-info" data-toggle="tooltip" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if($row['status'] == 'Pending' && $auth->hasPermission('pt_bst_po_pembelian', 'process')): ?>
                                <a href="proses_selesai.php?id=<?= $row['id_pembelian'] ?>" 
                                   class="btn btn-sm btn-success" 
                                   onclick="event.preventDefault(); var url = this.href; showConfirm('Selesaikan PO Pembelian ini? Stok barang milik PT BST akan bertambah secara otomatis.', function() { window.location.href = url; });"
                                   data-toggle="tooltip" title="Tandai Selesai & Tambah Stok">
                                    <i class="fas fa-check"></i>
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
    <?php include_once('../../layouts/footer.php'); ?>

</div>
</div>

<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tblPo').DataTable({ order: [[0, 'desc']] });
});
</script>
</body>
</html>

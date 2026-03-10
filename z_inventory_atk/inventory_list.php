<?php
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
if ($auth->hasPermission('inventory_atk_master', 'read') || $auth->hasPermission('pt_bst_inventory', 'read')) {
    // Access granted
} else {
    $auth->requirePermission('inventory_atk_master', 'read'); 
}

$page_title = "Master Inventory Barang";

$result = mysqli_query($conn, "SELECT * FROM inventory_atk ORDER BY kategori, nama_barang");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
</head>
<body>
<?php include_once('../layouts/navbar.php'); ?>
<div class="page-wrapper">
<div class="content container-fluid">

    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title"><?= $page_title ?></h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Master Barang</li>
                </ul>
            </div>
            <?php if ($auth->hasPermission('inventory_atk', 'create')): ?>
            <div class="col-auto">
                <a href="inventory_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Barang
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>


    <!-- Tabel -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="tblMaster">
                    <thead class="thead-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Nama Barang</th>
                            <th width="90">Satuan</th>
                            <th width="130">Kategori</th>
                            <?php if ($auth->hasPermission('inventory_atk', 'update') || $auth->hasPermission('inventory_atk', 'delete')): ?>
                            <th width="120">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
                            <td>
                                <?php
                                $badge = ['ATK' => 'primary', 'Konsumsi' => 'success', 'Elektronik' => 'info', 'Lainnya' => 'secondary'];
                                $b = $badge[$row['kategori']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $b ?>"><?= $row['kategori'] ?></span>
                            </td>
                            <?php if ($auth->hasPermission('inventory_atk', 'update') || $auth->hasPermission('inventory_atk', 'delete')): ?>
                            <td>
                                <?php if ($auth->hasPermission('inventory_atk', 'update')): ?>
                                    <a href="inventory_edit.php?id=<?= $row['id_barang'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($auth->hasPermission('inventory_atk', 'delete')): ?>
                                    <a href="inventory_delete.php?id=<?= $row['id_barang'] ?>"
                                       class="btn btn-sm btn-danger" title="Hapus"
                                       onclick="return confirm('Yakin hapus barang ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php include_once('../layouts/footer.php'); ?>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(window).on('load', function() {
    $('#tblMaster').DataTable({
        order: [],
        ordering: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], ['10', '25', '50', '100', 'Semua']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Tidak ada data ditemukan',
            paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' }
        }
    });
});
</script>
</body>
</html>

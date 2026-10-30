<?php
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
if (!$auth->hasPermission('inventory_atk_stok', 'read') && !$auth->hasPermission('inventory_atk_master', 'read')) {
    $auth->requirePermission('inventory_atk_stok', 'read');
}

$page_title = "Kelola Stok Barang";

// Query semua barang (LEFT JOIN untuk auto tampil dari master)
$sql = "SELECT b.id_barang, b.nama_barang, b.satuan, b.kategori,
               s.id_stok, s.quantity, s.keterangan
        FROM inventory_atk b
        LEFT JOIN inventory_stok s ON b.id_barang = s.id_barang
        ORDER BY b.kategori, b.nama_barang";
$result = mysqli_query($conn, $sql);
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
                    <li class="breadcrumb-item active">Kelola Stok</li>
                </ul>
            </div>
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
                <table class="table table-hover table-bordered" id="tblStok">
                    <thead class="thead-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Nama Barang</th>
                            <th width="90">Satuan</th>
                            <th width="120">Kategori</th>
                            <th width="100" class="text-center">Quantity</th>
                            <th>Keterangan</th>
                            <th width="100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $has_data = false;
                        while ($row = mysqli_fetch_assoc($result)):
                            $has_data = true;
                            $badge = ['ATK' => 'primary', 'Konsumsi' => 'success', 'Elektronik' => 'info', 'Lainnya' => 'secondary'];
                            $b = $badge[$row['kategori']] ?? 'secondary';
                            $qty = $row['quantity'] ?? null;
                            $qty_display = $qty !== null ? $qty : '-';
                            $badge_color = $qty > 0 ? 'success' : ($qty === null ? 'secondary' : 'danger');
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $b ?>"><?= $row['kategori'] ?></span></td>
                            <td class="text-center">
                                <span class="badge badge-<?= $badge_color ?> badge-pill px-3">
                                    <?= $qty_display ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($row['keterangan'] ?? '-')) ?></td>
                            <td class="text-center">
                                <a href="history_detail.php?id_barang=<?= $row['id_barang'] ?>" class="btn btn-sm btn-info" title="Lihat History In/Out">
                                    <i class="fas fa-history"></i> History
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
<?php include_once('../layouts/footer.php'); ?>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(window).on('load', function() {
    $('#tblStok').DataTable({
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
            emptyTable: 'Belum ada barang di master inventory. <a href="inventory_list.php">Tambah barang dulu ke master</a>.',
            paginate: { first: 'Pertama', last: 'Terakhir', next: '&raquo;', previous: '&laquo;' }
        }
    });
});
</script>
</body>
</html>

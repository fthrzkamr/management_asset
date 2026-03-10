<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_inventory', 'read');

$page_title = "Master Inventory PT BST";

// Ambil data gabungan dari master atk dan stok bst
$query = "SELECT a.id_barang, a.kode_barang, a.nama_barang, a.satuan, a.kategori, 
          COALESCE(b.quantity, 0) as stok_bst, b.id_stok_bst 
          FROM inventory_atk a 
          LEFT JOIN bst_stok b ON a.id_barang = b.id_barang 
          ORDER BY a.kategori, a.nama_barang";
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
                <h3 class="page-title"><i class="fas fa-boxes text-primary"></i> <?= $page_title ?></h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Inventory PT BST</li>
                </ul>
                <p class="text-muted small mt-1">Gudang Penyedia PT BST - Master data terpusat di ATK</p>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible bg-success text-white border-0 fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Tabel -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered table-sm" id="tblBst">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="12%">Kode</th>
                            <th>Nama Barang</th>
                            <th width="10%">Satuan</th>
                            <th width="15%">Kategori</th>
                            <th width="10%" class="text-center">Stok BST</th>
                            <?php if ($auth->hasPermission('pt_bst', 'update')): ?>
                            <th width="10%" class="text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><small><?= htmlspecialchars($row['kode_barang']) ?></small></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
                            <td>
                                <?php
                                $badge = ['ATK' => 'primary', 'Konsumsi' => 'success', 'Elektronik' => 'info', 'Lainnya' => 'secondary'];
                                $cat = $row['kategori'] ? $row['kategori'] : 'Lainnya';
                                $b = $badge[$cat] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $b ?>"><?= $cat ?></span>
                            </td>
                            <td class="text-center font-weight-bold <?= $row['stok_bst'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($row['stok_bst']) ?>
                            </td>
                            <?php if ($auth->hasPermission('pt_bst', 'update')): ?>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="openModal(<?= $row['id_barang'] ?>, '<?= addslashes($row['nama_barang']) ?>', <?= $row['stok_bst'] ?>)" title="Update Stok Manual">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                            <?php endif; ?>
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

<!-- Modal Update Stok -->
<div class="modal fade" id="stokModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="update_stok.php" method="POST">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-boxes"></i> Update Stok Manual</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body pb-0">
                    <input type="hidden" name="id_barang" id="modal_id_barang">
                    <div class="form-group">
                        <label class="font-weight-bold">Item:</label>
                        <p id="modal_nama_barang" class="mb-2 text-primary"></p>
                    </div>
                    <div class="form-group">
                        <label>Sisa Stok Tersedia (BST)</label>
                        <div class="input-group">
                            <input type="number" name="stok_baru" id="modal_stok_baru" class="form-group form-control" required min="0">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-calculator"></i></span>
                            </div>
                        </div>
                        <small class="text-muted">*Set Manual Kuantitas</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Stok</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tblBst').DataTable({
        ordering: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
        language: {
            search: "Filter:",
            lengthMenu: "Tampil _MENU_ data",
            info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ item",
            infoEmpty: "Belum ada master barang",
            zeroRecords: "Pencarian tidak ditemukan",
            paginate: { first: "Start", last: "End", next: "&raquo;", previous: "&laquo;" }
        }
    });
});

function openModal(id, nama, stokSekarang) {
    document.getElementById('modal_id_barang').value = id;
    document.getElementById('modal_nama_barang').innerText = nama;
    document.getElementById('modal_stok_baru').value = stokSekarang;
    $('#stokModal').modal('show');
}
</script>
</body>
</html>

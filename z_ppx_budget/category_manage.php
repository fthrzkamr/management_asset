<?php

include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission to manage category
if (!$auth->canManageCategory()) {
    header("Location: ../index.php");
    exit();
}

// Get all active categories
// Get success/error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

$query = "SELECT * FROM ppx_budget_category ORDER BY category_name";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Budget - DFM Asset</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
</head>
<body>
<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Kategori Budget</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                        <li class="breadcrumb-item active">Kategori Budget</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
                        <i class="fas fa-plus"></i> Tambah Kategori
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-tags"></i> Daftar Kategori</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="categoryTable">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Kategori</th>
                                        <th>Deskripsi</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['category_code']) ?></code></td>
                                            <td><strong><?= htmlspecialchars($row['category_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['description']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $row['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $row['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="editCategory(<?= $row['id_category'] ?>, '<?= htmlspecialchars($row['category_code']) ?>', '<?= htmlspecialchars($row['category_name']) ?>', '<?= htmlspecialchars($row['description']) ?>', <?= $row['is_active'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($row['is_active']): ?>
                                                    <a href="category_toggle.php?id=<?= $row['id_category'] ?>&action=deactivate" 
                                                       class="btn btn-sm btn-warning" 
                                                       onclick="return confirm('Nonaktifkan kategori ini?')">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="category_toggle.php?id=<?= $row['id_category'] ?>&action=activate" 
                                                       class="btn btn-sm btn-success" 
                                                       onclick="return confirm('Aktifkan kategori ini?')">
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
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Kategori Baru</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="category_insert.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Kode Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_code" class="form-control" 
                               placeholder="Contoh: ELEKTRONIK" required maxlength="50">
                        <small class="text-muted">Gunakan huruf kapital tanpa spasi</small>
                    </div>
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" 
                               placeholder="Contoh: Elektronik" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Jelaskan kategori ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Kategori</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="category_update.php" method="POST">
                <input type="hidden" name="id_category" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Kode Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_code" id="edit_code" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="edit_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" id="edit_status" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once('../layouts/footer.php'); ?>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#categoryTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 10,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]],
        "language": {
            "emptyTable": "Tidak ada data yang tersedia",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 entri",
            "infoFiltered": "(disaring dari _MAX_ total entri)",
            "lengthMenu": "Tampilkan _MENU_ entri",
            "loadingRecords": "Memuat...",
            "processing": "Memproses...",
            "search": "Cari:",
            "zeroRecords": "Tidak ditemukan data yang sesuai",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        }
    });
});

function editCategory(id, code, name, desc, status) {
    $('#edit_id').val(id);
    $('#edit_code').val(code);
    $('#edit_name').val(name);
    $('#edit_desc').val(desc);
    $('#edit_status').val(status);
    $('#editCategoryModal').modal('show');
}
</script>
</body>
</html>

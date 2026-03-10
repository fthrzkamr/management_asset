<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { exit("Unauthorized"); }
if (!$auth->hasPermission('master_cabang', 'read')) { exit("Access Denied"); }

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total data
$count_sql = "SELECT COUNT(*) as total FROM master_cabang";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Query data dengan limit
$sql = "SELECT * FROM master_cabang ORDER BY id_cabang ASC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
?>

<div class="table-responsive">
    <table class="table table-hover table-center mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 1%;">No</th>
                <th class="text-center">Nama Cabang</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php 
                $no = $offset + 1;
                while ($row = mysqli_fetch_assoc($result)): 
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['nama_cabang']) ?></td>
                        <td class="text-center">
                            <?php if ($auth->hasPermission('master_cabang', 'update')): ?>
                                <a href="cabang_edit.php?id=<?= urlencode($row['id_cabang']) ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('master_cabang', 'delete')): ?>
                                <a href="cabang_delete.php?id=<?= urlencode($row['id_cabang']) ?>" 
                                   class="btn btn-sm btn-danger btn-delete-confirm" 
                                   data-message="Yakin ingin menghapus cabang ini?" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center text-muted">Tidak ada data cabang</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<nav class="py-1">
    <ul class="pagination justify-content-start pagination-cabang">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>

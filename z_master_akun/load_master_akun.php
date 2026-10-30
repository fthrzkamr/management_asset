<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->hasPermission('master_akun', 'read')) {
    exit('<div class="alert alert-danger">Akses Ditolak</div>');
}

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$totalData = mysqli_query($conn, "SELECT COUNT(*) AS total FROM master_akun");
$rowTotal = mysqli_fetch_assoc($totalData);
$total = $rowTotal['total'];
$pages = ceil($total / $limit);

$query = mysqli_query($conn, "SELECT * FROM master_akun ORDER BY no_akun ASC LIMIT $start, $limit");
$no = $start + 1;

?>

<div class="table-responsive">
    <table class="table table-hover table-center mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 1%;">No</th>
                <th class="text-center">No Akun</th>
                <th class="text-center">Nama Akun</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td class="text-center"><?= htmlspecialchars($row['no_akun']); ?></td>
                    <td class="text-center"><?= htmlspecialchars($row['nama_akun']); ?></td>
                    <td class="text-center">
                        <?php if ($auth->hasPermission('master_akun', 'update')): ?>
                        <a href="master_akun_edit.php?id=<?= $row['id_ma']; ?>" class="btn btn-sm btn-warning" title="Edit Akun"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if ($auth->hasPermission('master_akun', 'delete')): ?>
                        <a href="master_akun_delete.php?id=<?= $row['id_ma']; ?>" class="btn btn-sm btn-danger btn-delete-confirm" data-message="Yakin ingin menghapus akun ini?" title="Hapus Akun"><i class="fas fa-trash-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<nav class="py-1">
    <ul class="pagination justify-content-start pagination-akun">
        <!-- Previous Button -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= $page - 1 ?>" <?= ($page <= 1) ? 'tabindex="-1"' : '' ?>>
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        </li>
        
        <!-- First Page -->
        <?php if ($page > 3): ?>
            <li class="page-item">
                <a class="page-link" href="#" data-page="1">1</a>
            </li>
            <?php if ($page > 4): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Pages around current -->
        <?php 
        $start_page = max(1, $page - 2);
        $end_page = min($pages, $page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        
        <!-- Last Page -->
        <?php if ($page < $pages - 2): ?>
            <?php if ($page < $pages - 3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item">
                <a class="page-link" href="#" data-page="<?= $pages ?>"><?= $pages ?></a>
            </li>
        <?php endif; ?>
        
        <!-- Next Button -->
        <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= $page + 1 ?>" <?= ($page >= $pages) ? 'tabindex="-1"' : '' ?>>
                Next <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>